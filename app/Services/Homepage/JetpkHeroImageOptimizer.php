<?php

namespace App\Services\Homepage;

use App\Support\Homepage\JetpkHeroImagePixelValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Generates validated responsive hero variants from a stored CMS upload.
 */
final class JetpkHeroImageOptimizer
{
  /** @var array<string, int> */
    private const TARGET_WIDTHS = [
        'desktop' => 1200,
        'tablet' => 1024,
        'mobile' => 768,
    ];

    public function __construct(
        private readonly JetpkHeroImagePixelValidator $pixelValidator,
    ) {}

    /**
     * @return array{
     *     activated: bool,
     *     fingerprint: string,
     *     warning: ?string,
     *     manifest: ?array<string, mixed>,
     *     published_paths: list<string>,
     *     validation: list<array<string, mixed>>
     * }
     */
    public function optimize(
        string $absoluteSourcePath,
        string $profileSlug,
        string $pageKey = 'home',
        ?string $relativeSourcePath = null,
    ): array {
        if (! extension_loaded('gd')) {
            return $this->failureResult('', 'GD extension is unavailable for hero optimization.');
        }

        if (! is_file($absoluteSourcePath) || ! is_readable($absoluteSourcePath)) {
            return $this->failureResult('', 'Hero source file is missing or unreadable.');
        }

        $fingerprint = substr(hash_file('sha256', $absoluteSourcePath), 0, 16);
        $sourceStats = $this->pixelValidator->validateSourceFile($absoluteSourcePath);

        if (! $sourceStats['valid']) {
            return $this->failureResult(
                $fingerprint,
                'Uploaded hero appears blank or unreadable. Original upload kept; responsive variants were not activated.',
            );
        }

        $relativeOutputBase = sprintf(
            'client-assets/%s/pages/%s/lcp/%s',
            trim($profileSlug),
            trim($pageKey),
            $fingerprint,
        );

        $disk = Storage::disk('public');
        $absoluteOutputBase = $disk->path($relativeOutputBase);
        File::ensureDirectoryExists($absoluteOutputBase);

        $supportsWebp = $this->pixelValidator->environmentSupportsWebp();
        $supportsAvif = $this->pixelValidator->environmentSupportsAvif();

        $variants = [];
        $validation = [];
        $publishedPaths = [];
        if (is_string($relativeSourcePath) && trim($relativeSourcePath) !== '') {
            $publishedPaths[] = trim($relativeSourcePath);
        }

        foreach (self::TARGET_WIDTHS as $breakpoint => $targetWidth) {
            $sourceSize = @getimagesize($absoluteSourcePath);
            $sourceWidth = (int) ($sourceSize[0] ?? $targetWidth);
            $sourceHeight = (int) ($sourceSize[1] ?? 1);
            $targetHeight = (int) max(1, round($sourceHeight * ($targetWidth / max(1, $sourceWidth))));

            $formats = ['jpg' => 85];
            if ($supportsWebp) {
                $formats['webp'] = 85;
            }
            if ($supportsAvif) {
                $formats['avif'] = 70;
            }

            foreach ($formats as $format => $quality) {
                $filename = 'hero-'.$breakpoint.'.'.$format;
                $relativePath = $relativeOutputBase.'/'.$filename;
                $absolutePath = $disk->path($relativePath);

                if ($absolutePath === $absoluteSourcePath) {
                    continue;
                }

                $written = $this->writeVariant(
                    $absoluteSourcePath,
                    $absolutePath,
                    $targetWidth,
                    $targetHeight,
                    $format,
                    $quality,
                );

                if (! $written) {
                    $validation[] = [
                        'breakpoint' => $breakpoint,
                        'format' => $format,
                        'valid' => false,
                        'reason' => 'encode failed',
                    ];

                    continue;
                }

                $result = $this->pixelValidator->validateFile($absolutePath, $targetWidth, $targetHeight);
                $validation[] = array_merge(
                    ['breakpoint' => $breakpoint, 'format' => $format],
                    $result,
                );

                if (! $result['valid']) {
                    @unlink($absolutePath);

                    continue;
                }

                $variants[$breakpoint][$format] = [
                    'path' => $relativePath,
                    'url' => $disk->url($relativePath),
                    'width' => $targetWidth,
                    'height' => $targetHeight,
                    'bytes' => (int) filesize($absolutePath),
                ];
                $publishedPaths[] = $relativePath;
            }
        }

        $hasMandatoryJpeg = $this->hasValidatedFormat($variants, 'jpg');
        if (! $hasMandatoryJpeg) {
            $this->deleteVariantDirectory($profileSlug, $pageKey, $fingerprint);

            return [
                'activated' => false,
                'fingerprint' => $fingerprint,
                'warning' => 'Responsive hero optimization failed validation. Original upload kept.',
                'manifest' => null,
                'published_paths' => array_values(array_unique(array_filter($publishedPaths))),
                'validation' => $validation,
            ];
        }

        $formatKeys = [];
        foreach ($variants as $breakpointVariants) {
            $formatKeys = array_merge($formatKeys, array_keys($breakpointVariants));
        }

        $relativeSourcePath = trim((string) ($relativeSourcePath ?? ''));
        if ($relativeSourcePath === '') {
            $relativeSourcePath = $this->relativeFromAbsolute($absoluteSourcePath);
        }

        $manifest = [
            'fingerprint' => $fingerprint,
            'source_path' => $relativeSourcePath,
            'generated_at' => now()->toIso8601String(),
            'formats' => array_values(array_unique($formatKeys)),
            'variants' => $variants,
            'validation' => $validation,
        ];

        return [
            'activated' => true,
            'fingerprint' => $fingerprint,
            'warning' => null,
            'manifest' => $manifest,
            'published_paths' => array_values(array_unique($publishedPaths)),
            'validation' => $validation,
        ];
    }

    public function deleteVariantDirectory(string $profileSlug, string $pageKey, string $fingerprint): void
    {
        $relative = sprintf(
            'client-assets/%s/pages/%s/lcp/%s',
            trim($profileSlug),
            trim($pageKey),
            trim($fingerprint),
        );

        Storage::disk('public')->deleteDirectory($relative);
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $variants
     */
    private function hasValidatedFormat(array $variants, string $format): bool
    {
        foreach ($variants as $breakpointVariants) {
            if (isset($breakpointVariants[$format])) {
                return true;
            }
        }

        return false;
    }

    private function writeVariant(
        string $source,
        string $dest,
        int $targetWidth,
        int $targetHeight,
        string $format,
        int $quality,
    ): bool {
        $image = $this->loadImage($source);
        if ($image === null) {
            return false;
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            imagedestroy($image);

            return false;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);

        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($image),
            imagesy($image),
        );

        $written = match ($format) {
            'jpg' => imagejpeg($canvas, $dest, $quality),
            'webp' => imagewebp($canvas, $dest, $quality),
            'avif' => imageavif($canvas, $dest, $quality),
            default => false,
        };

        imagedestroy($image);
        imagedestroy($canvas);

        return (bool) $written && is_file($dest) && filesize($dest) > 0;
    }

    private function loadImage(string $source): ?\GdImage
    {
        $type = (int) (@getimagesize($source)[2] ?? 0);

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            19 => function_exists('imagecreatefromavif') ? @imagecreatefromavif($source) : false,
            default => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    private function relativeFromAbsolute(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        $roots = [
            rtrim(str_replace('\\', '/', storage_path('app/public')), '/').'/',
            rtrim(str_replace('\\', '/', public_path('storage')), '/').'/',
        ];

        foreach ($roots as $root) {
            if (str_starts_with($normalized, $root)) {
                return ltrim(substr($normalized, strlen($root)), '/');
            }
        }

        if (preg_match('#/framework/testing/disks/public/(.+)$#', $normalized, $matches) === 1) {
            return $matches[1];
        }

        return basename($normalized);
    }

  /** @return array{activated: bool, fingerprint: string, warning: ?string, manifest: ?array<string, mixed>, published_paths: list<string>, validation: list<array<string, mixed>>} */
    private function failureResult(string $fingerprint, string $warning): array
    {
        return [
            'activated' => false,
            'fingerprint' => $fingerprint,
            'warning' => $warning,
            'manifest' => null,
            'published_paths' => [],
            'validation' => [],
        ];
    }
}
