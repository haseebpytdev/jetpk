<?php

namespace App\Services\Client;

use App\Support\Client\ClientPublicWebrootPath;

/**
 * Resolves client theme ids and public theme URLs at runtime (MC-6A, MC-8A).
 *
 * Delegates theme resolution to RuntimeThemeManager (registry-validated).
 * Asset profile resolution unchanged from MC-6A.
 */
final class ClientThemeResolver
{
    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly RuntimeThemeManager $runtimeThemeManager,
        private readonly ClientThemeRegistry $themeRegistry,
    ) {}

    public function frontendTheme(): string
    {
        return $this->runtimeThemeManager->frontend();
    }

    public function adminTheme(): string
    {
        return $this->runtimeThemeManager->admin();
    }

    public function staffTheme(): string
    {
        return $this->runtimeThemeManager->staff();
    }

    public function assetProfile(): string
    {
        $profile = $this->clientContext->get();
        if ($profile !== null) {
            $value = trim((string) ($profile->asset_profile ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $configProfile = trim((string) config('ota_client.asset_profile', ''));
        if ($configProfile !== '') {
            return $configProfile;
        }

        return trim((string) config('ota_client.slug', ''));
    }

    public function frontendThemeUrl(): string
    {
        return $this->themeUrl('frontend', $this->frontendTheme());
    }

    public function adminThemeUrl(): string
    {
        return $this->themeUrl('admin', $this->adminTheme());
    }

    public function staffThemeUrl(): string
    {
        return $this->themeUrl('staff', $this->staffTheme());
    }

    public function themeExists(string $theme, string $area = 'frontend'): bool
    {
        $theme = trim($theme);
        if ($theme === '') {
            return false;
        }

        $area = $this->normalizeArea($area);

        if (! $this->themeRegistry->validateTheme($theme, $area)) {
            return false;
        }

        $assetBase = trim($this->themeRegistry->assetBase($theme, $area), '/');

        return ClientPublicWebrootPath::isDirectory($assetBase);
    }

    /**
     * @return array{
     *     frontend_theme: string,
     *     admin_theme: string,
     *     staff_theme: string,
     *     asset_profile: string,
     *     frontend_theme_url: string,
     *     admin_theme_url: string,
     *     staff_theme_url: string,
     *     frontend_theme_exists: bool,
     *     admin_theme_exists: bool,
     *     staff_theme_exists: bool
     * }
     */
    public function all(): array
    {
        $frontendTheme = $this->frontendTheme();
        $adminTheme = $this->adminTheme();
        $staffTheme = $this->staffTheme();

        return [
            'frontend_theme' => $frontendTheme,
            'admin_theme' => $adminTheme,
            'staff_theme' => $staffTheme,
            'asset_profile' => $this->assetProfile(),
            'frontend_theme_url' => $this->frontendThemeUrl(),
            'admin_theme_url' => $this->adminThemeUrl(),
            'staff_theme_url' => $this->staffThemeUrl(),
            'frontend_theme_exists' => $this->themeExists($frontendTheme, 'frontend'),
            'admin_theme_exists' => $this->themeExists($adminTheme, 'admin'),
            'staff_theme_exists' => $this->themeExists($staffTheme, 'staff'),
        ];
    }

    private function themeUrl(string $area, string $theme): string
    {
        $assetBase = rtrim($this->themeRegistry->assetBase($theme, $this->normalizeArea($area)), '/');

        return asset($assetBase.'/');
    }

    private function normalizeArea(string $area): string
    {
        $area = strtolower(trim($area));

        return in_array($area, ['frontend', 'admin', 'staff'], true) ? $area : 'frontend';
    }
}
