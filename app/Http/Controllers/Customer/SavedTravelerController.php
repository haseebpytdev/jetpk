<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Travelers\UpsertSavedTravelerRequest;
use App\Models\SavedTraveler;
use App\Models\User;
use App\Support\Geo\CountryList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SavedTravelerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SavedTraveler::class);

        $user = $request->user()->loadMissing('profile');
        $defaultTraveler = $this->resolveDefaultTraveler($user);
        $excludeId = $defaultTraveler['source'] === 'saved'
            ? $defaultTraveler['traveler']->id
            : null;

        $travelers = SavedTraveler::query()
            ->where('user_id', $user->id)
            ->when($excludeId !== null, fn ($q) => $q->whereKeyNot($excludeId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20);

        return view(client_view('travelers.index', 'customer'), [
            'travelers' => $travelers,
            'defaultTraveler' => $defaultTraveler,
            'routePrefix' => 'customer.travelers',
            'portalLabel' => 'Customer',
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SavedTraveler::class);

        return view(client_view('travelers.create', 'customer'), [
            'traveler' => new SavedTraveler,
            'routePrefix' => 'customer.travelers',
            'portalLabel' => 'Customer',
            'countries' => CountryList::forSelect(),
        ]);
    }

    public function store(UpsertSavedTravelerRequest $request): RedirectResponse
    {
        Gate::authorize('create', SavedTraveler::class);

        $user = $request->user();
        $traveler = SavedTraveler::query()->create(array_merge(
            $request->travelerPayload(),
            [
                'user_id' => $user->id,
                'agency_id' => $user->current_agency_id,
            ],
        ));

        $this->syncDefaultFlag($user->id, $user->current_agency_id, $traveler);

        return redirect()
            ->route('customer.travelers.index')
            ->with('status', 'traveler-saved');
    }

    public function edit(SavedTraveler $traveler): View
    {
        Gate::authorize('update', $traveler);

        return view(client_view('travelers.edit', 'customer'), [
            'traveler' => $traveler,
            'routePrefix' => 'customer.travelers',
            'portalLabel' => 'Customer',
            'countries' => CountryList::forSelect(),
        ]);
    }

    public function update(UpsertSavedTravelerRequest $request, SavedTraveler $traveler): RedirectResponse
    {
        Gate::authorize('update', $traveler);

        $traveler->update($request->travelerPayload());
        $this->syncDefaultFlag($traveler->user_id, $traveler->agency_id, $traveler);

        return redirect()
            ->route('customer.travelers.index')
            ->with('status', 'traveler-saved');
    }

    public function destroy(SavedTraveler $traveler): RedirectResponse
    {
        Gate::authorize('delete', $traveler);

        $traveler->delete();

        return redirect()
            ->route('customer.travelers.index')
            ->with('status', 'traveler-deleted');
    }

    /**
     * @return array{source: 'saved', traveler: SavedTraveler}|array{source: 'profile', card: array<string, mixed>}
     */
    protected function resolveDefaultTraveler(User $user): array
    {
        $saved = SavedTraveler::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        if ($saved !== null) {
            return ['source' => 'saved', 'traveler' => $saved];
        }

        return ['source' => 'profile', 'card' => $user->profileDefaultTravelerCard()];
    }

    protected function syncDefaultFlag(int $userId, ?int $agencyId, SavedTraveler $traveler): void
    {
        if (! $traveler->is_default) {
            return;
        }

        SavedTraveler::query()
            ->where('user_id', $userId)
            ->when(
                $agencyId !== null,
                fn ($q) => $q->where('agency_id', $agencyId),
                fn ($q) => $q->whereNull('agency_id'),
            )
            ->whereKeyNot($traveler->id)
            ->update(['is_default' => false]);
    }
}
