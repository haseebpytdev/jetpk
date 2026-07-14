<?php

namespace App\Services\Branding;

/**
 * Extracts dominant brand colors from client logo files (SVG + raster) without external APIs.
 */
final class LogoPaletteExtractor
{
    /** @var list<string> */
    private const IGNORE_COLORS = [
        '#ffffff', '#fff', '#fefefe', '#000000', '#000', '#111111', '#222222',
        'transparent', 'none',
    ];

    /**
     * @return array{
     *     primary: string,
     *     secondary: string,
     *     accent: string,
     *     background: string,
     *     surface: string,
     *     text: string,
     *     muted: string,
     *     success: string,
     *     warning: string,
     *     danger: string,
     *     swatches: list<string>,
     *     contrast_warnings: list<string>
     * }
     */
    public function extractFromPath(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return $this->fallbackPalette('Logo file not readable.');
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $swatches = match ($extension) {
            'svg' => $this->extractFromSvg((string) file_get_contents($absolutePath)),
            'png', 'jpg', 'jpeg', 'webp', 'gif' => $this->extractFromRaster($absolutePath),
            default => [],
        };

        if ($swatches === []) {
            return $this->fallbackPalette('No usable colors found in logo.');
        }

        return $this->buildPalette($swatches);
    }

    /**
     * @return list<string>
     */
    private function extractFromSvg(string $svg): array
    {
        $colors = [];
        if (preg_match_all('/(?:fill|stroke)\s*=\s*["\']?(#[0-9A-Fa-f]{3,8}|rgb\([^)]+\))/i', $svg, $matches)) {
            foreach ($matches[1] as $raw) {
                $hex = $this->normalizeColor((string) $raw);
                if ($hex !== null && ! $this->shouldIgnore($hex)) {
                    $colors[] = $hex;
                }
            }
        }
        if (preg_match_all('/#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})\b/', $svg, $hexMatches)) {
            foreach ($hexMatches[0] as $raw) {
                $hex = $this->normalizeColor((string) $raw);
                if ($hex !== null && ! $this->shouldIgnore($hex)) {
                    $colors[] = $hex;
                }
            }
        }

        return $this->rankColors($colors);
    }

    /**
     * @return list<string>
     */
    private function extractFromRaster(string $absolutePath): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [];
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false) {
            return [];
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return [];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $samples = [];
        $stepX = max(1, (int) floor($width / 24));
        $stepY = max(1, (int) floor($height / 24));

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha >= 100) {
                    continue;
                }
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $hex = $this->rgbToHex($r, $g, $b);
                if (! $this->shouldIgnore($hex)) {
                    $samples[] = $hex;
                }
            }
        }

        imagedestroy($image);

        return $this->rankColors($samples);
    }

    /**
     * @param  list<string>  $swatches
     * @return array{
     *     primary: string,
     *     secondary: string,
     *     accent: string,
     *     background: string,
     *     surface: string,
     *     text: string,
     *     muted: string,
     *     success: string,
     *     warning: string,
     *     danger: string,
     *     swatches: list<string>,
     *     contrast_warnings: list<string>
     * }
     */
    private function buildPalette(array $swatches): array
    {
        $primary = $swatches[0] ?? '#EA7A1E';
        $secondary = $swatches[1] ?? $this->lighten($primary, 0.12);
        $accent = $swatches[2] ?? '#0BA5AD';
        $background = '#EDF3F7';
        $surface = '#FFFFFF';
        $text = $this->pickReadableText($background);
        $muted = '#5F7585';

        $warnings = [];
        if ($this->contrastRatio($primary, $surface) < 3.0) {
            $warnings[] = 'Primary on surface may be low contrast.';
        }

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
            'background' => $background,
            'surface' => $surface,
            'text' => $text,
            'muted' => $muted,
            'success' => '#16A34A',
            'warning' => '#D69E2E',
            'danger' => '#DC2626',
            'swatches' => array_values(array_unique($swatches)),
            'contrast_warnings' => $warnings,
        ];
    }

    /**
     * @return array{
     *     primary: string,
     *     secondary: string,
     *     accent: string,
     *     background: string,
     *     surface: string,
     *     text: string,
     *     muted: string,
     *     success: string,
     *     warning: string,
     *     danger: string,
     *     swatches: list<string>,
     *     contrast_warnings: list<string>
     * }
     */
    private function fallbackPalette(string $reason): array
    {
        $palette = $this->buildPalette(['#EA7A1E', '#0BA5AD', '#0C8FD6']);
        $palette['contrast_warnings'][] = $reason;

        return $palette;
    }

    /**
     * @param  list<string>  $colors
     * @return list<string>
     */
    private function rankColors(array $colors): array
    {
        $counts = [];
        foreach ($colors as $color) {
            $counts[$color] = ($counts[$color] ?? 0) + 1;
        }
        arsort($counts);

        return array_slice(array_keys($counts), 0, 6);
    }

    private function normalizeColor(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (str_starts_with($raw, 'rgb')) {
            if (preg_match('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $raw, $m)) {
                return $this->rgbToHex((int) $m[1], (int) $m[2], (int) $m[3]);
            }

            return null;
        }
        if (! str_starts_with($raw, '#')) {
            $raw = '#'.$raw;
        }
        if (strlen($raw) === 4) {
            $raw = '#'.$raw[1].$raw[1].$raw[2].$raw[2].$raw[3].$raw[3];
        }

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $raw) ? strtoupper($raw) : null;
    }

    private function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private function shouldIgnore(string $hex): bool
    {
        return in_array(strtolower($hex), self::IGNORE_COLORS, true);
    }

    private function lighten(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        $r = min(255, (int) round(hexdec(substr($hex, 0, 2)) * (1 + $amount)));
        $g = min(255, (int) round(hexdec(substr($hex, 2, 2)) * (1 + $amount)));
        $b = min(255, (int) round(hexdec(substr($hex, 4, 2)) * (1 + $amount)));

        return $this->rgbToHex($r, $g, $b);
    }

    private function pickReadableText(string $background): string
    {
        return $this->contrastRatio('#0B1A26', $background) >= $this->contrastRatio('#FFFFFF', $background)
            ? '#0B1A26'
            : '#FFFFFF';
    }

    private function contrastRatio(string $fg, string $bg): float
    {
        $l1 = $this->relativeLuminance($fg);
        $l2 = $this->relativeLuminance($bg);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
        $linear = array_map(static function (float $c): float {
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
