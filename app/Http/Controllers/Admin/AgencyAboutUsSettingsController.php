<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAboutUsSettingsRequest;
use App\Models\Agency;
use App\Services\Agencies\AboutUsContentPresenter;
use App\Services\Agencies\AgencyBrandingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyAboutUsSettingsController extends Controller
{
    public function __construct(
        protected AgencyBrandingService $brandingService,
        protected AboutUsContentPresenter $presenter,
    ) {}

    public function edit(Request $request): View
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('view', $agency);
        $settings = $this->brandingService->getSettingsForAgency($agency);

        return view('dashboard.admin.settings.about-us', [
            'agency' => $agency,
            'settings' => $settings,
            'aboutUs' => $this->presenter->presentForAdmin($settings),
        ]);
    }

    public function update(UpdateAboutUsSettingsRequest $request): RedirectResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        $payload = $this->presenter->buildPayloadForStorage($request->all());
        $this->brandingService->updateAboutUsSettings($agency, $request->user(), $payload);

        return back()->with('status', 'about-us-settings-updated');
    }

    protected function resolveAgency(Request $request): Agency
    {
        $user = $request->user();
        if ($user->isPlatformAdmin() && $request->filled('agency_id')) {
            return Agency::query()->findOrFail($request->integer('agency_id'));
        }

        return Agency::query()->findOrFail($user->current_agency_id);
    }
}
