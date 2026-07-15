<?php

namespace App\Support\Branding;

/**
 * Canonical JetPK runtime palette → CSS custom properties for public and dashboard shells.
 *
 * Derives theme-aware soft surfaces from saved primary/secondary/accent without replacing them.
 */
final class JetpkBrandPaletteCssResolver
{
    /**
     * @param  array<string, string>  $palette
     * @return array<string, string>
     */
    public function variablesFromThemePalette(string $theme, array $palette): array
    {
        $primary = $this->normalizeHex($palette['primary'] ?? null) ?? '#63B32E';
        $accent = $this->normalizeHex($palette['accent'] ?? null) ?? '#19A7A6';
        $success = $this->normalizeHex($palette['success'] ?? null) ?? '#63B32E';
        $pageBg = $this->normalizeHex($palette['page_bg'] ?? null) ?? '#EDF3F7';
        $surface = $this->normalizeHex($palette['surface'] ?? null) ?? '#FFFFFF';
        $text = $this->normalizeHex($palette['text'] ?? null) ?? '#0B1D2A';
        $textMuted = $this->normalizeHex($palette['text_muted'] ?? null) ?? '#62788A';
        $border = $this->normalizeHex($palette['border'] ?? null) ?? '#D7E2E9';

        $derived = config('jetpk-theme-palette.derived.'.$theme, []);
        $primaryHover = $this->normalizeHex($derived['primary_hover'] ?? null) ?? $this->adjustBrightness($primary, 0.92);
        $primaryActive = $this->normalizeHex($derived['primary_active'] ?? null) ?? $this->adjustBrightness($primary, 0.82);
        $primarySoft = $this->normalizeHex($derived['primary_soft'] ?? null) ?? $this->mixWithWhite($primary, 0.88);
        $primaryBorder = $this->normalizeHex($derived['primary_border'] ?? null) ?? $this->mixWithWhite($primary, 0.72);
        $accentHover = $this->normalizeHex($derived['accent_hover'] ?? null) ?? $this->adjustBrightness($accent, 0.9);
        $accentSoft = $this->normalizeHex($derived['accent_soft'] ?? null) ?? $this->mixWithWhite($accent, 0.9);
        $successHover = $this->normalizeHex($derived['success_hover'] ?? null) ?? $this->adjustBrightness($success, 0.9);
        $surfaceMuted = $this->normalizeHex($derived['surface_muted'] ?? null) ?? $this->mixWithWhite($surface, 0.04);

        $primaryContrast = $this->contrastInk($primary);
        $semantic = config('jetpk-theme-palette.semantic', []);
        $warning = $this->normalizeHex($semantic['warning'] ?? null) ?? '#F59E0B';
        $danger = $this->normalizeHex($semantic['danger'] ?? null) ?? '#DC2626';
        $info = $this->normalizeHex($semantic['info'] ?? null) ?? '#0EA5E9';

        $gradientStart = $this->adjustBrightness($primary, 1.04);
        $gradientEnd = $this->adjustBrightness($primary, 0.92);
        $gradientHoverStart = $this->adjustBrightness($primaryHover, 1.02);
        $gradientHoverEnd = $this->adjustBrightness($primaryHover, 0.9);

        $disabledBg = $this->mixWithWhite($surfaceMuted, 0.08);
        $disabledText = $textMuted;

        return [
            '--jp-primary' => $primary,
            '--jp-primary-hover' => $primaryHover,
            '--jp-primary-active' => $primaryActive,
            '--jp-primary-soft' => $primarySoft,
            '--jp-primary-border' => $primaryBorder,
            '--jp-accent' => $accent,
            '--jp-accent-hover' => $accentHover,
            '--jp-accent-soft' => $accentSoft,
            '--jp-success' => $success,
            '--jp-success-hover' => $successHover,
            '--jp-warning' => $warning,
            '--jp-danger' => $danger,
            '--jp-info' => $info,
            '--jp-page-bg' => $pageBg,
            '--jp-surface' => $surface,
            '--jp-surface-muted' => $surfaceMuted,
            '--jp-text' => $text,
            '--jp-text-muted' => $textMuted,
            '--jp-border' => $border,
            '--jp-focus-ring' => '0 0 0 3px '.$this->hexWithAlpha($accent, 0.35),
            '--jp-disabled-bg' => $disabledBg,
            '--jp-disabled-text' => $disabledText,
            '--jp-color-primary' => $primary,
            '--jp-color-primary-hover' => $primaryHover,
            '--jp-color-primary-active' => $primaryActive,
            '--jp-color-primary-contrast' => $primaryContrast,
            '--jp-color-primary-soft' => $primarySoft,
            '--jp-color-primary-soft-border' => $primaryBorder,
            '--jp-color-accent' => $accent,
            '--jp-color-accent-hover' => $accentHover,
            '--jp-color-accent-soft' => $accentSoft,
            '--jp-color-focus' => $accent,
            '--jp-color-surface' => $surface,
            '--jp-color-surface-subtle' => $surfaceMuted,
            '--jp-color-text' => $text,
            '--jp-color-text-muted' => $textMuted,
            '--jp-color-border' => $border,
            '--jp-gradient-primary' => "linear-gradient(135deg, {$gradientStart}, {$gradientEnd})",
            '--jp-gradient-primary-hover' => "linear-gradient(135deg, {$gradientHoverStart}, {$gradientHoverEnd})",
            '--jp-button-shadow' => '0 1px 2px '.$this->hexWithAlpha($primary, 0.12).', 0 1px 3px '.$this->hexWithAlpha($primary, 0.08),
            '--jp-button-shadow-hover' => '0 4px 12px '.$this->hexWithAlpha($primary, 0.18),
            '--jp-focus-ring-shadow' => '0 0 0 3px '.$this->hexWithAlpha($accent, 0.35),
            '--bg' => $pageBg,
            '--bg-2' => $surfaceMuted,
            '--surface' => $surface,
            '--surface-2' => $surfaceMuted,
            '--card' => $surface,
            '--line' => $border,
            '--line-soft' => $this->mixWithWhite($border, 0.35),
            '--line-strong' => $this->adjustBrightness($border, 0.88),
            '--text' => $text,
            '--text-2' => $textMuted,
            '--muted' => $textMuted,
            '--brand' => $primary,
            '--brand-bright' => $primaryHover,
            '--brand-ink' => $primaryContrast,
            '--brand-soft' => $this->hexWithAlpha($primary, 0.14),
            '--gold' => $accent,
            '--gold-deep' => $accentHover,
            '--gold-soft' => $this->hexWithAlpha($accent, 0.12),
            '--shadow-brand' => '0 8px 24px -12px '.$this->hexWithAlpha($primary, 0.35),
            '--ring' => '0 0 0 3px '.$this->hexWithAlpha($accent, 0.35),
            '--notch' => $surface,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function variablesFromHex(?string $primary, ?string $secondary, ?string $accent): array
    {
        $primary = $this->normalizeHex($primary) ?? '#16A34A';
        $secondary = $this->normalizeHex($secondary) ?? '#15803D';
        $accent = $this->normalizeHex($accent) ?? '#0D9488';

        $primaryHover = $this->adjustBrightness($primary, 0.92);
        $primaryActive = $this->adjustBrightness($primary, 0.82);
        $primaryContrast = $this->contrastInk($primary);

        $primarySoft = $this->mixWithWhite($primary, 0.88);
        $primarySoftHover = $this->mixWithWhite($primary, 0.82);
        $primarySoftBorder = $this->mixWithWhite($primary, 0.72);

        $secondarySoft = $this->mixWithWhite($secondary, 0.9);
        $secondarySoftHover = $this->mixWithWhite($secondary, 0.84);
        $secondarySoftBorder = $this->mixWithWhite($secondary, 0.7);

        $accentSoft = $this->mixWithWhite($accent, 0.9);
        $accentSoftHover = $this->mixWithWhite($accent, 0.84);
        $accentSoftBorder = $this->mixWithWhite($accent, 0.7);

        $gradientStart = $this->adjustBrightness($primary, 1.08);
        $gradientEnd = $this->adjustBrightness($primary, 0.88);
        $gradientHoverStart = $this->adjustBrightness($primary, 1.04);
        $gradientHoverEnd = $this->adjustBrightness($primary, 0.82);

        $secondaryGradientStart = $this->adjustBrightness($secondary, 1.06);
        $secondaryGradientEnd = $this->adjustBrightness($secondary, 0.9);
        $accentGradientStart = $this->adjustBrightness($accent, 1.06);
        $accentGradientEnd = $this->adjustBrightness($accent, 0.9);

        $focus = $accent;
        $surface = '#0F1E2C';
        $surfaceSubtle = '#15293A';
        $text = '#EAF3F8';
        $textMuted = '#7790A2';
        $border = '#21384C';

        return [
            '--jp-color-primary' => $primary,
            '--jp-color-primary-hover' => $primaryHover,
            '--jp-color-primary-active' => $primaryActive,
            '--jp-color-primary-contrast' => $primaryContrast,
            '--jp-color-primary-soft' => $primarySoft,
            '--jp-color-primary-soft-hover' => $primarySoftHover,
            '--jp-color-primary-soft-border' => $primarySoftBorder,
            '--jp-color-secondary' => $secondary,
            '--jp-color-secondary-hover' => $this->adjustBrightness($secondary, 0.94),
            '--jp-color-secondary-contrast' => $this->contrastInk($secondary),
            '--jp-color-secondary-soft' => $secondarySoft,
            '--jp-color-secondary-soft-hover' => $secondarySoftHover,
            '--jp-color-secondary-soft-border' => $secondarySoftBorder,
            '--jp-color-accent' => $accent,
            '--jp-color-accent-hover' => $this->adjustBrightness($accent, 0.9),
            '--jp-color-accent-contrast' => $this->contrastInk($accent),
            '--jp-color-accent-soft' => $accentSoft,
            '--jp-color-accent-soft-hover' => $accentSoftHover,
            '--jp-color-accent-soft-border' => $accentSoftBorder,
            '--jp-gradient-primary' => "linear-gradient(135deg, {$gradientStart}, {$gradientEnd})",
            '--jp-gradient-primary-hover' => "linear-gradient(135deg, {$gradientHoverStart}, {$gradientHoverEnd})",
            '--jp-gradient-secondary' => "linear-gradient(135deg, {$secondaryGradientStart}, {$secondaryGradientEnd})",
            '--jp-gradient-accent' => "linear-gradient(135deg, {$accentGradientStart}, {$accentGradientEnd})",
            '--jp-button-shadow' => '0 1px 2px '.$this->hexWithAlpha($primary, 0.12).', 0 1px 3px '.$this->hexWithAlpha($primary, 0.08),
            '--jp-button-shadow-hover' => '0 4px 12px '.$this->hexWithAlpha($primary, 0.18),
            '--jp-focus-ring' => '0 0 0 3px '.$this->hexWithAlpha($accent, 0.35),
            '--jp-color-focus' => $focus,
            '--jp-color-surface' => $surface,
            '--jp-color-surface-subtle' => $surfaceSubtle,
            '--jp-color-text' => $text,
            '--jp-color-text-muted' => $textMuted,
            '--jp-color-border' => $border,
            '--brand' => $primary,
            '--brand-bright' => $this->adjustBrightness($primary, 1.1),
            '--gold' => $accent,
            '--brand-ink' => $primaryContrast,
            '--brand-soft' => $this->hexWithAlpha($primary, 0.14),
            '--shadow-brand' => '0 8px 24px -12px '.$this->hexWithAlpha($primary, 0.35),
            '--ring' => '0 0 0 3px '.$this->hexWithAlpha($accent, 0.35),
        ];
    }

    private function normalizeHex(mixed $hex): ?string
    {
        if (! is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/', trim($hex)) !== 1) {
            return null;
        }

        return strtoupper(trim($hex));
    }

    private function adjustBrightness(string $hex, float $factor): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#0F172A';
        }

        $r = max(0, min(255, (int) round(hexdec(substr($hex, 0, 2)) * $factor)));
        $g = max(0, min(255, (int) round(hexdec(substr($hex, 2, 2)) * $factor)));
        $b = max(0, min(255, (int) round(hexdec(substr($hex, 4, 2)) * $factor)));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private function mixWithWhite(string $hex, float $whiteRatio): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#F8FAFC';
        }

        $colorRatio = 1 - $whiteRatio;
        $r = (int) round(hexdec(substr($hex, 0, 2)) * $colorRatio + 255 * $whiteRatio);
        $g = (int) round(hexdec(substr($hex, 2, 2)) * $colorRatio + 255 * $whiteRatio);
        $b = (int) round(hexdec(substr($hex, 4, 2)) * $colorRatio + 255 * $whiteRatio);

        return sprintf('#%02X%02X%02X', min(255, $r), min(255, $g), min(255, $b));
    }

    private function contrastInk(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#0F172A';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.62 ? '#0F172A' : '#FFFFFF';
    }

    private function hexWithAlpha(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 'rgba(22,163,74,'.$alpha.')';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d,%d,%d,%.2f)', $r, $g, $b, $alpha);
    }
}
