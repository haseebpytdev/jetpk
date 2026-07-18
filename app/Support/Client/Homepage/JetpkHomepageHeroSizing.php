<?php

namespace App\Support\Client\Homepage;

/**
 * Normalizes and resolves JetPK homepage hero typography and search UI scale CMS values.
 *
 * Contract:
 * - Hero text fields store integer percentages (75–140); 100 = design baseline.
 * - Search UI scale stores integer percentages (80–115); 100 = compact baseline (0.90× legacy tokens).
 */
final class JetpkHomepageHeroSizing
{
    public const HERO_TEXT_MIN = 75;

    public const HERO_TEXT_MAX = 140;

    public const HERO_TEXT_DEFAULT = 100;

    public const SEARCH_UI_MIN = 80;

    public const SEARCH_UI_MAX = 115;

    public const SEARCH_UI_DEFAULT = 100;

    /** Compact search baseline as a fraction of legacy full-size tokens. */
    public const SEARCH_UI_COMPACT_BASELINE = 0.90;

    /**
     * @return list<string>
     */
    public static function heroTextFieldKeys(): array
    {
        return [
            'eyebrow_size',
            'headline_size',
            'highlight_size',
            'subtitle_size',
        ];
    }

    /**
     * @param  array<string, mixed>  $hero
     * @return array<string, mixed>
     */
    public static function normalizeHeroSection(array $hero): array
    {
        foreach (self::heroTextFieldKeys() as $key) {
            if (array_key_exists($key, $hero)) {
                $hero[$key] = (string) self::normalizeHeroTextPercent($hero[$key]);
            }
        }

        if (array_key_exists('search_ui_scale', $hero)) {
            $hero['search_ui_scale'] = (string) self::normalizeSearchUiPercent($hero['search_ui_scale']);
        }

        return $hero;
    }

    public static function normalizeHeroTextPercent(mixed $value): int
    {
        if ($value === null || $value === '') {
            return self::HERO_TEXT_DEFAULT;
        }

        if (! is_numeric($value)) {
            return self::HERO_TEXT_DEFAULT;
        }

        return (int) max(self::HERO_TEXT_MIN, min(self::HERO_TEXT_MAX, (int) round((float) $value)));
    }

    public static function normalizeSearchUiPercent(mixed $value): int
    {
        if ($value === null || $value === '') {
            return self::SEARCH_UI_DEFAULT;
        }

        if (! is_numeric($value)) {
            return self::SEARCH_UI_DEFAULT;
        }

        return (int) max(self::SEARCH_UI_MIN, min(self::SEARCH_UI_MAX, (int) round((float) $value)));
    }

    public static function heroTextScaleDecimal(mixed $percent): float
    {
        return self::normalizeHeroTextPercent($percent) / 100;
    }

    public static function searchUiScaleDecimal(mixed $percent): float
    {
        return round(self::SEARCH_UI_COMPACT_BASELINE * (self::normalizeSearchUiPercent($percent) / 100), 4);
    }

    /**
     * @param  array<string, mixed>  $hero
     * @return array<string, string>
     */
    public static function cssVariablesFromHero(array $hero): array
    {
        return [
            '--jp-hero-eyebrow-scale' => (string) self::heroTextScaleDecimal($hero['eyebrow_size'] ?? self::HERO_TEXT_DEFAULT),
            '--jp-hero-headline-scale' => (string) self::heroTextScaleDecimal($hero['headline_size'] ?? self::HERO_TEXT_DEFAULT),
            '--jp-hero-highlight-scale' => (string) self::heroTextScaleDecimal($hero['highlight_size'] ?? self::HERO_TEXT_DEFAULT),
            '--jp-hero-subtitle-scale' => (string) self::heroTextScaleDecimal($hero['subtitle_size'] ?? self::HERO_TEXT_DEFAULT),
            '--jp-search-ui-scale' => (string) self::searchUiScaleDecimal($hero['search_ui_scale'] ?? self::SEARCH_UI_DEFAULT),
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function defaultHeroSizingFields(array $defaults = []): array
    {
        return array_merge($defaults, [
            'eyebrow_size' => (string) self::HERO_TEXT_DEFAULT,
            'headline_size' => (string) self::HERO_TEXT_DEFAULT,
            'highlight_size' => (string) self::HERO_TEXT_DEFAULT,
            'subtitle_size' => (string) self::HERO_TEXT_DEFAULT,
            'search_ui_scale' => (string) self::SEARCH_UI_DEFAULT,
        ]);
    }
}
