<?php

namespace App\Services\Homepage;

use App\Support\Homepage\JetpkHeroImagePixelValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Profile-driven responsive image derivatives (AVIF/WebP/JPEG) for JetPakistan homepage CMS media.
 */
final class JetpkResponsiveImageOptimizer
{
    public const PROFILE_HERO = 'hero';

    public const PROFILE_CARD = 'card';

    public const PROFILE_SUPPORT_BACKGROUND = 'support-background';

    /** @var array<string, array{dir: string, filename_prefix: string, widths: array<string, int>, jpg: int, webp: int, avif: int}> */
    private const PROFILES = [
        self::PROFILE_HERO => [
            'dir' => 'lcp',
            'filename_prefix' => 'hero',
            'widths' => [
                'desktop' => 1200,
                'tablet' => 1024,
                'mobile' => 768,
            ],
            'jpg' => 85,
            'webp' => 85,
            'avif' => 70,
        ],
        self::PROFILE_CARD => [
            'dir' => 'card',
            'filename_prefix' => 'card',
            'widths' => [
                '320' => 320,
                '480' => 480,
                '640' => 640,
                '768' => 768,
            ],
            'jpg' => 82,
            'webp' => 80,
            'avif' => 60,
        ],
        self::PROFILE_SUPPORT_BACKGROUND => [
            'dir' => 'support',
            'filename_prefix' => 'support',
            'widths' => [
                '480' => 480,
                '768' => 768,
                '1024' => 1024,
                '1280' => 1280,
            ],
            'jpg' => 85,
            'webp' => 85,
            'avif' => 70,
        ],
    ];

    public function __construct(
        private readonly JetpkHeroImagePixelValidator $pixelValidator,
    ) {}

    /**
     * @return list<string>
     */
    public static function knownProfiles(): array
    {
        return array_keys(self::PROFILES);
    }

    /**
     * @return array<string, int>
     */
    public function widthsFor(string $profile): array
    {
        return self::PROFILES[$profile]['widths'] ?? [];
    }

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
        string $mediaProfile,
        string $pageKey = 'home',
        ?string $relativeSourcePath = null,
    ): array {
        if (! isset(self::PROFILES[$mediaProfile])) {
            return $this->failureResult('', 'Unknown responsive media profile: '.$mediaProfile);
        }

        if (! extension_loaded('gd')) {
            return $this->failureResult('', 'GD extension is unavailable for responsive image optimization.');
        }

        if (! is_file($absoluteSourcePath) || ! is_readable($absoluteSourcePath)) {
            return $this->failureResult('', 'Source file is missing or unreadable.');
        }

        $fingerprint = substr(hash_file('sha256', $absoluteSourcePath), 0, 16);
        $sourceStats = $this->pixelValidator->validateSourceFile($absoluteSourcePath);

        if (! $sourceStats['valid']) {
            return $this->failureResult(
                $fingerprint,
                'Uploaded image appears blank or unreadable. Original upload kept; responsive variants were not activated.',
            );
        }

        $profile = self::PROFILES[$mediaProfile];
        $relativeOutputBase = sprintf(
            'client-assets/%s/pages/%s/%s/%s',
            trim($profileSlug),
            trim($pageKey),
            $profile['dir'],
            $fingerprint,
        );

        $disk = Storage::disk('public');
        $absoluteOutputBase = $disk->path($relativeOutputBase);
        File::ensureDirectoryExists($absoluteOutputBase);

        $supportsWebp = $this->pixelValidator->environmentSupportsWebp();
        $supportsAvif = $this->pixelValidator->environmentSupportsAvif();

        $sourceSize = @getimagesize($absoluteSourcePath);
        $sourceWidth = (int) ($sourceSize[0] ?? 0);
        $sourceHeight = (int) ($sourceSize[1] ?? 0);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return $this->failureResult($fingerprint, 'Source dimensions could not be read.');
        }

        $variants = [];
        $validation = [];
        $publishedPaths = [];
        if (is_string($relativeSourcePath) && trim($relativeSourcePath) !== '') {
            $publishedPaths[] = trim($relativeSourcePath);
        }

        /** @var array<int, array{breakpoint: string, path_stem: string, width: int, height: int}> $planned */
        $planned = [];
        $usedWidths = [];

        foreach ($profile['widths'] as $breakpoint => $targetWidth) {
            $effectiveWidth = min((int) $targetWidth, $sourceWidth);
            if ($effectiveWidth < 1) {
                continue;
            }

            if (isset($usedWidths[$effectiveWidth])) {
                $donor = $usedWidths[$effectiveWidth];
                $planned[] = [
                    'breakpoint' => (string) $breakpoint,
                    'path_stem' => $donor['path_stem'],
                    'width' => $effectiveWidth,
                    'height' => $donor['height'],
                    'alias_of' => $donor['breakpoint'],
                ];

                continue;
            }

            $effectiveHeight = (int) max(1, round($sourceHeight * ($effectiveWidth / $sourceWidth)));
            $pathStem = $profile['filename_prefix'].'-'.$breakpoint;
            $usedWidths[$effectiveWidth] = [
                'breakpoint' => (string) $breakpoint,
                'path_stem' => $pathStem,
                'height' => $effectiveHeight,
            ];
            $planned[] = [
                'breakpoint' => (string) $breakpoint,
                'path_stem' => $pathStem,
                'width' => $effectiveWidth,
                'height' => $effectiveHeight,
            ];
        }

        foreach ($planned as $plan) {
            $formats = ['jpg' => $profile['jpg']];
            if ($supportsWebp) {
                $formats['webp'] = $profile['webp'];
            }
            if ($supportsAvif) {
                $formats['avif'] = $profile['avif'];
            }

            $isAlias = isset($plan['alias_of']);

            foreach ($formats as $format => $quality) {
                $filename = $plan['path_stem'].'.'.$format;
                $relativePath = $relativeOutputBase.'/'.$filename;
                $absolutePath = $disk->path($relativePath);

                if ($absolutePath === $absoluteSourcePath) {
                    continue;
                }

                if (! $isAlias) {
                    $written = $this->writeVariant(
                        $absoluteSourcePath,
                        $absolutePath,
                        $plan['width'],
                        $plan['height'],
                        $format,
                        $quality,
                    );

                    if (! $written) {
                        $validation[] = [
                            'breakpoint' => $plan['breakpoint'],
                            'format' => $format,
                            'valid' => false,
                            'reason' => 'encode failed',
                        ];

                        continue;
                    }

                    $result = $this->pixelValidator->validateFile($absolutePath, $plan['width'], $plan['height'], $format);
                    $validation[] = array_merge(
                        ['breakpoint' => $plan['breakpoint'], 'format' => $format],
                        $result,
                    );

                    if (! $result['valid']) {
                        @unlink($absolutePath);

                        continue;
                    }
                } elseif (! is_file($absolutePath)) {
                    // Alias reuses the donor stem path; if donor encode failed, skip.
                    continue;
                } else {
                    $validation[] = [
                        'breakpoint' => $plan['breakpoint'],
                        'format' => $format,
                        'valid' => true,
                        'reason' => 'aliased from '.$plan['alias_of'],
                    ];
                }

                if (! is_file($absolutePath) || filesize($absolutePath) < 1) {
                    continue;
                }

                $variants[$plan['breakpoint']][$format] = [
                    'path' => $relativePath,
                    'url' => $disk->url($relativePath),
                    'width' => $plan['width'],
                    'height' => $plan['height'],
                    'bytes' => (int) filesize($absolutePath),
                ];
                $publishedPaths[] = $relativePath;
            }
        }

        $hasMandatoryJpeg = $this->hasValidatedFormat($variants, 'jpg');
        if (! $hasMandatoryJpeg) {
            $this->deleteVariantDirectory($profileSlug, $pageKey, $mediaProfile, $fingerprint);

            return [
                'activated' => false,
                'fingerprint' => $fingerprint,
                'warning' => 'Responsive image optimization failed validation. Original upload kept.',
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
            'profile' => $mediaProfile,
            'fingerprint' => $fingerprint,
            'source_path' => $relativeSourcePath,
            'source_width' => $sourceWidth,
            'source_height' => $sourceHeight,
            'source_bytes' => is_file($absoluteSourcePath) ? (int) filesize($absoluteSourcePath) : 0,
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

    public function deleteVariantDirectory(
        string $profileSlug,
        string $pageKey,
        string $mediaProfile,
        string $fingerprint,
    ): void {
        $dir = self::PROFILES[$mediaProfile]['dir'] ?? null;
        if ($dir === null) {
            return;
        }

        $relative = sprintf(
            'client-assets/%s/pages/%s/%s/%s',
            trim($profileSlug),
            trim($pageKey),
            $dir,
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

        // Photographic CMS media: flatten onto white. Branding/logos are out of this pipeline.
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
            'webp' => function_exists('imagewebp') ? imagewebp($canvas, $dest, $quality) : false,
            'avif' => function_exists('imageavif') ? imageavif($canvas, $dest, $quality) : false,
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
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
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
