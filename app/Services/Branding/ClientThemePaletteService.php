<?php

namespace App\Services\Branding;

use App\Models\ClientProfile;
use App\Models\ClientThemePalette;
use App\Services\Client\ClientAssetResolver;
use App\Support\Client\ClientPublicWebrootPath;
use App\Services\Client\CurrentClientContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

/**
 * Generates and stores draft theme palettes from client logos; approval required before live use.
 */
final class ClientThemePaletteService
{
    public function __construct(
        private readonly LogoPaletteExtractor $extractor,
        private readonly CurrentClientContext $clientContext,
        private readonly ClientAssetResolver $assetResolver,
    ) {}

    public function generateForProfile(ClientProfile $profile, ?string $logoRelativePath = null): ClientThemePalette
    {
        $logoPath = $logoRelativePath ?? $this->defaultLogoRelativePath();
        $absolute = $this->resolveAbsoluteLogoPath($logoPath);
        $paletteData = $this->extractor->extractFromPath($absolute);

        return ClientThemePalette::query()->updateOrCreate(
            ['client_profile_id' => $profile->id],
            [
                'source_logo_path' => $logoPath,
                'primary' => $paletteData['primary'],
                'secondary' => $paletteData['secondary'],
                'accent' => $paletteData['accent'],
                'background' => $paletteData['background'],
                'surface' => $paletteData['surface'],
                'text' => $paletteData['text'],
                'muted' => $paletteData['muted'],
                'success' => $paletteData['success'],
                'warning' => $paletteData['warning'],
                'danger' => $paletteData['danger'],
                'palette_json' => $paletteData,
                'generated_at' => now(),
                'approved_at' => null,
                'approved_by' => null,
            ],
        );
    }

    public function approveDraft(ClientProfile $profile, int $userId): ?ClientThemePalette
    {
        $palette = ClientThemePalette::query()->where('client_profile_id', $profile->id)->first();
        if ($palette === null) {
            return null;
        }

        $palette->forceFill([
            'approved_at' => now(),
            'approved_by' => $userId,
        ])->save();

        $branding = $profile->branding;
        if ($branding !== null) {
            $branding->forceFill([
                'primary_color' => $palette->primary,
                'secondary_color' => $palette->secondary,
                'accent_color' => $palette->accent,
                'config' => array_merge(is_array($branding->config) ? $branding->config : [], [
                    'theme_palette' => $palette->palette_json,
                    'theme_palette_approved_at' => now()->toIso8601String(),
                ]),
            ])->save();
        }

        return $palette->fresh();
    }

    public function cssVariablesForProfile(?ClientProfile $profile = null): array
    {
        if (! Schema::hasTable('client_theme_palettes')) {
            return [];
        }

        $profile = $profile ?? $this->clientContext->get();
        if ($profile === null) {
            return [];
        }

        $palette = ClientThemePalette::query()
            ->where('client_profile_id', $profile->id)
            ->whereNotNull('approved_at')
            ->first();

        if ($palette === null) {
            return [];
        }

        return [
            '--brand' => $palette->primary,
            '--brand-bright' => $palette->secondary,
            '--gold' => $palette->accent,
            '--bg' => $palette->background,
            '--surface' => $palette->surface,
            '--text' => $palette->text,
            '--muted' => $palette->muted,
        ];
    }

    private function defaultLogoRelativePath(): string
    {
        $profile = $this->assetResolver->activeAssetProfile();

        return 'client-assets/'.$profile.'/logo/logo.svg';
    }

    private function resolveAbsoluteLogoPath(string $relative): string
    {
        $relative = ltrim(str_replace(['..', '\\'], ['', '/'], $relative), '/');
        $configured = ClientPublicWebrootPath::path($relative);
        if (is_file($configured)) {
            return $configured;
        }

        $laravelPublic = public_path($relative);
        if (is_file($laravelPublic)) {
            return $laravelPublic;
        }

        $fallbackRelative = 'client-assets/jetpk-assets/logo/logo.svg';

        return is_file(ClientPublicWebrootPath::path($fallbackRelative))
            ? ClientPublicWebrootPath::path($fallbackRelative)
            : public_path($fallbackRelative);
    }
}
