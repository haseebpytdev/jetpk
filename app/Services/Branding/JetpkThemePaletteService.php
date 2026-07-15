<?php

namespace App\Services\Branding;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Support\Branding\JetpkBrandPaletteCssResolver;
use App\Support\Branding\JetpkCompanyBrandingResolver;
use App\Support\Branding\JetpkThemePaletteValidator;
use Illuminate\Validation\ValidationException;

/**
 * JetPakistan day/night theme palette persistence in agency_settings.meta.
 */
final class JetpkThemePaletteService
{
    public const META_KEY = 'jetpk_theme_palette';

    public function __construct(
        private readonly JetpkThemePaletteValidator $validator,
        private readonly JetpkCompanyBrandingResolver $brandingResolver,
    ) {}

    public function isJetpkScoped(): bool
    {
        return $this->brandingResolver->isJetpkDeployment();
    }

    /**
     * @return array{day: array<string, string>, night: array<string, string>}
     */
    public function defaults(): array
    {
        return [
            'day' => $this->normalizedThemeDefaults('day'),
            'night' => $this->normalizedThemeDefaults('night'),
        ];
    }

    /**
     * @return array{day: array<string, string>, night: array<string, string>}
     */
    public function palettesForAgency(Agency $agency, bool $migrateLegacy = true): array
    {
        $settings = AgencySetting::query()->where('agency_id', $agency->id)->first();
        $stored = $this->extractStoredPalettes($settings);

        if ($migrateLegacy) {
            $stored = $this->migrateLegacyValues($settings, $stored);
        }

        return [
            'day' => $this->normalizeDayPrimaryIfLegacy($agency, $this->mergeWithDefaults('day', $stored['day'] ?? [])),
            'night' => $this->normalizeObsoletePrimaryIfLegacy($this->mergeWithDefaults('night', $stored['night'] ?? []), 'night'),
        ];
    }

    public function isLegacyDayPrimary(?string $hex): bool
    {
        $hex = $this->validator->normalizeHex($hex);
        if ($hex === null) {
            return false;
        }

        $legacy = array_map(
            'strtoupper',
            array_merge(
                config('jetpk-theme-palette.legacy_orange_primary', []),
                config('jetpk-theme-palette.legacy_system_primary', []),
                config('jetpk-theme-palette.legacy_obsolete_day_primary', []),
                $this->brandSchemePrimaryColors(),
            ),
        );

        return in_array($hex, $legacy, true);
    }

    public function isDayPrimaryAdminCustomized(?AgencySetting $settings): bool
    {
        if ($settings === null) {
            return false;
        }

        $meta = is_array($settings->meta) ? $settings->meta : [];
        $key = (string) config('jetpk-theme-palette.meta_day_customized_key', 'jetpk_theme_palette_day_customized');

        return filter_var($meta[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return array{action: string, source: string, current: string, target: string, customized: bool}
     */
    public function dayPrimaryNormalizationPlan(Agency $agency): array
    {
        $settings = AgencySetting::query()->where('agency_id', $agency->id)->first();
        $stored = $this->extractStoredPalettes($settings);
        $rawPrimary = $this->validator->normalizeHex($stored['day']['primary'] ?? null);
        $effectivePrimary = $this->normalizeDayPrimaryIfLegacy($agency, $this->mergeWithDefaults('day', $stored['day'] ?? []))['primary'];
        $current = $rawPrimary ?? $effectivePrimary;
        $target = strtoupper((string) config('jetpk-theme-palette.defaults.day.primary', '#63B32E'));
        $customized = $this->isDayPrimaryAdminCustomized($settings);

        $source = 'stored_meta';
        if ($rawPrimary === null && $settings !== null && is_string($settings->primary_color)) {
            $legacyPrimary = strtoupper(trim($settings->primary_color));
            if ($legacyPrimary === $effectivePrimary) {
                $source = 'agency_settings.primary_color';
            }
        }

        if ($customized) {
            return [
                'action' => 'preserve',
                'source' => $this->isLegacyDayPrimary($current) ? 'admin_customized_legacy' : 'admin_customized',
                'current' => $current,
                'target' => $this->isLegacyDayPrimary($current) ? $target : $current,
                'customized' => true,
            ];
        }

        if ($this->isLegacyDayPrimary($current)) {
            return [
                'action' => 'normalize',
                'source' => $source,
                'current' => $current,
                'target' => $target,
                'customized' => false,
            ];
        }

        if ($current === $target) {
            return [
                'action' => 'noop',
                'source' => $source,
                'current' => $current,
                'target' => $target,
                'customized' => $customized,
            ];
        }

        return [
            'action' => 'preserve',
            'source' => 'non_legacy_custom',
            'current' => $current,
            'target' => $current,
            'customized' => $customized,
        ];
    }

    /**
     * @return array{action: string, source: string, current: string, target: string, customized: bool}
     */
    public function normalizeDayPrimaryDefault(Agency $agency, bool $dryRun = true): array
    {
        if (! $this->isJetpkScoped()) {
            throw ValidationException::withMessages([
                'palette' => ['Theme palette normalization is only available for JetPakistan.'],
            ]);
        }

        $plan = $this->dayPrimaryNormalizationPlan($agency);
        if ($dryRun || $plan['action'] !== 'normalize') {
            return $plan;
        }

        $settings = AgencySetting::query()->firstOrCreate(['agency_id' => $agency->id]);
        $palettes = $this->palettesForAgency($agency, false);
        $palettes['day']['primary'] = $plan['target'];

        $meta = is_array($settings->meta) ? $settings->meta : [];
        $meta[self::META_KEY] = [
            'day' => $palettes['day'],
            'night' => $palettes['night'],
        ];
        $meta[(string) config('jetpk-theme-palette.meta_day_customized_key', 'jetpk_theme_palette_day_customized')] = false;
        $settings->meta = $meta;
        $settings->primary_color = $plan['target'];
        $settings->secondary_color = $this->derivedValue('day', 'primary_hover', $plan['target']);
        $settings->save();

        return $plan;
    }

    /**
     * @param  array<string, string>  $dayPalette
     * @param  array<string, string>  $nightPalette
     */
    public function savePalettes(Agency $agency, array $dayPalette, array $nightPalette, ?string $saveScope = null, bool $markDayCustomized = true): AgencySetting
    {
        if (! $this->isJetpkScoped()) {
            throw ValidationException::withMessages([
                'palette' => ['Theme palette settings are only available for JetPakistan.'],
            ]);
        }

        $day = $this->normalizeIncomingPalette('day', $dayPalette);
        $night = $this->normalizeIncomingPalette('night', $nightPalette);

        if ($saveScope === 'day') {
            $night = $this->palettesForAgency($agency, false)['night'];
        } elseif ($saveScope === 'night') {
            $day = $this->palettesForAgency($agency, false)['day'];
        }

        $dayErrors = $this->validator->validatePalette('day', $day);
        $nightErrors = $this->validator->validatePalette('night', $night);
        if ($dayErrors !== [] || $nightErrors !== []) {
            throw ValidationException::withMessages([
                'day' => $dayErrors,
                'night' => $nightErrors,
            ]);
        }

        $settings = AgencySetting::query()->firstOrCreate(['agency_id' => $agency->id]);
        $meta = is_array($settings->meta) ? $settings->meta : [];
        $meta[self::META_KEY] = ['day' => $day, 'night' => $night];
        $customizedKey = (string) config('jetpk-theme-palette.meta_day_customized_key', 'jetpk_theme_palette_day_customized');
        if ($saveScope === null || $saveScope === 'both' || $saveScope === 'day') {
            $meta[$customizedKey] = $markDayCustomized;
        }
        $settings->meta = $meta;

        $settings->primary_color = $day['primary'];
        $settings->secondary_color = $this->derivedValue('day', 'primary_hover', $day['primary']);
        $settings->accent_color = $day['accent'];
        $settings->save();

        return $settings->fresh();
    }

    /**
     * @return array<string, string>
     */
    public function resetTheme(Agency $agency, string $theme): array
    {
        $palettes = $this->palettesForAgency($agency, false);
        $palettes[$theme] = $this->normalizedThemeDefaults($theme);

        $this->savePalettes(
            $agency,
            $palettes['day'],
            $palettes['night'],
            $theme,
            markDayCustomized: false,
        );

        return $palettes[$theme];
    }

    /**
     * @param  array<string, string>  $palette
     * @return array<string, string>
     */
    public function cssVariablesForTheme(string $theme, array $palette): array
    {
        return app(JetpkBrandPaletteCssResolver::class)->variablesFromThemePalette($theme, $palette);
    }

    /**
     * @param  array{day: array<string, string>, night: array<string, string>}  $palettes
     * @return array{night: array<string, string>, day: array<string, string>}
     */
    public function cssVariableBlocks(array $palettes): array
    {
        return [
            'night' => $this->cssVariablesForTheme('night', $palettes['night']),
            'day' => $this->cssVariablesForTheme('day', $palettes['day']),
        ];
    }

    /**
     * @param  array<string, string>  $incoming
     * @return array<string, string>
     */
    private function normalizeIncomingPalette(string $theme, array $incoming): array
    {
        $normalized = [];
        foreach (config('jetpk-theme-palette.keys', []) as $key) {
            $hex = $this->validator->normalizeHex($incoming[$key] ?? null);
            if ($hex === null) {
                throw ValidationException::withMessages([
                    $theme.'.'.$key => ['Invalid hex color.'],
                ]);
            }
            $normalized[$key] = $hex;
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function mergeWithDefaults(string $theme, array $overrides): array
    {
        $merged = $this->normalizedThemeDefaults($theme);
        foreach ($overrides as $key => $value) {
            $hex = $this->validator->normalizeHex($value);
            if ($hex !== null && in_array($key, config('jetpk-theme-palette.keys', []), true)) {
                $merged[$key] = $hex;
            }
        }

        return $merged;
    }

    /**
     * @return array<string, string>
     */
    private function normalizedThemeDefaults(string $theme): array
    {
        $defaults = config('jetpk-theme-palette.defaults.'.$theme, []);
        $normalized = [];
        foreach (config('jetpk-theme-palette.keys', []) as $key) {
            $normalized[$key] = strtoupper((string) ($defaults[$key] ?? '#000000'));
        }

        return $normalized;
    }

    /**
     * @return array{day?: array<string, string>, night?: array<string, string>}
     */
    private function extractStoredPalettes(?AgencySetting $settings): array
    {
        if ($settings === null) {
            return [];
        }

        $meta = is_array($settings->meta) ? $settings->meta : [];
        $stored = $meta[self::META_KEY] ?? null;

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param  array{day?: array<string, string>, night?: array<string, string>}  $stored
     * @return array{day?: array<string, string>, night?: array<string, string>}
     */
    private function migrateLegacyValues(?AgencySetting $settings, array $stored): array
    {
        if ($settings === null || ! $this->isJetpkScoped()) {
            return $stored;
        }

        $legacyOranges = array_map('strtoupper', config('jetpk-theme-palette.legacy_orange_primary', []));
        $needsPersist = false;

        if (($stored['day'] ?? []) === [] && is_string($settings->primary_color)) {
            $primary = strtoupper(trim($settings->primary_color));
            if ($this->isLegacyDayPrimary($primary)) {
                $stored['day']['primary'] = config('jetpk-theme-palette.legacy_migration_target.day');
                $needsPersist = true;
            } elseif ($this->validator->normalizeHex($primary) !== null && ! $this->isDayPrimaryAdminCustomized($settings)) {
                $stored['day']['primary'] = $primary;
            }
        } elseif (isset($stored['day']['primary']) && $this->isLegacyDayPrimary($stored['day']['primary']) && ! $this->isDayPrimaryAdminCustomized($settings)) {
            $stored['day']['primary'] = config('jetpk-theme-palette.legacy_migration_target.day');
            $needsPersist = true;
        }

        if (($stored['day']['accent'] ?? null) === null && is_string($settings->accent_color)) {
            $accent = $this->validator->normalizeHex($settings->accent_color);
            if ($accent !== null && ! in_array($accent, $legacyOranges, true)) {
                $stored['day']['accent'] = $accent;
            }
        }

        if (($stored['night'] ?? []) === []) {
            $stored['night'] = [];
            if (is_string($settings->primary_color)) {
                $primary = strtoupper(trim($settings->primary_color));
                if (in_array($primary, $legacyOranges, true)) {
                    $stored['night']['primary'] = config('jetpk-theme-palette.legacy_migration_target.night');
                    $needsPersist = true;
                }
            }
        } elseif (isset($stored['night']['primary']) && $this->isObsoleteStoredPrimary($stored['night']['primary'])) {
            $stored['night']['primary'] = config('jetpk-theme-palette.legacy_migration_target.night');
            $needsPersist = true;
        }

        if ($needsPersist && ($stored['day'] !== [] || $stored['night'] !== [])) {
            $meta = is_array($settings->meta) ? $settings->meta : [];
            $meta[self::META_KEY] = [
                'day' => $this->mergeWithDefaults('day', $stored['day'] ?? []),
                'night' => $this->mergeWithDefaults('night', $stored['night'] ?? []),
            ];
            $settings->primary_color = $meta[self::META_KEY]['day']['primary'];
            $settings->secondary_color = $this->derivedValue('day', 'primary_hover', $meta[self::META_KEY]['day']['primary']);
            $settings->forceFill(['meta' => $meta])->save();
        }

        return $stored;
    }

    /**
     * @param  array<string, string>  $day
     * @return array<string, string>
     */
    private function normalizeDayPrimaryIfLegacy(Agency $agency, array $day): array
    {
        $settings = AgencySetting::query()->where('agency_id', $agency->id)->first();
        if ($this->isDayPrimaryAdminCustomized($settings)) {
            return $day;
        }

        if ($this->isLegacyDayPrimary($day['primary'] ?? null)) {
            $day['primary'] = strtoupper((string) config('jetpk-theme-palette.legacy_migration_target.day', config('jetpk-theme-palette.defaults.day.primary', '#63B32E')));
        }

        return $day;
    }

    /**
     * @param  array<string, string>  $palette
     * @return array<string, string>
     */
    private function normalizeObsoletePrimaryIfLegacy(array $palette, string $theme): array
    {
        if ($this->isObsoleteStoredPrimary($palette['primary'] ?? null)) {
            $palette['primary'] = strtoupper((string) config('jetpk-theme-palette.legacy_migration_target.'.$theme, config('jetpk-theme-palette.defaults.'.$theme.'.primary', '#63B32E')));
        }

        return $palette;
    }

    public function isObsoleteStoredPrimary(?string $hex): bool
    {
        $hex = $this->validator->normalizeHex($hex);
        if ($hex === null) {
            return false;
        }

        $obsolete = array_map('strtoupper', config('jetpk-theme-palette.legacy_obsolete_day_primary', []));

        return in_array($hex, $obsolete, true);
    }

    /**
     * @return list<string>
     */
    private function brandSchemePrimaryColors(): array
    {
        $colors = [];
        foreach (config('ota-brand-schemes.presets', []) as $preset) {
            if (is_array($preset) && is_string($preset['primary'] ?? null)) {
                $colors[] = strtoupper($preset['primary']);
            }
        }

        return $colors;
    }

    private function derivedValue(string $theme, string $key, string $fallback): string
    {
        return strtoupper((string) (config('jetpk-theme-palette.derived.'.$theme.'.'.$key) ?? $fallback));
    }
}
