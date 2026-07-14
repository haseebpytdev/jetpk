<?php

namespace App\Support\Branding;

use App\Models\AgencySetting;
use App\Models\User;

/**
 * Resolves public display name, page titles, and brand color tokens from agency settings + config fallbacks.
 */
class BrandDisplayResolver
{
    public const META_COLOR_SCHEME = 'color_scheme';

    public static function displayName(?AgencySetting $settings = null, ?User $user = null): string
    {
        if ($user?->isAgentPortalUser()) {
            $partner = trim($user->agentDisplayAgencyName());
            if ($partner !== '') {
                return $partner;
            }
        }

        if ($settings !== null) {
            $name = trim((string) ($settings->display_name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $client = config('ota-client', []);
        $brand = config('ota-brand', []);
        $name = trim((string) ($client['agency_name'] ?? $brand['product_name'] ?? $brand['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) config('app.name', 'Travel'));
    }

    public static function pageTitle(string $section, ?string $brandName = null): string
    {
        $section = trim($section);
        $brand = trim($brandName ?? self::displayName());

        if ($section === '') {
            return $brand;
        }

        return $section.' | '.$brand;
    }

    /**
     * @return array{primary: string, secondary: string, accent: string, primary_dark: string, scheme: string}
     */
    public static function themeColors(?AgencySetting $settings = null): array
    {
        $schemeKey = self::colorSchemeKey($settings);
        $presets = config('ota-brand-schemes.presets', []);
        $preset = is_array($presets[$schemeKey] ?? null) ? $presets[$schemeKey] : null;

        if (! self::usesStoredBrandColors($schemeKey) && $preset !== null) {
            $primary = self::normalizeHex((string) ($preset['primary'] ?? '')) ?? '#2563eb';

            return [
                'primary' => $primary,
                'secondary' => self::normalizeHex((string) ($preset['secondary'] ?? '')) ?? '#0ea5e9',
                'accent' => self::normalizeHex((string) ($preset['accent'] ?? '')) ?? '#f59e0b',
                'primary_dark' => self::darkenHex($primary),
                'scheme' => $schemeKey,
            ];
        }

        $client = config('ota-client', []);
        $primary = self::normalizeHex($settings?->primary_color)
            ?? self::normalizeHex((string) ($client['primary_color'] ?? ''))
            ?? '#2563eb';

        return [
            'primary' => $primary,
            'secondary' => self::normalizeHex($settings?->secondary_color) ?? '#0ea5e9',
            'accent' => self::normalizeHex($settings?->accent_color) ?? '#f59e0b',
            'primary_dark' => self::darkenHex($primary),
            'scheme' => $schemeKey,
        ];
    }

    public static function colorSchemeKey(?AgencySetting $settings): string
    {
        $meta = is_array($settings?->meta) ? $settings->meta : [];
        $key = (string) ($meta[self::META_COLOR_SCHEME] ?? config('ota-brand-schemes.default', 'blue_travel'));
        $allowed = array_keys(config('ota-brand-schemes.presets', []));

        return in_array($key, $allowed, true) ? $key : 'blue_travel';
    }

    /**
     * @return array<string, string>
     */
    public static function cssVariables(?AgencySetting $settings = null): array
    {
        $theme = self::themeColors($settings);

        return [
            '--brand-primary' => $theme['primary'],
            '--brand-primary-dark' => $theme['primary_dark'],
            '--brand-secondary' => $theme['secondary'],
            '--brand-accent' => $theme['accent'],
            '--client-primary' => $theme['primary'],
            '--ota-blue' => $theme['primary'],
            '--ota-blue-dark' => $theme['primary_dark'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function applyColorSchemeToPayload(string $colorScheme, array $validated): array
    {
        if (self::usesStoredBrandColors($colorScheme)) {
            return $validated;
        }

        $preset = config('ota-brand-schemes.presets.'.$colorScheme);
        if (! is_array($preset)) {
            return $validated;
        }

        foreach (['primary_color' => 'primary', 'secondary_color' => 'secondary', 'accent_color' => 'accent'] as $field => $presetKey) {
            if (isset($preset[$presetKey])) {
                $validated[$field] = (string) $preset[$presetKey];
            }
        }

        return $validated;
    }

    public static function usesStoredBrandColors(string $schemeKey): bool
    {
        if ($schemeKey === 'custom') {
            return true;
        }

        return str_starts_with($schemeKey, 'logo_auto_');
    }

    protected static function normalizeHex(?string $hex): ?string
    {
        if (! is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/', trim($hex)) !== 1) {
            return null;
        }

        return strtoupper(trim($hex));
    }

    protected static function darkenHex(string $hex, float $factor = 0.85): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#1d4ed8';
        }

        $r = max(0, min(255, (int) round(hexdec(substr($hex, 0, 2)) * $factor)));
        $g = max(0, min(255, (int) round(hexdec(substr($hex, 2, 2)) * $factor)));
        $b = max(0, min(255, (int) round(hexdec(substr($hex, 4, 2)) * $factor)));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}
