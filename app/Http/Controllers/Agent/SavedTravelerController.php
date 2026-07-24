<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Travelers\UpsertSavedTravelerRequest;
use App\Models\SavedTraveler;
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

        $agencyId = $request->user()->current_agency_id;
        abort_if($agencyId === null, 403);

        $travelers = SavedTraveler::query()
            ->whereIn('user_id', $request->user()->ownerAgentPortalUserIds())
            ->where('agency_id', $agencyId)
            ->orderByDesc('is_default')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20);

        $viewData = [
            'travelers' => $travelers,
            'routePrefix' => 'agent.travelers',
            'portalLabel' => 'Agent',
        ];

        return view(client_view('travelers.index', 'agent'), $viewData);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SavedTraveler::class);
        abort_if($request->user()->agent() === null, 403);

        $viewData = [
            'traveler' => new SavedTraveler,
            'routePrefix' => 'agent.travelers',
            'portalLabel' => 'Agent',
            'countries' => CountryList::forSelect(),
        ];

        return view(client_view('travelers.create', 'agent'), $viewData);
    }

    public function store(UpsertSavedTravelerRequest $request): RedirectResponse
    {
        Gate::authorize('create', SavedTraveler::class);

        $user = $request->user();
        abort_if($user->agent() === null || $user->current_agency_id === null, 403);

        $traveler = SavedTraveler::query()->create(array_merge(
            $request->travelerPayload(),
            [
                'user_id' => $user->id,
                'agency_id' => $user->current_agency_id,
            ],
        ));

        $this->syncDefaultFlag($user->id, $user->current_agency_id, $traveler);

        return redirect()
            ->route('agent.travelers.index')
            ->with('status', 'traveler-saved');
    }

    public function edit(Request $request, SavedTraveler $traveler): View
    {
        Gate::authorize('update', $traveler);

        $viewData = [
            'traveler' => $traveler,
            'routePrefix' => 'agent.travelers',
            'portalLabel' => 'Agent',
            'countries' => CountryList::forSelect(),
        ];

        return view(client_view('travelers.edit', 'agent'), $viewData);
    }

    public function update(UpsertSavedTravelerRequest $request, SavedTraveler $traveler): RedirectResponse
    {
        Gate::authorize('update', $traveler);

        $traveler->update($request->travelerPayload());
        $this->syncDefaultFlag($traveler->user_id, $traveler->agency_id, $traveler);

        return redirect()
            ->route('agent.travelers.index')
            ->with('status', 'traveler-saved');
    }

    public function destroy(SavedTraveler $traveler): RedirectResponse
    {
        Gate::authorize('delete', $traveler);

        $traveler->delete();

        return redirect()
            ->route('agent.travelers.index')
            ->with('status', 'traveler-deleted');
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
