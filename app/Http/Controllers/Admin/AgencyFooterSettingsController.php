<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFooterSettingsRequest;
use App\Models\Agency;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Agencies\FooterSettingsPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyFooterSettingsController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected AgencyBrandingService $brandingService,
        protected FooterSettingsPresenter $presenter,
    ) {}

    public function edit(Request $request): View|JsonResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('view', $agency);
        $settings = $this->brandingService->getSettingsForAgency($agency);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'footer' => $this->presenter->presentForAdmin($settings),
            ]);
        }

        return view('dashboard.admin.settings.footer', [
            'agency' => $agency,
            'settings' => $settings,
            'footer' => $this->presenter->presentForAdmin($settings),
            'menuSectionKeys' => FooterSettingsPresenter::MENU_SECTION_KEYS,
            'socialPlatforms' => FooterSettingsPresenter::SOCIAL_PLATFORMS,
            'spacingOptions' => FooterSettingsPresenter::SPACING_OPTIONS,
        ]);
    }

    public function update(UpdateFooterSettingsRequest $request): RedirectResponse|JsonResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        $settings = $this->brandingService->getSettingsForAgency($agency);

        $payload = $this->presenter->buildPayloadForStorage($request->all(), $settings);

        $footerLogoPath = null;
        if ($request->hasFile('footer_logo')) {
            $media = $this->brandingService->uploadMedia(
                $agency,
                $request->user(),
                $request->file('footer_logo'),
                'branding',
                null,
            );
            $footerLogoPath = $media->file_path;
        }

        $this->brandingService->updateFooterSettings($agency, $request->user(), $payload, $footerLogoPath);

        if ($this->wantsBackOfficeJson($request)) {
            $fresh = $this->brandingService->getSettingsForAgency($agency)->fresh();

            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'footer-settings-updated',
                'footer' => $this->presenter->presentForAdmin($fresh),
            ]);
        }

        return back()->with('status', 'footer-settings-updated');
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
