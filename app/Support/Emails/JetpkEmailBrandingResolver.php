<?php

namespace App\Support\Emails;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\JetPakistanClientProfileProvisioner;

/**
 * JetpkEmailBrandingResolver
 *
 * Resolves the JetPakistan `$emailBrand` array used by every JetPK email view.
 * Read-only and client-specific.
 *
 * Resolution order for each field:
 *   1. Existing client/branding source (wire this into fetchClientProfile()).
 *   2. config('jetpk_email.brand.*') overrides.
 *   3. Safe JetPK constants (never Master branding).
 *
 * IMPORTANT for integrators (Cursor):
 *   - Replace the body of fetchClientProfile() with your real client/branding
 *     lookup (e.g. Client::where('slug', 'jetpk')->first(), a branding row,
 *     or an asset profile helper). Return an associative array using the keys
 *     documented below, or an empty array if not found.
 *   - Do NOT fall back to Master branding anywhere.
 */
class JetpkEmailBrandingResolver
{
    public const CLIENT_SLUG = 'jetpk';

    /** Safe JetPK defaults. These must never be Master values. */
    protected static array $defaults = [
        'client_slug'      => self::CLIENT_SLUG,
        'brand_name'       => 'JetPakistan',
        'legal_name'       => 'JetPakistan',
        'logo_url'         => null,
        'home_url'         => null,
        'manage_url'       => null,
        'support_email'    => 'support@jetpakistan.com',
        'support_phone'    => null,
        'primary_color'    => '#00843D',
        'accent_color'     => '#F58220',
        'text_color'       => '#0f2435',
        'muted_color'      => '#64748b',
        'background_color' => '#eef6f9',
        'border_color'     => '#d9e6ee',
        'card_color'       => '#ffffff',
        'footer_text'      => 'You are receiving this email because you used JetPakistan services.',
        'address'          => null,
    ];

    /**
     * Resolve the normalized branding array.
     *
     * @param  string|null  $clientSlug
     * @return array<string, mixed>
     */
    public static function resolve(?string $clientSlug = self::CLIENT_SLUG): array
    {
        // Only JetPK is supported here. Any other slug still returns safe JetPK
        // constants rather than leaking Master branding.
        $profile = ($clientSlug === self::CLIENT_SLUG) ? static::fetchClientProfile() : [];
        $config  = static::configBrand();

        $brand = array_merge(static::$defaults, $config, static::onlyFilled($profile));

        if (empty($brand['home_url'])) {
            $appUrl = trim((string) config('app.url', ''));
            $brand['home_url'] = static::absoluteUrl($appUrl !== '' ? $appUrl : null)
                ?? trim((string) env('JETPK_HOME_URL', 'https://www.jetpakistan.com'));
        }

        // Normalise logo to an absolute URL (or null for text fallback).
        $brand['logo_url'] = static::absoluteUrl($brand['logo_url'] ?? null);
        $brand['home_url'] = static::absoluteUrl($brand['home_url'] ?? null) ?? ($brand['home_url'] ?? null);

        // Guarantee the client slug is always JetPK for these views.
        $brand['client_slug'] = self::CLIENT_SLUG;

        return $brand;
    }

    /**
     * Fetch the JetPK client/branding profile from DB or JetPK seed defaults.
     *
     * @return array<string, mixed>
     */
    protected static function fetchClientProfile(): array
    {
        try {
            if (function_exists('app')) {
                /** @var ClientProfileResolver $resolver */
                $resolver = app(ClientProfileResolver::class);
                $profile = $resolver->resolveBySlug(self::CLIENT_SLUG);
                if ($profile instanceof ClientProfile) {
                    return static::profileFromDb($profile);
                }
            }
        } catch (\Throwable $e) {
            // Fall through to JetPK seed defaults â€” never Master branding.
        }

        return static::profileFromSeedDefaults();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function profileFromDb(ClientProfile $profile): array
    {
        $branding = $profile->branding;
        $brandName = static::firstNonEmpty(
            $branding?->company_name,
            $profile->name,
            'JetPakistan',
        );

        $config = is_array($branding?->config) ? $branding->config : [];
        $homeUrl = static::firstNonEmpty(
            $config['website'] ?? null,
            static::previewHomeUrl($profile->preview_path),
        );

        $manageUrl = static::previewManageUrl($profile->preview_path);

        return static::onlyFilled([
            'brand_name'    => $brandName,
            'legal_name'    => $brandName,
            'logo_url'      => static::resolveLogoUrl($profile, $branding),
            'home_url'      => $homeUrl,
            'manage_url'    => $manageUrl,
            'support_email' => $branding?->email,
            'support_phone' => $branding?->phone,
            'primary_color' => $branding?->primary_color,
            'accent_color'  => $branding?->accent_color,
            'address'       => $branding?->address,
            'footer_text'   => $branding?->footer_text,
        ]);
    }

    /**
     * JetPK provisioner defaults â€” used when the DB profile is absent.
     * Never reads Master config/ota-client.php branding.
     *
     * @return array<string, mixed>
     */
    protected static function profileFromSeedDefaults(): array
    {
        $previewPath = '/'.JetPakistanClientProfileProvisioner::SLUG;

        return static::onlyFilled([
            'brand_name'    => 'JetPakistan',
            'legal_name'    => 'JetPakistan',
            'logo_url'      => static::resolveLogoUrlFromPaths('jetpk-assets', 'logo/logo.svg'),
            'home_url'      => static::previewHomeUrl($previewPath) ?? 'https://www.jetpakistan.com',
            'manage_url'    => static::previewManageUrl($previewPath),
            'support_email' => 'support@jetpakistan.com',
            'support_phone' => '+92 21 111 000 000',
            'primary_color' => '#00843D',
            'accent_color'  => '#F58220',
            'address'       => 'Karachi, Pakistan',
            'footer_text'   => 'JetPakistan â€” your gateway to seamless travel.',
        ]);
    }

    protected static function resolveLogoUrl(ClientProfile $profile, ?ClientProfileBranding $branding): ?string
    {
        $assetProfile = static::firstNonEmpty($profile->asset_profile, 'jetpk-assets');
        $logoPath = static::firstNonEmpty($branding?->logo_path, 'logo/logo.svg');

        return static::resolveLogoUrlFromPaths($assetProfile, $logoPath);
    }

    protected static function resolveLogoUrlFromPaths(string $assetProfile, string $logoPath): ?string
    {
        $assetProfile = trim($assetProfile);
        $logoPath = trim($logoPath);
        if ($assetProfile === '' || $logoPath === '') {
            return null;
        }

        $relative = 'client-assets/'.$assetProfile.'/'.ltrim($logoPath, '/');
        if (! is_file(public_path($relative))) {
            return null;
        }

        if (function_exists('asset')) {
            try {
                return asset($relative);
            } catch (\Throwable $e) {
                return static::absoluteUrl($relative);
            }
        }

        return static::absoluteUrl($relative);
    }

    protected static function previewHomeUrl(?string $previewPath): ?string
    {
        $previewPath = trim((string) $previewPath);
        if ($previewPath === '') {
            return null;
        }

        $path = '/'.ltrim($previewPath, '/');

        if (function_exists('url')) {
            try {
                return url($path);
            } catch (\Throwable $e) {
                return static::absoluteUrl($path);
            }
        }

        return static::absoluteUrl($path);
    }

    protected static function previewManageUrl(?string $previewPath): ?string
    {
        $previewPath = trim((string) $previewPath);
        if ($previewPath === '') {
            return null;
        }

        $path = rtrim('/'.ltrim($previewPath, '/'), '/').'/lookup-booking';

        if (function_exists('url')) {
            try {
                return url($path);
            } catch (\Throwable $e) {
                return static::absoluteUrl($path);
            }
        }

        return static::absoluteUrl($path);
    }

    protected static function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function configBrand(): array
    {
        if (function_exists('config')) {
            $brand = config('jetpk_email.brand');
            if (is_array($brand)) {
                return static::onlyFilled($brand);
            }
        }

        return [];
    }

    /**
     * Keep only non-null / non-empty values so fallbacks are not overwritten.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function onlyFilled(array $values): array
    {
        return array_filter($values, static function ($v) {
            return !is_null($v) && $v !== '';
        });
    }

    /**
     * Build an absolute URL. Relative paths are resolved against the app URL
     * (via url() when available, else config('app.url')). Returns null if empty.
     */
    protected static function absoluteUrl(?string $value): ?string
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Already absolute (http/https or protocol-relative).
        if (preg_match('#^(https?:)?//#i', $value)) {
            return $value;
        }

        $path = '/' . ltrim($value, '/');

        if (function_exists('url')) {
            try {
                return url($path);
            } catch (\Throwable $e) {
                // fall through to config-based build
            }
        }

        $base = null;
        if (function_exists('config')) {
            $base = config('app.url');
        }
        $base = $base ?: 'https://jetpakistan.pk';

        return rtrim($base, '/') . $path;
    }
}

