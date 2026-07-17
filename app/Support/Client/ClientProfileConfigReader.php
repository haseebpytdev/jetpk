<?php

namespace App\Support\Client;

use App\Models\Agency;
use App\Models\AgencySetting;
use Illuminate\Support\Facades\Schema;

/**
 * Reads deployment profile fields from config/ota_client.php, config/ota-client.php,
 * and optional default-agency DB branding (shared by sync + export fallback paths).
 */
final class ClientProfileConfigReader
{
    /**
     * @var list<string>
     */
    public const MODULE_KEYS = [
        'sabre',
        'al_haider_group_ticketing',
        'accounting',
        'hotels',
        'visa',
        'payment_gateway',
        'dev_cp',
        'staff_panel',
        'admin_panel',
    ];

    /**
     * @return array<string, bool>
     */
    public function modulesFromConfig(): array
    {
        $modules = config('ota_client.modules', []);
        if (! is_array($modules)) {
            $modules = [];
        }

        return $this->normalizeModules($modules);
    }

    /**
     * @return array{
     *     company_name: string,
     *     domain: string,
     *     phone: string,
     *     email: string,
     *     address: string,
     *     footer_text: string,
     *     primary_color: string,
     *     secondary_color: string,
     *     accent_color: string,
     *     logo_path: string,
     *     favicon_path: string,
     *     timezone: string,
     *     currency: string,
     *     storage_logo_path: ?string,
     *     storage_favicon_path: ?string,
     *     storage_hero_image_path: ?string
     * }
     */
    public function brandingFromConfig(?array $agencyBranding = null): array
    {
        $clientConfig = config('ota-client', []);
        if (! is_array($clientConfig)) {
            $clientConfig = [];
        }

        $db = $agencyBranding;

        $companyName = $this->firstNonEmpty(
            $db['company_name'] ?? null,
            $clientConfig['agency_name'] ?? null,
            config('app.name'),
            'Travel',
        );

        $domain = $this->firstNonEmpty(
            $db['domain'] ?? null,
            $this->domainFromAppUrl(),
            $clientConfig['domain_preview'] ?? null,
            'example.com',
        );

        $phone = $this->firstNonEmpty(
            $db['phone'] ?? null,
            $clientConfig['support_phone'] ?? null,
            '',
        );

        $email = $this->firstNonEmpty(
            $db['email'] ?? null,
            $clientConfig['support_email'] ?? null,
            config('mail.from.address'),
            '',
        );

        $address = $this->firstNonEmpty(
            $db['address'] ?? null,
            $this->addressFromConfig($clientConfig),
            '',
        );

        $footerText = $this->firstNonEmpty(
            $db['footer_text'] ?? null,
            $clientConfig['footer_text'] ?? null,
            '',
        );

        $primaryColor = $this->firstNonEmpty(
            $db['primary_color'] ?? null,
            $clientConfig['primary_color'] ?? null,
            '#0c4a6e',
        );

        $secondaryColor = $this->firstNonEmpty(
            $db['secondary_color'] ?? null,
            '#0ea5e9',
        );

        $accentColor = $this->firstNonEmpty(
            $db['accent_color'] ?? null,
            '#f59e0b',
        );

        $logoPath = $this->normalizeBrandingAssetPath(
            $db['logo_path'] ?? null,
            'logo/logo.svg',
            'logo',
        );

        $faviconPath = $this->normalizeBrandingAssetPath(
            $db['favicon_path'] ?? null,
            'favicon/favicon.ico',
            'favicon',
        );

        $timezone = $this->firstNonEmpty(
            $db['timezone'] ?? null,
            config('app.timezone'),
            'Asia/Karachi',
        );

        $currency = $this->firstNonEmpty(
            $db['currency'] ?? null,
            'PKR',
        );

        return [
            'company_name' => $companyName,
            'domain' => $domain,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'footer_text' => $footerText,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'accent_color' => $accentColor,
            'logo_path' => $logoPath,
            'favicon_path' => $faviconPath,
            'timezone' => $timezone,
            'currency' => $currency,
            'storage_logo_path' => $db['storage_logo_path'] ?? null,
            'storage_favicon_path' => $db['storage_favicon_path'] ?? null,
            'storage_hero_image_path' => $db['storage_hero_image_path'] ?? null,
            'header_logo_height' => $db['header_logo_height'] ?? null,
        ];
    }

    /**
     * @return array{
     *     slug: string,
     *     name: string,
     *     theme: string,
     *     asset_profile: string,
     *     environment: string,
     *     default_locale: string,
     *     timezone: string,
     *     currency: string,
     *     domain: string,
     *     modules: array<string, bool>,
     *     branding: array<string, mixed>,
     *     is_master_profile: bool
     * }
     */
    public function profilePayloadFromConfig(?string $slug = null, bool $mergeAgencyBranding = false): array
    {
        $slug = trim((string) ($slug ?? ''));
        if ($slug === '') {
            $slug = ClientProfile::slug();
        }
        if ($slug === '') {
            $slug = trim((string) config('client.canonical_client.slug', '')) ?: 'jetpk';
        }

        $agencyBranding = $mergeAgencyBranding ? $this->loadAgencyBranding() : null;
        $branding = $this->brandingFromConfig($agencyBranding);
        $assetProfile = ClientProfile::assetProfile();
        if ($assetProfile === '') {
            $assetProfile = $slug;
        }

        return [
            'slug' => $slug,
            'name' => $branding['company_name'],
            'theme' => ClientProfile::theme(),
            'asset_profile' => $assetProfile,
            'environment' => (string) config('app.env', 'production'),
            'default_locale' => (string) config('app.locale', 'en'),
            'timezone' => $branding['timezone'],
            'currency' => $branding['currency'],
            'domain' => $branding['domain'],
            'modules' => $this->modulesFromConfig(),
            'branding' => $branding,
            'is_master_profile' => ! config('client.standalone', true)
                && $slug === trim((string) config('ota_client.master_client_slug', config('client.canonical_client.slug', 'jetpk'))),
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    public function loadAgencyBranding(): ?array
    {
        if (! Schema::hasTable('agencies')) {
            return null;
        }

        $agencySlug = trim((string) config('ota.default_agency_slug', ''));
        if ($agencySlug === '') {
            return null;
        }

        $agency = Agency::query()->where('slug', $agencySlug)->first();
        if ($agency === null) {
            return null;
        }

        $settings = null;
        if (Schema::hasTable('agency_settings')) {
            $settings = AgencySetting::query()->where('agency_id', $agency->id)->first();
        }

        if ($settings === null) {
            return [
                'company_name' => $agency->name,
            ];
        }

        $addressParts = array_values(array_filter([
            trim((string) ($settings->office_address ?? '')),
            trim((string) ($settings->city ?? '')),
            trim((string) ($settings->country ?? '')),
        ], fn (string $part): bool => $part !== ''));

        $domain = trim((string) ($settings->website_url ?? ''));
        if ($domain !== '') {
            $parsedHost = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST);
            if (is_string($parsedHost) && $parsedHost !== '') {
                $domain = $parsedHost;
            }
        }

        return [
            'company_name' => $this->firstNonEmpty($settings->display_name, $agency->name),
            'domain' => $domain !== '' ? $domain : null,
            'phone' => trim((string) ($settings->support_phone ?? '')) ?: null,
            'email' => trim((string) ($settings->support_email ?? '')) ?: null,
            'address' => $addressParts !== [] ? implode(', ', $addressParts) : null,
            'footer_text' => trim((string) ($settings->footer_about ?? '')) ?: null,
            'primary_color' => trim((string) ($settings->primary_color ?? '')) ?: null,
            'secondary_color' => trim((string) ($settings->secondary_color ?? '')) ?: null,
            'accent_color' => trim((string) ($settings->accent_color ?? '')) ?: null,
            'logo_path' => trim((string) ($settings->logo_path ?? '')) ?: null,
            'favicon_path' => trim((string) ($settings->favicon_path ?? '')) ?: null,
            'storage_logo_path' => trim((string) ($settings->logo_path ?? '')) ?: null,
            'storage_favicon_path' => trim((string) ($settings->favicon_path ?? '')) ?: null,
            'storage_hero_image_path' => trim((string) ($settings->hero_image_path ?? '')) ?: null,
            'timezone' => trim((string) ($settings->timezone ?? '')) ?: null,
            'currency' => trim((string) ($settings->currency ?? '')) ?: null,
            'header_logo_height' => \App\Support\Branding\PlatformBrandingResolver::headerLogoHeight($settings),
        ];
    }

    /**
     * @param  array<string, mixed>  $modules
     * @return array<string, bool>
     */
    public function normalizeModules(array $modules): array
    {
        $normalized = [];
        foreach (self::MODULE_KEYS as $key) {
            $normalized[$key] = array_key_exists($key, $modules) ? (bool) $modules[$key] : true;
        }

        return $normalized;
    }

    public function normalizeBrandingAssetPath(?string $path, string $default, string $folder): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return $default;
        }

        if (str_contains($path, '://') || str_starts_with($path, '/')) {
            return $folder.'/'.basename($path);
        }

        if (str_contains($path, '/')) {
            return $folder.'/'.basename($path);
        }

        return $path;
    }

    private function domainFromAppUrl(): ?string
    {
        $url = trim((string) config('app.url', ''));
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    /**
     * @param  array<string, mixed>  $clientConfig
     */
    private function addressFromConfig(array $clientConfig): ?string
    {
        $city = trim((string) ($clientConfig['office_city'] ?? ''));

        return $city !== '' ? $city : null;
    }

    private function firstNonEmpty(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) && ! is_numeric($candidate)) {
                continue;
            }
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
