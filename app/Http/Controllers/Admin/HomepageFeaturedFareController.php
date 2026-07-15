<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HomepageFeaturedFareRefreshStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHomepageFeaturedFareRequest;
use App\Http\Requests\Admin\UpdateHomepageFeaturedFareRequest;
use App\Models\HomepageFeaturedFare;
use App\Services\Homepage\HomepageFeaturedFareRefreshService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HomepageFeaturedFareController extends Controller
{
    public function __construct(
        protected HomepageFeaturedFareRefreshService $refreshService,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', HomepageFeaturedFare::class);

        return redirect()->to(route('admin.settings.homepage.edit').'#featured-fares');
    }

    public function store(StoreHomepageFeaturedFareRequest $request): RedirectResponse
    {
        Gate::authorize('create', HomepageFeaturedFare::class);

        HomepageFeaturedFare::query()->create($this->payload($request) + [
            'agency_id' => $this->resolveAgencyId($request),
            'last_status' => HomepageFeaturedFareRefreshStatus::Pending,
        ]);

        return redirect()
            ->to(route('admin.settings.homepage.edit').'#featured-fares')
            ->with('status', 'featured-fare-created');
    }

    public function edit(HomepageFeaturedFare $homepageFeaturedFare): View
    {
        Gate::authorize('view', $homepageFeaturedFare);

        return view('dashboard.admin.settings.homepage-featured-fare-edit', [
            'fare' => $homepageFeaturedFare,
            'offsetOptions' => HomepageFeaturedFare::ALLOWED_DATE_OFFSETS,
        ]);
    }

    public function update(UpdateHomepageFeaturedFareRequest $request, HomepageFeaturedFare $homepageFeaturedFare): RedirectResponse
    {
        Gate::authorize('update', $homepageFeaturedFare);

        $homepageFeaturedFare->update($this->payload($request));

        return redirect()
            ->to(route('admin.settings.homepage.edit').'#featured-fares')
            ->with('status', 'featured-fare-updated');
    }

    public function destroy(HomepageFeaturedFare $homepageFeaturedFare): RedirectResponse
    {
        Gate::authorize('delete', $homepageFeaturedFare);

        $homepageFeaturedFare->delete();

        return redirect()
            ->to(route('admin.settings.homepage.edit').'#featured-fares')
            ->with('status', 'featured-fare-deleted');
    }

    public function refresh(HomepageFeaturedFare $homepageFeaturedFare): RedirectResponse
    {
        Gate::authorize('refresh', $homepageFeaturedFare);

        $this->refreshService->refreshOne($homepageFeaturedFare);

        return redirect()
            ->to(route('admin.settings.homepage.edit').'#featured-fares')
            ->with('status', 'featured-fare-refreshed');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        return [
            'title' => $request->string('title')->toString() ?: null,
            'origin_code' => strtoupper($request->string('origin_code')->toString()),
            'destination_code' => strtoupper($request->string('destination_code')->toString()),
            'date_offset_days' => (int) $request->input('date_offset_days'),
            'cabin' => $request->string('cabin')->toString() ?: 'economy',
            'adults' => max(1, (int) $request->input('adults', 1)),
            'is_enabled' => $request->boolean('is_enabled'),
            'sort_order' => (int) $request->input('sort_order', 100),
        ];
    }

    protected function scopedQuery($user): Builder
    {
        $query = HomepageFeaturedFare::query();

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    protected function resolveAgencyId(Request $request): int
    {
        $agencyId = $request->user()->current_agency_id;
        abort_if($agencyId === null, 403, 'No agency context assigned.');

        return $agencyId;
    }
}
