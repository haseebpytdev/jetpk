<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Agencies\SlimTopbarPresenter;
use App\Services\Communication\AgencyCommunicationSettingsService;
use App\Services\Media\BackgroundRemovalSettingsService;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Branding\BrandDisplayResolver;
use App\Support\Branding\PlatformBrandingResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyBrandingController extends Controller
{
    public function __construct(
        protected AgencyBrandingService $brandingService,
        protected SlimTopbarPresenter $slimTopbarPresenter,
        protected AgencyCommunicationSettingsService $communicationSettingsService,
        protected BackgroundRemovalSettingsService $backgroundRemovalSettingsService,
    ) {}

    public function edit(Request $request): View
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('view', $agency);
        $settings = $this->brandingService->getSettingsForAgency($agency);
        $communication = $this->communicationSettingsService->getOrCreateSettings($agency);
        $meta = is_array($settings->meta) ? $settings->meta : [];

        $logoPath = $settings->logo_path;
        $bgSettings = $this->backgroundRemovalSettingsService->getForAgency($agency);

        return view(client_view('settings.branding', 'admin'), [
            'agency' => $agency,
            'settings' => $settings,
            'communication' => $communication,
            'companyPrefix' => AgencyPrefixService::storedPrefix($agency) ?? AgencyPrefixService::suggestPrefix($agency->name, (int) $agency->id),
            'customerReferencePrefix' => PlatformBrandingResolver::customerReferencePrefix($settings),
            'agentReferencePrefix' => PlatformBrandingResolver::agentReferencePrefix($settings),
            'colorScheme' => BrandDisplayResolver::colorSchemeKey($settings),
            'colorSchemeOptions' => config('ota-brand-schemes.presets', []),
            'logoUrl' => is_string($logoPath) && $logoPath !== '' ? asset('storage/'.$logoPath) : null,
            'slimTopbar' => $this->slimTopbarPresenter->presentForAdmin($settings),
            'headerLogoHeight' => PlatformBrandingResolver::headerLogoHeight($settings),
            'defaultHeaderLogoHeight' => PlatformBrandingResolver::DEFAULT_HEADER_LOGO_HEIGHT,
            'minHeaderLogoHeight' => PlatformBrandingResolver::MIN_HEADER_LOGO_HEIGHT,
            'maxHeaderLogoHeight' => PlatformBrandingResolver::MAX_HEADER_LOGO_HEIGHT,
            'backgroundRemovalEnabled' => (bool) $bgSettings->is_enabled,
            'backgroundRemovalDefaultForLogos' => (bool) $bgSettings->default_for_logos,
            'backgroundRemovalSettingsUrl' => client_route('admin.settings.background-removal.edit'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'company_prefix' => ['nullable', 'string', 'min:2', 'max:4', 'regex:/^[A-Z0-9]+$/'],
            'customer_reference_prefix' => ['nullable', 'string', 'min:2', 'max:4', 'regex:/^[A-Z0-9]+$/'],
            'agent_reference_prefix' => ['nullable', 'string', 'min:2', 'max:4', 'regex:/^[A-Z0-9]+$/'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:100'],
            'support_whatsapp' => ['nullable', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'office_address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'color_scheme' => ['nullable', 'string', 'in:blue_travel,green_umrah,dark_premium,custom,logo_auto_1,logo_auto_2,logo_auto_3'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'header_cta_label' => ['nullable', 'string', 'max:255'],
            'header_cta_url' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'favicon' => ['nullable', 'file', 'max:1024'],
            'hero_image' => ['nullable', 'image', 'max:5120'],
            'header_logo_height' => ['nullable', 'integer', 'min:'.PlatformBrandingResolver::MIN_HEADER_LOGO_HEIGHT, 'max:'.PlatformBrandingResolver::MAX_HEADER_LOGO_HEIGHT],
            'slim_topbar_enabled' => ['nullable', 'boolean'],
            'slim_topbar_message' => ['nullable', 'string', 'max:255'],
            'slim_topbar_show_phone' => ['nullable', 'boolean'],
            'slim_topbar_show_email' => ['nullable', 'boolean'],
            'slim_topbar_show_whatsapp' => ['nullable', 'boolean'],
            'slim_topbar_background_color' => ['nullable', 'string', 'max:20'],
            'slim_topbar_text_color' => ['nullable', 'string', 'max:20'],
            'slim_topbar_accent_color' => ['nullable', 'string', 'max:20'],
        ]);

        $setting = $this->brandingService->getSettingsForAgency($agency);

        if (filled($validated['company_prefix'] ?? null)) {
            AgencyPrefixService::savePrefix($agency, (string) $validated['company_prefix']);
        }
        unset($validated['company_prefix']);

        $referencePrefixMeta = [];
        if (filled($validated['customer_reference_prefix'] ?? null)) {
            $referencePrefixMeta[PlatformBrandingResolver::META_CUSTOMER_REFERENCE_PREFIX] = AgencyPrefixService::sanitizePrefix((string) $validated['customer_reference_prefix']);
        }
        unset($validated['customer_reference_prefix']);
        if (filled($validated['agent_reference_prefix'] ?? null)) {
            $referencePrefixMeta[PlatformBrandingResolver::META_AGENT_REFERENCE_PREFIX] = AgencyPrefixService::sanitizePrefix((string) $validated['agent_reference_prefix']);
        }
        unset($validated['agent_reference_prefix']);

        if (array_key_exists('mail_from_name', $validated)) {
            $this->communicationSettingsService->updateSettings($agency, $request->user(), [
                'mail_from_name' => $validated['mail_from_name'],
            ]);
            unset($validated['mail_from_name']);
        }

        foreach ([
            'logo' => 'logo_path',
            'favicon' => 'favicon_path',
            'hero_image' => 'hero_image_path',
        ] as $inputKey => $settingKey) {
            if ($request->hasFile($inputKey)) {
                $media = $this->brandingService->uploadMedia($agency, $request->user(), $request->file($inputKey), 'branding', null);
                $validated[$settingKey] = $media->file_path;
            }
        }

        $colorScheme = (string) ($validated['color_scheme'] ?? BrandDisplayResolver::colorSchemeKey($setting));
        unset($validated['color_scheme']);
        $validated = BrandDisplayResolver::applyColorSchemeToPayload($colorScheme, $validated);

        foreach ([
            'slim_topbar_enabled',
            'slim_topbar_message',
            'slim_topbar_show_phone',
            'slim_topbar_show_email',
            'slim_topbar_show_whatsapp',
            'slim_topbar_background_color',
            'slim_topbar_text_color',
            'slim_topbar_accent_color',
        ] as $topbarField) {
            unset($validated[$topbarField]);
        }

        $slimTopbar = $this->slimTopbarPresenter->buildForStorage($request->all());

        $this->brandingService->updateSettings($agency, $request->user(), $validated, $colorScheme, $slimTopbar);

        if ($referencePrefixMeta !== []) {
            $settings = $this->brandingService->getSettingsForAgency($agency)->fresh();
            $meta = is_array($settings->meta) ? $settings->meta : [];
            $settings->forceFill(['meta' => array_merge($meta, $referencePrefixMeta)])->save();
        }

        return back()->with('status', 'branding-updated');
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
