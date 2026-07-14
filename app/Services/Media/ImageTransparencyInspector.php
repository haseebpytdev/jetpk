<?php

namespace App\Services\Media;

use App\Data\Media\TransparencyInspection;

/**
 * Read-only PNG/raster transparency analysis using GD when available.
 */
final class ImageTransparencyInspector
{
    public function inspect(string $absolutePath): TransparencyInspection
    {
        if (! is_file($absolutePath)) {
            return TransparencyInspection::unknown('Image file not found.');
        }

        if (! extension_loaded('gd')) {
            return TransparencyInspection::unknown('GD extension unavailable; transparency could not be verified.');
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return TransparencyInspection::unknown('Could not read image dimensions.');
        }

        $mime = (string) ($info['mime'] ?? '');
        $image = match ($mime) {
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if ($image === false) {
            return TransparencyInspection::unknown('Could not decode image for transparency analysis.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        if ($width <= 0 || $height <= 0) {
            return TransparencyInspection::unknown('Invalid image dimensions.');
        }

        if ($mime !== 'image/png' && $mime !== 'image/webp') {
            return new TransparencyInspection(
                known: true,
                hasAlphaChannel: false,
                hasTransparentPixels: false,
                transparentPixelRatio: 0.0,
                opaquePixelRatio: 1.0,
                isFullyTransparent: false,
                isFullyOpaque: true,
            );
        }

        return $this->inspectPngOrWebpPixels($absolutePath, $mime, $width, $height);
    }

    public function hasAlphaChannel(string $absolutePath): bool
    {
        return $this->inspect($absolutePath)->hasAlphaChannel;
    }

    public function hasTransparentPixels(string $absolutePath): bool
    {
        return $this->inspect($absolutePath)->hasTransparentPixels;
    }

    public function transparentPixelRatio(string $absolutePath): float
    {
        return $this->inspect($absolutePath)->transparentPixelRatio;
    }

    public function isFullyTransparent(string $absolutePath): bool
    {
        return $this->inspect($absolutePath)->isFullyTransparent;
    }

    public function isFullyOpaque(string $absolutePath): bool
    {
        return $this->inspect($absolutePath)->isFullyOpaque;
    }

    private function inspectPngOrWebpPixels(string $absolutePath, string $mime, int $width, int $height): TransparencyInspection
    {
        $image = $mime === 'image/webp' && function_exists('imagecreatefromwebp')
            ? @imagecreatefromwebp($absolutePath)
            : @imagecreatefrompng($absolutePath);

        if ($image === false) {
            return TransparencyInspection::unknown('Could not decode image pixels.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = 0;
        $opaque = 0;
        $total = $width * $height;
        $step = max(1, (int) floor(sqrt($total / 50_000)));

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha >= 120) {
                    $transparent++;
                } else {
                    $opaque++;
                }
            }
        }

        imagedestroy($image);

        $sampled = max(1, $transparent + $opaque);
        $transparentRatio = $transparent / $sampled;
        $opaqueRatio = $opaque / $sampled;

        return new TransparencyInspection(
            known: true,
            hasAlphaChannel: true,
            hasTransparentPixels: $transparent > 0,
            transparentPixelRatio: round($transparentRatio, 5),
            opaquePixelRatio: round($opaqueRatio, 5),
            isFullyTransparent: $transparentRatio > 0.995,
            isFullyOpaque: $transparentRatio < 0.005,
            warning: $transparentRatio > 0.85 ? 'Most of the image appears transparent. Review before applying.' : null,
        );
    }
}
