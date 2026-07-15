<?php

namespace App\Services\Client;

use App\Models\ClientProfile;
use App\Support\Client\ClientPublicWebrootPath;

/**
 * Resolves validated client themes at runtime from profile, config, and registry (MC-8A).
 *
 * Source priority per area: profile DB fields → config('ota_client') → registry fallback.
 * Invalid or inactive selections fall back to the area default from ClientThemeRegistry.
 */
final class RuntimeThemeManager
{
    /**
     * @var list<string>
     */
    private const AREAS = ['frontend', 'admin', 'staff'];

    /**
     * @var array<string, string>
     */
    private const PROFILE_COLUMNS = [
        'frontend' => 'active_frontend_theme',
        'admin' => 'active_admin_theme',
        'staff' => 'active_staff_theme',
    ];

    /**
     * @var array<string, string>
     */
    private const CONFIG_KEYS = [
        'frontend' => 'theme',
        'admin' => 'admin_theme',
        'staff' => 'staff_theme',
    ];

    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly ClientThemeRegistry $registry,
    ) {}

    public function frontend(?ClientProfile $profile = null): string
    {
        return $this->forArea('frontend', $profile);
    }

    public function admin(?ClientProfile $profile = null): string
    {
        return $this->forArea('admin', $profile);
    }

    public function staff(?ClientProfile $profile = null): string
    {
        return $this->forArea('staff', $profile);
    }

    public function forArea(string $area, ?ClientProfile $profile = null): string
    {
        return $this->resolveArea($this->normalizeArea($area), $profile)['resolved'];
    }

    public function assetBase(string $area = 'frontend', ?ClientProfile $profile = null): string
    {
        $normalizedArea = $this->normalizeArea($area);
        $resolved = $this->resolveArea($normalizedArea, $profile)['resolved'];

        return $this->registry->assetBase($resolved, $normalizedArea);
    }

    public function assetUrl(string $path, string $area = 'frontend', ?ClientProfile $profile = null): string
    {
        $base = rtrim($this->assetBase($area, $profile), '/');
        $relative = ltrim($path, '/');

        if ($relative === '') {
            return asset($base.'/');
        }

        return asset($base.'/'.$relative);
    }

    public function themeExists(string $area = 'frontend', ?ClientProfile $profile = null): bool
    {
        $normalizedArea = $this->normalizeArea($area);
        $resolved = $this->resolveArea($normalizedArea, $profile)['resolved'];

        if (! $this->registry->validateTheme($resolved, $normalizedArea)) {
            return false;
        }

        $assetBase = trim($this->registry->assetBase($resolved, $normalizedArea), '/');

        return ClientPublicWebrootPath::isDirectory($assetBase);
    }

    /**
     * @return array{
     *     client_slug: string|null,
     *     areas: array<string, array{
     *         selected: string|null,
     *         resolved: string,
     *         used_fallback: bool,
     *         registry_valid: bool,
     *         on_disk: bool,
     *         asset_base: string
     *     }>,
     *     warnings: list<string>
     * }
     */
    public function summary(?ClientProfile $profile = null): array
    {
        $profile = $this->resolveProfile($profile);
        $warnings = [];
        $areas = [];

        foreach (self::AREAS as $area) {
            $resolution = $this->resolveArea($area, $profile);
            $onDisk = $this->themeDirectoryExists($resolution['resolved'], $area);

            if ($resolution['used_fallback']) {
                $selected = $resolution['selected'] ?? '(empty)';
                $warnings[] = sprintf(
                    '%s theme "%s" is missing or inactive; using fallback "%s".',
                    ucfirst($area),
                    $selected,
                    $resolution['resolved'],
                );
            }

            if ($resolution['registry_valid'] && ! $onDisk) {
                $warnings[] = sprintf(
                    '%s theme "%s" is registered but %s is missing on disk.',
                    ucfirst($area),
                    $resolution['resolved'],
                    ClientPublicWebrootPath::path($resolution['asset_base']),
                );
            }

            $areas[$area] = [
                'selected' => $resolution['selected'],
                'resolved' => $resolution['resolved'],
                'used_fallback' => $resolution['used_fallback'],
                'registry_valid' => $resolution['registry_valid'],
                'on_disk' => $onDisk,
                'asset_base' => $resolution['asset_base'],
            ];
        }

        return [
            'client_slug' => $profile?->slug,
            'areas' => $areas,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{
     *     selected: string|null,
     *     resolved: string,
     *     used_fallback: bool,
     *     registry_valid: bool,
     *     asset_base: string
     * }
     */
    private function resolveArea(string $area, ?ClientProfile $profile = null): array
    {
        $profile = $this->resolveProfile($profile);
        $selected = $this->selectedTheme($area, $profile);
        $usedFallback = false;

        if ($selected === null || $selected === '') {
            $resolved = $this->registry->fallback($area);
            $usedFallback = true;
        } elseif ($this->registry->validateTheme($selected, $area)) {
            $resolved = $selected;
        } else {
            $resolved = $this->registry->fallback($area);
            $usedFallback = true;
        }

        if ($profile !== null && $resolved !== 'jetpakistan') {
            $adminTheme = trim((string) ($profile->active_admin_theme ?? ''));
            if ($adminTheme === 'jetpakistan' && $this->registry->validateTheme('jetpakistan', $area)) {
                $resolved = 'jetpakistan';
            }
        }

        return [
            'selected' => $selected !== '' ? $selected : null,
            'resolved' => $resolved,
            'used_fallback' => $usedFallback,
            'registry_valid' => $this->registry->validateTheme($resolved, $area),
            'asset_base' => $this->registry->assetBase($resolved, $area),
        ];
    }

    private function selectedTheme(string $area, ?ClientProfile $profile): ?string
    {
        $column = self::PROFILE_COLUMNS[$area] ?? null;
        if ($profile !== null && $column !== null) {
            $value = trim((string) ($profile->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $configKey = self::CONFIG_KEYS[$area] ?? null;
        if ($configKey !== null) {
            $value = trim((string) config('ota_client.'.$configKey, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolveProfile(?ClientProfile $profile): ?ClientProfile
    {
        if ($profile instanceof ClientProfile) {
            return $profile;
        }

        return $this->clientContext->get();
    }

    private function themeDirectoryExists(string $themeKey, string $area): bool
    {
        $assetBase = trim($this->registry->assetBase($themeKey, $area), '/');

        return $assetBase !== '' && ClientPublicWebrootPath::isDirectory($assetBase);
    }

    private function normalizeArea(string $area): string
    {
        $area = strtolower(trim($area));

        return in_array($area, self::AREAS, true) ? $area : 'frontend';
    }
}
