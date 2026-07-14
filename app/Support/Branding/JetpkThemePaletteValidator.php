<?php

namespace App\Support\Branding;

/**
 * Validates JetPakistan day/night palette hex values and WCAG-oriented contrast.
 */
final class JetpkThemePaletteValidator
{
    private const HEX_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * @param  array<string, string>  $palette
     * @return array<string, list<string>>
     */
    public function validatePalette(string $theme, array $palette): array
    {
        $errors = [];
        $keys = config('jetpk-theme-palette.keys', []);

        foreach ($keys as $key) {
            $value = $palette[$key] ?? '';
            $fieldErrors = $this->validateHexField($key, $value);
            if ($fieldErrors !== []) {
                $errors[$key] = $fieldErrors;
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $primary = strtoupper((string) ($palette['primary'] ?? ''));
        $text = strtoupper((string) ($palette['text'] ?? ''));
        $pageBg = strtoupper((string) ($palette['page_bg'] ?? ''));
        $surface = strtoupper((string) ($palette['surface'] ?? ''));

        $buttonInk = $this->buttonInkForPrimary($primary);
        $buttonContrast = $this->contrastRatio($primary, $buttonInk);
        if ($buttonContrast < 3.0) {
            $errors['primary'][] = sprintf(
                'Primary action color needs more contrast with button text (%.1f:1). Try a %s green.',
                $buttonContrast,
                $theme === 'night' ? 'lighter' : 'darker',
            );
        }

        $textOnPage = $this->contrastRatio($text, $pageBg);
        if ($textOnPage < 4.5) {
            $errors['text'][] = sprintf(
                'Primary text contrast against page background is too low (%.1f:1). Adjust text or page background.',
                $textOnPage,
            );
        }

        $textOnSurface = $this->contrastRatio($text, $surface);
        if ($textOnSurface < 4.5) {
            $errors['text'][] = sprintf(
                'Primary text contrast against card surface is too low (%.1f:1). Adjust text or surface color.',
                $textOnSurface,
            );
        }

        if ($theme === 'night') {
            foreach (['primary', 'page_bg', 'surface'] as $darkKey) {
                $luminance = $this->relativeLuminance(strtoupper((string) ($palette[$darkKey] ?? '')));
                if ($darkKey === 'primary' && $luminance < 0.12) {
                    $errors[$darkKey][] = 'Night primary action is too dark for visible buttons. Use a lighter green.';
                }
                if (in_array($darkKey, ['page_bg', 'surface'], true) && $luminance > 0.35) {
                    $errors[$darkKey][] = 'Night '.$darkKey.' should stay dark enough for night mode readability.';
                }
            }
        }

        if ($theme === 'day') {
            foreach (['page_bg', 'surface'] as $lightKey) {
                $luminance = $this->relativeLuminance(strtoupper((string) ($palette[$lightKey] ?? '')));
                if ($luminance < 0.75) {
                    $errors[$lightKey][] = 'Day '.$lightKey.' looks too dark. Use a lighter surface for day theme.';
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public function validateHexField(string $field, mixed $value): array
    {
        if (! is_string($value)) {
            return ['Color must be a six-digit hex value.'];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return ['Color is required.'];
        }

        if (preg_match('/[;{}<>]/', $trimmed) === 1) {
            return ['Invalid characters detected. Use #RRGGBB only.'];
        }

        if (preg_match(self::HEX_PATTERN, $trimmed) !== 1) {
            return ['Use a six-digit hex color such as #006B45.'];
        }

        return [];
    }

    public function normalizeHex(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = strtoupper(trim($value));

        return preg_match(self::HEX_PATTERN, $trimmed) === 1 ? $trimmed : null;
    }

    public function contrastRatio(string $foreground, string $background): float
    {
        $l1 = $this->relativeLuminance($foreground);
        $l2 = $this->relativeLuminance($background);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public function buttonInkForPrimary(string $hex): string
    {
        $whiteContrast = $this->contrastRatio($hex, '#FFFFFF');
        $darkContrast = $this->contrastRatio($hex, '#0B1D2A');

        return $whiteContrast >= $darkContrast ? '#FFFFFF' : '#0B1D2A';
    }

    public function relativeLuminance(string $hex): float
    {
        $hex = ltrim(strtoupper($hex), '#');
        if (strlen($hex) !== 6) {
            return 0.0;
        }

        $channels = [];
        foreach ([0, 2, 4] as $offset) {
            $value = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
