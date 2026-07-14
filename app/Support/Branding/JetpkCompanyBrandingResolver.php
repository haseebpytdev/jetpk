<?php

namespace App\Support\Branding;

use App\Models\Agency;
use App\Services\Branding\JetpkThemePaletteService;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Support\Facades\Storage;

/**
 * Canonical Company Branding resolver for JetPK dedicated deployment.
 *
 * Agency Settings → Branding is the authoritative source; client-assets paths are fallback only.
 */
final class JetpkCompanyBrandingResolver
{
    public function __construct(
        private readonly ClientProfileConfigReader $configReader,
    ) {}

    public function isJetpkDeployment(): bool
    {
        return function_exists('ota_single_client_root_slug')
            && ota_single_client_root_slug() === 'jetpk';
    }

    /**
     * @return array<string, mixed>
     */
    public function brandingConfig(): array
    {
        $agency = $this->isJetpkDeployment() ? $this->configReader->loadAgencyBranding() : null;

        return $this->configReader->brandingFromConfig($agency);
    }

    public function companyName(): string
    {
        $config = $this->brandingConfig();

        return trim((string) ($config['company_name'] ?? '')) !== ''
            ? trim((string) $config['company_name'])
            : 'JetPakistan';
    }

    public function logoUrl(): ?string
    {
        $config = $this->brandingConfig();
        $storagePath = trim((string) ($config['storage_logo_path'] ?? ''));
        if ($storagePath !== '' && $this->storageExists($storagePath)) {
            return $this->storageUrl($storagePath);
        }

        $logoPath = trim((string) ($config['logo_path'] ?? ''));
        if ($logoPath === '') {
            return null;
        }

        if (str_starts_with($logoPath, 'agencies/') && $this->storageExists($logoPath)) {
            return $this->storageUrl($logoPath);
        }

        $profile = trim((string) config('ota_client.asset_profile', config('ota_client.slug', 'jetpk')));

        return asset('client-assets/'.$profile.'/'.$logoPath);
    }

    public function faviconUrl(): ?string
    {
        $config = $this->brandingConfig();
        $storagePath = trim((string) ($config['storage_favicon_path'] ?? ''));
        if ($storagePath !== '' && $this->storageExists($storagePath)) {
            return $this->storageUrl($storagePath);
        }

        $faviconPath = trim((string) ($config['favicon_path'] ?? ''));
        if ($faviconPath === '') {
            return null;
        }

        if (str_starts_with($faviconPath, 'agencies/') && $this->storageExists($faviconPath)) {
            return $this->storageUrl($faviconPath);
        }

        $profile = trim((string) config('ota_client.asset_profile', config('ota_client.slug', 'jetpk')));

        return asset('client-assets/'.$profile.'/'.$faviconPath);
    }

    public function hasConfiguredLogo(): bool
    {
        return $this->logoUrl() !== null;
    }

    public function headerLogoHeight(): int
    {
        $config = $this->brandingConfig();
        $stored = $config['header_logo_height'] ?? null;

        if ($stored === null || $stored === '') {
            return PlatformBrandingResolver::DEFAULT_HEADER_LOGO_HEIGHT;
        }

        return PlatformBrandingResolver::clampHeaderLogoHeight((int) $stored);
    }

    public function headerLogoHeightPx(): string
    {
        return $this->headerLogoHeight().'px';
    }

    /**
     * @return array{night: array<string, string>, day: array<string, string>}
     */
    public function publicCssVariableBlocks(): array
    {
        $height = $this->headerLogoHeightPx();
        $shared = [
            '--jp-header-logo-height' => $height,
            '--jp-logo-mark' => $height,
        ];

        if ($this->isJetpkDeployment()) {
            $agency = Agency::query()->orderBy('id')->first();
            if ($agency !== null) {
                $paletteService = app(JetpkThemePaletteService::class);
                $blocks = $paletteService->cssVariableBlocks($paletteService->palettesForAgency($agency));

                return [
                    'night' => array_merge($blocks['night'], $shared),
                    'day' => array_merge($blocks['day'], $shared),
                ];
            }
        }

        $config = $this->brandingConfig();
        $legacy = app(JetpkBrandPaletteCssResolver::class)->variablesFromHex(
            is_string($config['primary_color'] ?? null) ? $config['primary_color'] : null,
            is_string($config['secondary_color'] ?? null) ? $config['secondary_color'] : null,
            is_string($config['accent_color'] ?? null) ? $config['accent_color'] : null,
        );

        return [
            'night' => array_merge($legacy, $shared),
            'day' => array_merge($legacy, $shared),
        ];
    }

    public function publicCssVariables(): array
    {
        return $this->publicCssVariableBlocks()['night'];
    }

    /**
     * @return list<array{consumer: string, view: string, resolver: string, asset_key: string, fallback: string, status: string}>
     */
    public function consumptionMatrix(): array
    {
        return [
            ['consumer' => 'Public header', 'view' => 'themes/frontend/jetpakistan/partials/header.blade.php', 'resolver' => 'jetpk_company_branding()->logoUrl()', 'asset_key' => 'agency_settings.logo_path', 'fallback' => 'x-jp.brand-logo wordmark', 'status' => 'canonical'],
            ['consumer' => 'Public header logo size', 'view' => 'themes/frontend/jetpakistan/layouts/frontend.blade.php', 'resolver' => 'jetpk_company_branding()->headerLogoHeight()', 'asset_key' => 'agency_settings.meta.header_logo_height', 'fallback' => '36px default', 'status' => 'canonical'],
            ['consumer' => 'Public footer', 'view' => 'themes/frontend/jetpakistan/partials/footer.blade.php', 'resolver' => 'jetpk_company_branding()->logoUrl()', 'asset_key' => 'agency_settings.logo_path', 'fallback' => 'wordmark', 'status' => 'canonical'],
            ['consumer' => 'Mobile drawer', 'view' => 'themes/frontend/jetpakistan/partials/drawer.blade.php', 'resolver' => 'jetpk_company_branding()->logoUrl()', 'asset_key' => 'agency_settings.logo_path', 'fallback' => 'wordmark', 'status' => 'canonical'],
            ['consumer' => 'Portal header', 'view' => 'themes/frontend/jetpakistan/components/portal/header-brand.blade.php', 'resolver' => 'jetpk_company_branding()->logoUrl()', 'asset_key' => 'agency_settings.logo_path', 'fallback' => 'wordmark', 'status' => 'canonical'],
            ['consumer' => 'Dashboard sidebar', 'view' => 'themes/admin/jetpakistan/partials/sidebar.blade.php', 'resolver' => 'jetpk_company_branding()->logoUrl()', 'asset_key' => 'agency_settings.logo_path', 'fallback' => 'JP mark + wordmark', 'status' => 'canonical'],
            ['consumer' => 'Auth layout', 'view' => 'themes/frontend/jetpakistan/layouts/auth.blade.php', 'resolver' => 'jetpk_company_branding()', 'asset_key' => 'display_name', 'fallback' => 'JetPakistan copy', 'status' => 'canonical'],
            ['consumer' => 'Error shell', 'view' => 'themes/frontend/jetpakistan/errors/partials/shell.blade.php', 'resolver' => 'jetpk_company_branding()->companyName()', 'asset_key' => 'display_name', 'fallback' => 'JetPakistan', 'status' => 'canonical'],
            ['consumer' => 'Favicon', 'view' => 'themes/frontend/jetpakistan/layouts/frontend.blade.php', 'resolver' => 'jetpk_company_branding()->faviconUrl()', 'asset_key' => 'agency_settings.favicon_path', 'fallback' => 'client-assets favicon', 'status' => 'canonical'],
            ['consumer' => 'Email header', 'view' => 'emails/themes/jetpakistan/partials/header.blade.php', 'resolver' => 'JetpkEmailBrandingResolver', 'asset_key' => 'logo_url', 'fallback' => 'client-assets logo', 'status' => 'canonical'],
            ['consumer' => 'Page Settings Global', 'view' => 'themes/admin/jetpakistan/page-settings/partials/branding-ownership.blade.php', 'resolver' => 'link only', 'asset_key' => '—', 'fallback' => '—', 'status' => 'no-duplicate'],
            ['consumer' => 'Media Library', 'view' => 'themes/admin/jetpakistan/settings/media.blade.php', 'resolver' => 'general media only', 'asset_key' => '—', 'fallback' => '—', 'status' => 'no-duplicate'],
        ];
    }

    private function storageExists(string $path): bool
    {
        return Storage::disk('public')->exists($path)
            || is_file(public_path('storage/'.$path))
            || is_file(storage_path('app/public/'.$path));
    }

    private function storageUrl(string $path): string
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return asset('storage/'.$path);
    }
}
