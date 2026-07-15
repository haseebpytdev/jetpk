<?php

namespace App\Services\Client;

/**
 * Config-driven registry of deployable client themes per portal area (MC-8A).
 *
 * Reads config/client_themes.php only — no DB or filesystem access.
 */
final class ClientThemeRegistry
{
    /**
     * @var list<string>
     */
    private const AREAS = ['frontend', 'admin', 'staff'];

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $area = null): array
    {
        if ($area === null) {
            $themes = [];
            foreach (self::AREAS as $portalArea) {
                foreach ($this->themesForArea($portalArea) as $theme) {
                    $themes[] = $theme;
                }
            }

            return $themes;
        }

        return array_values($this->themesForArea($this->normalizeArea($area)));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key, ?string $area = null): ?array
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        if ($area !== null) {
            $normalizedArea = $this->normalizeArea($area);
            $theme = $this->themesForArea($normalizedArea)[$key] ?? null;

            return is_array($theme) ? $theme : null;
        }

        foreach (self::AREAS as $portalArea) {
            $theme = $this->themesForArea($portalArea)[$key] ?? null;
            if (is_array($theme)) {
                return $theme;
            }
        }

        return null;
    }

    public function exists(string $key, ?string $area = null): bool
    {
        return $this->get($key, $area) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function active(?string $area = null): array
    {
        return array_values(array_filter(
            $this->all($area),
            static fn (array $theme): bool => ($theme['status'] ?? '') === 'active',
        ));
    }

    public function fallback(string $area): string
    {
        $normalizedArea = $this->normalizeArea($area);
        $fallback = trim((string) (config('client_themes.areas.'.$normalizedArea.'.fallback') ?? ''));

        if ($fallback !== '') {
            return $fallback;
        }

        return match ($normalizedArea) {
            'admin' => 'default-admin',
            'staff' => 'default-staff',
            default => 'v1-classic',
        };
    }

    public function assetBase(string $themeKey, string $area): string
    {
        $themeKey = trim($themeKey);
        $normalizedArea = $this->normalizeArea($area);

        $theme = $this->get($themeKey, $normalizedArea);
        if (is_array($theme)) {
            $assetBase = trim((string) ($theme['asset_base'] ?? ''));
            if ($assetBase !== '') {
                return $assetBase;
            }
        }

        return 'themes/'.$normalizedArea.'/'.$themeKey;
    }

    public function validateTheme(string $themeKey, string $area): bool
    {
        $themeKey = trim($themeKey);
        if ($themeKey === '') {
            return false;
        }

        $theme = $this->get($themeKey, $this->normalizeArea($area));

        return is_array($theme) && ($theme['status'] ?? '') === 'active';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function themesForArea(string $area): array
    {
        $themes = config('client_themes.areas.'.$area.'.themes', []);

        return is_array($themes) ? $themes : [];
    }

    private function normalizeArea(?string $area): string
    {
        if ($area === null) {
            return 'frontend';
        }

        $area = strtolower(trim($area));

        return in_array($area, self::AREAS, true) ? $area : 'frontend';
    }
}
