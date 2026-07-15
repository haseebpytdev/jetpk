<?php

namespace App\Support\Homepage;

/**
 * Pixel-level validation for generated hero image variants.
 */
final class JetpkHeroImagePixelValidator
{
    private const SAMPLE_GRID = 8;

    private const MIN_LUMINANCE_VARIANCE = 40.0;

    private const MIN_AVERAGE_LUMINANCE = 8.0;

    private const MIN_NON_BLACK_RATIO = 0.12;

    /**
     * @return array{valid: bool, reason: string, width: int, height: int, bytes: int, average_luminance: float, luminance_variance: float, non_black_ratio: float}
     */
    public function validateSourceFile(string $absolutePath): array
    {
        $bytes = is_file($absolutePath) ? (int) filesize($absolutePath) : 0;
        if ($bytes < 256) {
            return $this->failure('empty file', 0, 0, $bytes);
        }

        $size = @getimagesize($absolutePath);
        if (! is_array($size) || ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1) {
            return $this->failure('not decodable', 0, 0, $bytes);
        }

        $width = (int) $size[0];
        $height = (int) $size[1];
        $image = $this->decode($absolutePath, (int) ($size[2] ?? 0));
        if ($image === null) {
            return $this->failure('decode failed', $width, $height, $bytes);
        }

        $stats = $this->sampleStats($image, $width, $height);
        imagedestroy($image);

        if ($stats['non_black_ratio'] < self::MIN_NON_BLACK_RATIO) {
            return $this->result(false, 'near-solid black source', $width, $height, $bytes, $stats);
        }

        if ($stats['average_luminance'] < self::MIN_AVERAGE_LUMINANCE) {
            return $this->result(false, 'source luminance too low', $width, $height, $bytes, $stats);
        }

        if ($stats['luminance_variance'] < self::MIN_LUMINANCE_VARIANCE) {
            return $this->result(false, 'source colour variance too low', $width, $height, $bytes, $stats);
        }

        return $this->result(true, 'ok', $width, $height, $bytes, $stats);
    }

    public function validateFile(string $absolutePath, int $expectedWidth, int $expectedHeight): array
    {
        $bytes = is_file($absolutePath) ? (int) filesize($absolutePath) : 0;
        if ($bytes < 1) {
            return $this->failure('empty file', $expectedWidth, $expectedHeight, $bytes);
        }

        if ($bytes < $this->minimumBytesFor($expectedWidth, $expectedHeight)) {
            return $this->failure('implausibly small file', $expectedWidth, $expectedHeight, $bytes);
        }

        $size = @getimagesize($absolutePath);
        if (! is_array($size) || ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1) {
            return $this->failure('not decodable', $expectedWidth, $expectedHeight, $bytes);
        }

        $width = (int) $size[0];
        $height = (int) $size[1];
        if (abs($width - $expectedWidth) > 2 || abs($height - $expectedHeight) > 2) {
            return $this->failure('unexpected dimensions', $width, $height, $bytes);
        }

        $image = $this->decode($absolutePath, (int) ($size[2] ?? 0));
        if ($image === null) {
            return $this->failure('decode failed', $width, $height, $bytes);
        }

        $stats = $this->sampleStats($image, $width, $height);
        imagedestroy($image);

        if ($stats['non_black_ratio'] < self::MIN_NON_BLACK_RATIO) {
            return $this->result(false, 'near-solid black output', $width, $height, $bytes, $stats);
        }

        if ($stats['average_luminance'] < self::MIN_AVERAGE_LUMINANCE) {
            return $this->result(false, 'average luminance too low', $width, $height, $bytes, $stats);
        }

        if ($stats['luminance_variance'] < self::MIN_LUMINANCE_VARIANCE) {
            return $this->result(false, 'near-zero colour variance', $width, $height, $bytes, $stats);
        }

        return $this->result(true, 'ok', $width, $height, $bytes, $stats);
    }

    public function environmentSupportsAvif(): bool
    {
        if (! function_exists('imageavif') || ! function_exists('imagecreatefromavif')) {
            return false;
        }

        if (! extension_loaded('gd')) {
            return false;
        }

        $probe = imagecreatetruecolor(32, 32);
        if ($probe === false) {
            return false;
        }

        $red = imagecolorallocate($probe, 220, 40, 40);
        imagefilledrectangle($probe, 0, 0, 31, 31, $red);
        $temp = tempnam(sys_get_temp_dir(), 'jp-avif-');
        if ($temp === false) {
            imagedestroy($probe);

            return false;
        }

        $path = $temp.'.avif';
        @unlink($temp);
        $written = @imageavif($probe, $path, 70);
        imagedestroy($probe);
        if (! $written || ! is_file($path)) {
            @unlink($path);

            return false;
        }

        $validation = $this->validateFile($path, 32, 32);
        @unlink($path);

        return $validation['valid'];
    }

    public function environmentSupportsWebp(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromwebp');
    }

    private function minimumBytesFor(int $width, int $height): int
    {
        $pixels = max(1, $width * $height);

        return (int) max(900, round($pixels * 0.02));
    }

    private function decode(string $path, int $type): ?\GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            19 => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /**
     * @return array{average_luminance: float, luminance_variance: float, non_black_ratio: float}
     */
    private function sampleStats(\GdImage $image, int $width, int $height): array
    {
        $luminances = [];
        $nonBlack = 0;

        for ($row = 0; $row < self::SAMPLE_GRID; $row++) {
            for ($col = 0; $col < self::SAMPLE_GRID; $col++) {
                $x = (int) round($col / max(1, self::SAMPLE_GRID - 1) * max(0, $width - 1));
                $y = (int) round($row / max(1, self::SAMPLE_GRID - 1) * max(0, $height - 1));
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $alpha = ($rgb & 0x7F000000) >> 24;
                if ($alpha >= 120) {
                    continue;
                }

                $luminance = (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);
                $luminances[] = $luminance;
                if ($luminance > 12) {
                    $nonBlack++;
                }
            }
        }

        if ($luminances === []) {
            return [
                'average_luminance' => 0.0,
                'luminance_variance' => 0.0,
                'non_black_ratio' => 0.0,
            ];
        }

        $average = array_sum($luminances) / count($luminances);
        $variance = 0.0;
        foreach ($luminances as $value) {
            $variance += ($value - $average) ** 2;
        }
        $variance /= count($luminances);

        return [
            'average_luminance' => $average,
            'luminance_variance' => $variance,
            'non_black_ratio' => $nonBlack / count($luminances),
        ];
    }

    /**
     * @param  array{average_luminance: float, luminance_variance: float, non_black_ratio: float}  $stats
     * @return array{valid: bool, reason: string, width: int, height: int, bytes: int, average_luminance: float, luminance_variance: float, non_black_ratio: float}
     */
    private function result(
        bool $valid,
        string $reason,
        int $width,
        int $height,
        int $bytes,
        array $stats,
    ): array {
        return [
            'valid' => $valid,
            'reason' => $reason,
            'width' => $width,
            'height' => $height,
            'bytes' => $bytes,
            'average_luminance' => $stats['average_luminance'],
            'luminance_variance' => $stats['luminance_variance'],
            'non_black_ratio' => $stats['non_black_ratio'],
        ];
    }

  /** @return array{valid: bool, reason: string, width: int, height: int, bytes: int, average_luminance: float, luminance_variance: float, non_black_ratio: float} */
    private function failure(string $reason, int $width, int $height, int $bytes): array
    {
        return [
            'valid' => false,
            'reason' => $reason,
            'width' => $width,
            'height' => $height,
            'bytes' => $bytes,
            'average_luminance' => 0.0,
            'luminance_variance' => 0.0,
            'non_black_ratio' => 0.0,
        ];
    }
}
