<?php

namespace App\Support\Emails;

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
        'home_url'         => 'https://ota.haseebasif.com/jetpk',
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

        // Normalise logo to an absolute URL (or null for text fallback).
        $brand['logo_url'] = static::absoluteUrl($brand['logo_url'] ?? null);
        $brand['home_url'] = static::absoluteUrl($brand['home_url'] ?? null) ?? ($brand['home_url'] ?? null);

        // Guarantee the client slug is always JetPK for these views.
        $brand['client_slug'] = self::CLIENT_SLUG;

        return $brand;
    }

    /**
     * Fetch the JetPK client/branding profile.
     *
     * TODO(integrator): replace with the real lookup. Return an associative
     * array of any subset of the documented keys, or [] when not found.
     *
     * Example (pseudo):
     *   $client = \App\Models\Client::where('slug', 'jetpk')->first();
     *   if (! $client) return [];
     *   return [
     *       'brand_name'    => $client->company_name,
     *       'logo_url'      => $client->logo_path,   // will be absolutised below
     *       'support_email' => $client->support_email,
     *       'support_phone' => $client->support_phone,
     *       'home_url'      => $client->website_url,
     *       'primary_color' => $client->primary_color,
     *       'accent_color'  => $client->accent_color,
     *       'address'       => $client->address,
     *   ];
     *
     * @return array<string, mixed>
     */
    protected static function fetchClientProfile(): array
    {
        // Left intentionally empty so the resolver is safe out of the box.
        // Until wired, emails use config overrides + safe JetPK constants.
        return [];
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
        $base = $base ?: 'https://ota.haseebasif.com';

        return rtrim($base, '/') . $path;
    }
}
