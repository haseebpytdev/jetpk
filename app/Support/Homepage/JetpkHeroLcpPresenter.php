<?php

namespace App\Support\Homepage;

/**
 * Resolves predictable LCP hero picture sources from the CMS hero URL.
 *
 * Optimized AVIF/WebP/JPEG variants live beside the homepage media folder under
 * `pages/home/lcp/hero-{desktop|tablet|mobile}.{ext}` when generated via
 * `php artisan jetpk:hero-lcp-assets`.
 */
final class JetpkHeroLcpPresenter
{
  /** @var list<string> */
    private const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];

  /** @var array<string, array{width: int, height: int, media: string}> */
    private const BREAKPOINT_META = [
        'desktop' => ['width' => 1200, 'height' => 600, 'media' => '(min-width: 1025px)'],
        'tablet' => ['width' => 1024, 'height' => 512, 'media' => '(min-width: 769px) and (max-width: 1024px)'],
        'mobile' => ['width' => 768, 'height' => 384, 'media' => '(max-width: 768px)'],
    ];

    public function present(?string $heroUrl, string $altText = ''): ?array
    {
        $heroUrl = trim((string) $heroUrl);
        if ($heroUrl === '') {
            return null;
        }

        $intrinsic = $this->intrinsicFromUrl($heroUrl);
        $lcpBase = $this->lcpBaseUrl($heroUrl);
        $sources = [];

        if ($lcpBase !== null) {
            foreach (self::BREAKPOINTS as $breakpoint) {
                $meta = self::BREAKPOINT_META[$breakpoint];
                foreach (['avif', 'webp'] as $format) {
                    $url = $lcpBase.'/hero-'.$breakpoint.'.'.$format;
                    if ($this->urlFileExists($url)) {
                        $sources[] = [
                            'type' => 'image/'.$format,
                            'media' => $meta['media'],
                            'srcset' => $url,
                        ];
                    }
                }
            }
        }

        $fallbackUrl = $this->resolveFallbackUrl($heroUrl, $lcpBase);
        if ($fallbackUrl === null) {
            return null;
        }

        $fallbackIntrinsic = $this->intrinsicFromUrl($fallbackUrl) ?? $intrinsic;

        return [
            'alt' => $altText !== '' ? $altText : 'JetPakistan flights from Pakistan',
            'width' => $fallbackIntrinsic['width'],
            'height' => $fallbackIntrinsic['height'],
            'fallback_url' => $fallbackUrl,
            'preload_url' => $this->resolvePreloadUrl($sources, $fallbackUrl),
            'preload_type' => $this->resolvePreloadType($sources, $fallbackUrl),
            'preload_media' => '(min-width: 1025px)',
            'sources' => $sources,
            'has_responsive_variants' => $sources !== [],
        ];
    }

    public function lcpDirectoryForHeroUrl(string $heroUrl): ?string
    {
        $path = $this->publicPathFromUrl($heroUrl);
        if ($path === null) {
            return null;
        }

        $homeDir = dirname($path);
        if (! is_dir($homeDir)) {
            return null;
        }

        $lcpDir = $homeDir.'/lcp';

        return is_dir($lcpDir) ? $lcpDir : null;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function intrinsicFromUrl(string $url): ?array
    {
        $path = $this->publicPathFromUrl($url);
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);
        if (! is_array($size) || ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1) {
            return null;
        }

        return ['width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    private function lcpBaseUrl(string $heroUrl): ?string
    {
        $path = $this->publicPathFromUrl($heroUrl);
        if ($path === null) {
            return null;
        }

        $lcpDir = dirname($path).'/lcp';
        if (! is_dir($lcpDir)) {
            return null;
        }

        $relative = $this->relativePublicPath($lcpDir);
        if ($relative === null) {
            return null;
        }

        return asset($relative);
    }

    private function resolveFallbackUrl(string $heroUrl, ?string $lcpBase): ?string
    {
        if ($lcpBase !== null) {
            foreach (['desktop', 'tablet', 'mobile'] as $breakpoint) {
                $candidate = $lcpBase.'/hero-'.$breakpoint.'.jpg';
                if ($this->urlFileExists($candidate)) {
                    return $candidate;
                }
            }
        }

        return $heroUrl;
    }

    /**
     * @param  list<array{type: string, media: string, srcset: string}>  $sources
     */
    private function resolvePreloadUrl(array $sources, string $fallbackUrl): string
    {
        foreach ($sources as $source) {
            if ($source['media'] === '(min-width: 1025px)' && $source['type'] === 'image/avif') {
                return $source['srcset'];
            }
        }

        foreach ($sources as $source) {
            if ($source['media'] === '(min-width: 1025px)' && $source['type'] === 'image/webp') {
                return $source['srcset'];
            }
        }

        return $fallbackUrl;
    }

    /**
     * @param  list<array{type: string, media: string, srcset: string}>  $sources
     */
    private function resolvePreloadType(array $sources, string $fallbackUrl): ?string
    {
        $preload = $this->resolvePreloadUrl($sources, $fallbackUrl);

        foreach ($sources as $source) {
            if ($source['srcset'] === $preload) {
                return $source['type'];
            }
        }

        if (str_ends_with(strtolower($preload), '.webp')) {
            return 'image/webp';
        }

        if (str_ends_with(strtolower($preload), '.avif')) {
            return 'image/avif';
        }

        return null;
    }

    private function urlFileExists(string $url): bool
    {
        $path = $this->publicPathFromUrl($url);

        return $path !== null && is_file($path);
    }

    private function publicPathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = rawurldecode($path);

        if (str_starts_with($path, '/storage/')) {
            $storageRelative = ltrim(substr($path, strlen('/storage/')), '/');

            return storage_path('app/public/'.$storageRelative);
        }

        $relative = ltrim($path, '/');
        $publicPath = public_path($relative);

        return is_file($publicPath) ? $publicPath : null;
    }

    private function relativePublicPath(string $absolutePath): ?string
    {
        $publicRoot = rtrim(str_replace('\\', '/', public_path()), '/').'/';
        $normalized = str_replace('\\', '/', $absolutePath);

        if (! str_starts_with($normalized, $publicRoot)) {
            return null;
        }

        return ltrim(substr($normalized, strlen($publicRoot)), '/');
    }
}
