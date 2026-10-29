<?php

namespace App\Support\Homepage;

use App\Models\ClientPageAsset;
use App\Support\Client\ClientPageKeys;
use Illuminate\Support\Facades\Storage;

/**
 * Builds browser-ready picture/srcset payloads from responsive_media / hero_lcp manifests.
 */
final class JetpkResponsiveMediaPresenter
{
    /** @var array<string, string> */
    private const HERO_MEDIA = [
        'desktop' => '(min-width: 1025px)',
        'tablet' => '(min-width: 769px) and (max-width: 1024px)',
        'mobile' => '(max-width: 768px)',
    ];

    /** @var list<string> */
    private const MODERN_FORMATS = ['avif', 'webp'];

    /**
     * Width-based card/support presentation for Next <picture> consumption.
     *
     * @return array{
     *     fallback_url: string,
     *     width: int,
     *     height: int,
     *     sizes: string,
     *     sources: list<array{type: string, srcset: string}>,
     *     has_responsive_variants: bool,
     *     fingerprint: ?string,
     *     profile: ?string
     * }|null
     */
    public function presentWidthSrcset(
        string $fallbackUrl,
        ?array $manifest,
        string $sizes,
        int $defaultWidth = 640,
        int $defaultHeight = 480,
    ): ?array {
        $fallbackUrl = trim($fallbackUrl);
        if ($fallbackUrl === '') {
            return null;
        }

        $manifest = $this->resolveManifest($fallbackUrl, $manifest);
        $sources = [];

        if ($manifest !== null) {
            foreach (self::MODERN_FORMATS as $format) {
                $srcset = $this->buildWidthSrcset($manifest, $format);
                if ($srcset !== null) {
                    $sources[] = [
                        'type' => 'image/'.$format,
                        'srcset' => $srcset,
                    ];
                }
            }

            $jpgSrcset = $this->buildWidthSrcset($manifest, 'jpg');
            if ($jpgSrcset !== null) {
                $sources[] = [
                    'type' => 'image/jpeg',
                    'srcset' => $jpgSrcset,
                ];
            }
        }

        $resolvedFallback = $this->resolveJpegFallback($fallbackUrl, $manifest);
        $intrinsic = $this->largestIntrinsic($manifest) ?? ['width' => $defaultWidth, 'height' => $defaultHeight];

        return [
            'fallback_url' => $resolvedFallback,
            'width' => $intrinsic['width'],
            'height' => $intrinsic['height'],
            'sizes' => $sizes,
            'sources' => $sources,
            'has_responsive_variants' => $sources !== [],
            'fingerprint' => is_array($manifest) ? ($manifest['fingerprint'] ?? null) : null,
            'profile' => is_array($manifest) ? ($manifest['profile'] ?? null) : null,
        ];
    }

    /**
     * Hero presentation using media-query sources (avoids downloading mobile+desktop).
     *
     * @return array{
     *     alt: string,
     *     width: int,
     *     height: int,
     *     fallback_url: string,
     *     preloads: list<array{href: string, type: ?string, media: string}>,
     *     sources: list<array{type: string, media: string, srcset: string}>,
     *     has_responsive_variants: bool,
     *     manifest_fingerprint: mixed
     * }|null
     */
    public function presentHero(string $heroUrl, ?array $manifest, string $altText = ''): ?array
    {
        return app(JetpkHeroLcpPresenter::class)->present($heroUrl, $manifest, $altText);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function manifestFromAsset(?ClientPageAsset $asset): ?array
    {
        if ($asset === null) {
            return null;
        }

        $meta = is_array($asset->meta_json) ? $asset->meta_json : [];

        if (is_array($meta['responsive_media'] ?? null)) {
            return $meta['responsive_media'];
        }

        if (($asset->asset_key ?? '') === 'hero_background' && is_array($meta['hero_lcp'] ?? null)) {
            return $meta['hero_lcp'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lookupManifest(string $assetKey, ?int $clientProfileId, string $sourceUrl): ?array
    {
        $path = $this->storageRelativeFromUrl($sourceUrl);
        $query = ClientPageAsset::query()
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', $assetKey);

        if ($clientProfileId !== null) {
            $query->where('client_profile_id', $clientProfileId);
        }

        if ($path !== null) {
            $query->where('path', $path);
        }

        return $this->manifestFromAsset($query->first());
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function buildWidthSrcset(array $manifest, string $format): ?string
    {
        $parts = [];
        $variants = is_array($manifest['variants'] ?? null) ? $manifest['variants'] : [];

        foreach ($variants as $breakpoint => $formats) {
            if (! is_array($formats) || ! is_array($formats[$format] ?? null)) {
                continue;
            }

            $variant = $formats[$format];
            if (! $this->validatedVariantExists($variant)) {
                continue;
            }

            $url = trim((string) ($variant['url'] ?? ''));
            $width = (int) ($variant['width'] ?? 0);
            if ($url === '' || $width < 1) {
                continue;
            }

            $parts[$width] = $url.' '.$width.'w';
        }

        if ($parts === []) {
            return null;
        }

        ksort($parts);

        return implode(', ', array_values($parts));
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function largestIntrinsic(?array $manifest): ?array
    {
        if (! is_array($manifest)) {
            return null;
        }

        $best = null;
        $bestWidth = 0;
        foreach ((array) ($manifest['variants'] ?? []) as $formats) {
            if (! is_array($formats)) {
                continue;
            }
            foreach (['jpg', 'webp', 'avif'] as $format) {
                $variant = $formats[$format] ?? null;
                if (! is_array($variant)) {
                    continue;
                }
                $width = (int) ($variant['width'] ?? 0);
                $height = (int) ($variant['height'] ?? 0);
                if ($width > $bestWidth && $height > 0) {
                    $bestWidth = $width;
                    $best = ['width' => $width, 'height' => $height];
                }
            }
        }

        return $best;
    }

    private function resolveJpegFallback(string $fallbackUrl, ?array $manifest): string
    {
        if (! is_array($manifest)) {
            return $fallbackUrl;
        }

        $bestUrl = null;
        $bestWidth = 0;
        foreach ((array) ($manifest['variants'] ?? []) as $formats) {
            $variant = is_array($formats) ? ($formats['jpg'] ?? null) : null;
            if (! is_array($variant) || ! $this->validatedVariantExists($variant)) {
                continue;
            }
            $width = (int) ($variant['width'] ?? 0);
            if ($width >= $bestWidth) {
                $bestWidth = $width;
                $bestUrl = (string) ($variant['url'] ?? '');
            }
        }

        return $bestUrl !== null && $bestUrl !== '' ? $bestUrl : $fallbackUrl;
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function validatedVariantExists(array $variant): bool
    {
        $path = trim((string) ($variant['path'] ?? ''));
        if ($path === '') {
            $url = trim((string) ($variant['url'] ?? ''));
            $path = $this->storageRelativeFromUrl($url) ?? '';
        }

        if ($path === '') {
            return false;
        }

        return Storage::disk('public')->exists($path);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveManifest(string $sourceUrl, ?array $manifest): ?array
    {
        if (! is_array($manifest) || ! is_array($manifest['variants'] ?? null)) {
            return null;
        }

        $sourcePath = trim((string) ($manifest['source_path'] ?? ''));
        $urlPath = $this->storageRelativeFromUrl($sourceUrl);
        if ($sourcePath !== '' && $urlPath !== null && $sourcePath !== $urlPath) {
            return null;
        }

        return $manifest;
    }

    private function storageRelativeFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = rawurldecode($path);
        if (! str_starts_with($path, '/storage/')) {
            return null;
        }

        return ltrim(substr($path, strlen('/storage/')), '/');
    }
}
