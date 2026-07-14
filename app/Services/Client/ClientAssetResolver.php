<?php

namespace App\Services\Client;

use App\Support\Client\ClientProfileConfigReader;

/**
 * Resolves client theme and public asset paths/URLs at runtime (MC-5A).
 *
 * Uses preview DB profile when CurrentClientContext is in preview mode; otherwise
 * falls back to config('ota_client') and ClientProfileConfigReader branding.
 */
final class ClientAssetResolver
{
    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly ClientProfileConfigReader $configReader,
    ) {}

    public function activeTheme(): string
    {
        if ($this->usesPreviewProfile()) {
            $theme = trim((string) ($this->clientContext->theme() ?? ''));

            return $theme !== '' ? $theme : 'v1-classic';
        }

        $theme = trim((string) config('ota_client.theme', 'v1-classic'));

        return $theme !== '' ? $theme : 'v1-classic';
    }

    public function activeAssetProfile(): string
    {
        if ($this->usesPreviewProfile()) {
            return trim((string) ($this->clientContext->assetProfile() ?? ''));
        }

        $profile = trim((string) config('ota_client.asset_profile', ''));
        if ($profile !== '') {
            return $profile;
        }

        return trim((string) config('ota_client.slug', ''));
    }

    public function frontendThemePath(): string
    {
        return 'themes/frontend/'.$this->activeTheme().'/';
    }

    public function frontendThemeUrl(): string
    {
        return asset($this->frontendThemePath());
    }

    public function clientAssetPath(string $path): string
    {
        $relative = ltrim($path, '/');
        $profile = $this->activeAssetProfile();

        if ($profile === '') {
            return $relative;
        }

        if ($relative === '') {
            return 'client-assets/'.$profile;
        }

        return 'client-assets/'.$profile.'/'.$relative;
    }

    public function clientAssetUrl(string $path): string
    {
        return asset($this->clientAssetPath($path));
    }

    public function logoUrl(): ?string
    {
        $logoPath = trim((string) ($this->brandingField('logo_path') ?? ''));

        return $logoPath !== '' ? $this->clientAssetUrl($logoPath) : null;
    }

    public function faviconUrl(): ?string
    {
        $faviconPath = trim((string) ($this->brandingField('favicon_path') ?? ''));

        return $faviconPath !== '' ? $this->clientAssetUrl($faviconPath) : null;
    }

    public function bannerUrl(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return $this->clientAssetUrl('banners/'.$name);
    }

    public function heroImageUrl(): ?string
    {
        if ($this->usesPreviewProfile()) {
            $branding = $this->clientContext->branding();
            $config = is_array($branding?->config) ? $branding->config : [];
            $hero = trim((string) ($config['hero_image_path'] ?? ''));
            if ($hero !== '') {
                return $this->clientAssetUrl($hero);
            }
        }

        foreach (['hero.jpg', 'hero.png', 'hero.webp', 'hero.jpeg'] as $filename) {
            $relative = $this->clientAssetPath('banners/'.$filename);
            if (is_file(public_path($relative))) {
                return asset($relative);
            }
        }

        return null;
    }

    private function usesPreviewProfile(): bool
    {
        return $this->clientContext->isPreview() && $this->clientContext->get() !== null;
    }

    private function brandingField(string $field): ?string
    {
        if ($this->usesPreviewProfile()) {
            $branding = $this->clientContext->branding();
            $value = $branding?->{$field};

            return is_string($value) ? $value : null;
        }

        $branding = $this->configReader->brandingFromConfig();
        $value = $branding[$field] ?? null;

        return is_string($value) ? $value : null;
    }
}
