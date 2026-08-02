<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\RespondsWithCustomerPortalJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travelers\UpsertSavedTravelerRequest;
use App\Models\SavedTraveler;
use App\Models\User;
use App\Support\CustomerPortal\CustomerPortalTravelersPresenter;
use App\Support\Geo\CountryList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SavedTravelerController extends Controller
{
    use RespondsWithCustomerPortalJson;

    public function __construct(
        protected CustomerPortalTravelersPresenter $travelersPresenter,
    ) {}

    public function index(Request $request): View|JsonResponse
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

        if ($this->wantsCustomerPortalJson($request)) {
            $default = $defaultTraveler['source'] === 'saved' ? $defaultTraveler['traveler'] : null;

            return $this->customerPortalJson($this->travelersPresenter->presentIndex($travelers, $default));
        }

        $viewData = [
            'travelers' => $travelers,
            'defaultTraveler' => $defaultTraveler,
            'routePrefix' => 'customer.travelers',
            'portalLabel' => 'Customer',
        ];

        return view(client_view('travelers.index', 'customer'), $viewData);
    }

    public function create(Request $request): View|JsonResponse
    {
        Gate::authorize('create', SavedTraveler::class);

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->travelersPresenter->presentForm());
        }

        $viewData = [
            'traveler' => new SavedTraveler,
            'routePrefix' => 'customer.travelers',
            'portalLabel' => 'Customer',
            'countries' => CountryList::forSelect(),
        ];

        return view(client_view('travelers.create', 'customer'), $viewData);
    }

    public function store(UpsertSavedTravelerRequest $request): RedirectResponse|JsonResponse
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

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->travelersPresenter->presentStored($traveler), 201);
        }

        return redirect()
            ->route('customer.travelers.index')
            ->with('status', 'traveler-saved');
    }

    public function edit(Request $request, SavedTraveler $traveler): View|JsonResponse
    {
        Gate::authorize('update', $traveler);

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->travelersPresenter->presentForm($traveler));
        }

        $viewData = [
            'traveler' => $traveler,
            'routePrefix' => 'customer.travelers',
            'portalLabel' => 'Customer',
            'countries' => CountryList::forSelect(),
        ];

        return view(client_view('travelers.edit', 'customer'), $viewData);
    }

    public function update(UpsertSavedTravelerRequest $request, SavedTraveler $traveler): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $traveler);

        $traveler->update($request->travelerPayload());
        $this->syncDefaultFlag($traveler->user_id, $traveler->agency_id, $traveler);

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->travelersPresenter->presentStored($traveler->fresh()));
        }

        return redirect()
            ->route('customer.travelers.index')
            ->with('status', 'traveler-saved');
    }

    public function destroy(Request $request, SavedTraveler $traveler): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $traveler);

        $traveler->delete();

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson([
                'ok' => true,
                'message' => 'Traveler removed.',
                'redirect_url' => '/customer/travelers',
            ]);
        }

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
