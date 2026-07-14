<?php

namespace App\Support\Homepage;

use App\Models\ClientPageAsset;
use App\Support\Client\ClientPageKeys;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves validated LCP hero picture sources from the CMS hero manifest.
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

  /** @var list<string> */
    private const MODERN_FORMATS = ['avif', 'webp'];

    public function present(?string $heroUrl, ?array $manifest = null, string $altText = ''): ?array
    {
        $heroUrl = trim((string) $heroUrl);
        if ($heroUrl === '') {
            return null;
        }

        $manifest = $this->resolveManifest($heroUrl, $manifest);
        $intrinsic = $this->intrinsicFromUrl($heroUrl);
        $sources = [];

        if ($manifest !== null) {
            foreach (self::BREAKPOINTS as $breakpoint) {
                $meta = self::BREAKPOINT_META[$breakpoint];
                $breakpointVariants = is_array($manifest['variants'][$breakpoint] ?? null)
                    ? $manifest['variants'][$breakpoint]
                    : [];

                foreach (self::MODERN_FORMATS as $format) {
                    $variant = $breakpointVariants[$format] ?? null;
                    if (! is_array($variant)) {
                        continue;
                    }

                    $url = trim((string) ($variant['url'] ?? ''));
                    if ($url === '' || ! $this->validatedVariantExists($variant)) {
                        continue;
                    }

                    $sources[] = [
                        'type' => 'image/'.$format,
                        'media' => $meta['media'],
                        'srcset' => $url,
                    ];
                }
            }
        }

        $fallbackUrl = $this->resolveFallbackUrl($heroUrl, $manifest);
        $fallbackIntrinsic = $this->intrinsicFromUrl($fallbackUrl) ?? $intrinsic ?? ['width' => 1200, 'height' => 600];

        return [
            'alt' => $altText !== '' ? $altText : 'JetPakistan flights from Pakistan',
            'width' => $fallbackIntrinsic['width'],
            'height' => $fallbackIntrinsic['height'],
            'fallback_url' => $fallbackUrl,
            'preloads' => $this->resolvePreloads($sources, $manifest, $fallbackUrl),
            'sources' => $sources,
            'has_responsive_variants' => $sources !== [],
            'manifest_fingerprint' => is_array($manifest) ? ($manifest['fingerprint'] ?? null) : null,
        ];
    }

    public function manifestForHeroUrl(string $heroUrl, ?int $clientProfileId = null): ?array
    {
        return $this->resolveManifest($heroUrl, $this->lookupManifestFromAsset($heroUrl, $clientProfileId));
    }

    /**
     * @param  list<array{type: string, media: string, srcset: string}>  $sources
     * @return list<array{href: string, type: ?string, media: string}>
     */
    private function resolvePreloads(array $sources, ?array $manifest, string $fallbackUrl): array
    {
        $preloads = [];

        foreach ([
            '(min-width: 1025px)' => ['desktop', ['avif', 'webp', 'jpg']],
            '(max-width: 768px)' => ['mobile', ['avif', 'webp', 'jpg']],
        ] as $media => [$breakpoint, $formats]) {
            $url = $this->pickBreakpointUrl($sources, $manifest, $breakpoint, $formats);
            if ($url !== null) {
                $preloads[] = [
                    'href' => $url,
                    'type' => $this->mimeFromUrl($url),
                    'media' => $media,
                ];
            }
        }

        if ($preloads === []) {
            $preloads[] = [
                'href' => $fallbackUrl,
                'type' => $this->mimeFromUrl($fallbackUrl),
                'media' => '(min-width: 1px)',
            ];
        }

        return $preloads;
    }

    /**
     * @param  list<array{type: string, media: string, srcset: string}>  $sources
     * @param  list<string>  $formats
     */
    private function pickBreakpointUrl(array $sources, ?array $manifest, string $breakpoint, array $formats): ?string
    {
        foreach ($formats as $format) {
            foreach ($sources as $source) {
                if (! str_contains($source['srcset'], 'hero-'.$breakpoint.'.'.$format)) {
                    continue;
                }

                if ($this->breakpointMediaMatches($breakpoint, $source['media'])) {
                    return $source['srcset'];
                }
            }
        }

        if (! is_array($manifest)) {
            return null;
        }

        $breakpointVariants = is_array($manifest['variants'][$breakpoint] ?? null)
            ? $manifest['variants'][$breakpoint]
            : [];

        foreach ($formats as $format) {
            $variant = $breakpointVariants[$format] ?? null;
            if (is_array($variant) && $this->validatedVariantExists($variant)) {
                return (string) ($variant['url'] ?? '');
            }
        }

        return null;
    }

    private function breakpointMediaMatches(string $breakpoint, string $media): bool
    {
        return ($breakpoint === 'desktop' && str_contains($media, 'min-width: 1025px'))
            || ($breakpoint === 'tablet' && str_contains($media, '769px'))
            || ($breakpoint === 'mobile' && str_contains($media, 'max-width: 768px'));
    }

    private function resolveFallbackUrl(string $heroUrl, ?array $manifest): string
    {
        if (is_array($manifest)) {
            foreach (['desktop', 'tablet', 'mobile'] as $breakpoint) {
                $variant = $manifest['variants'][$breakpoint]['jpg'] ?? null;
                if (is_array($variant) && $this->validatedVariantExists($variant)) {
                    return (string) $variant['url'];
                }
            }
        }

        return $heroUrl;
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
    private function resolveManifest(string $heroUrl, ?array $manifest): ?array
    {
        if (! is_array($manifest) || ! is_array($manifest['variants'] ?? null)) {
            return null;
        }

        $sourcePath = trim((string) ($manifest['source_path'] ?? ''));
        $heroPath = $this->storageRelativeFromUrl($heroUrl);
        if ($sourcePath !== '' && $heroPath !== null && $sourcePath !== $heroPath) {
            return null;
        }

        return $manifest;
    }

    private function lookupManifestFromAsset(string $heroUrl, ?int $clientProfileId): ?array
    {
        $heroPath = $this->storageRelativeFromUrl($heroUrl);
        if ($heroPath === null) {
            return null;
        }

        $query = ClientPageAsset::query()
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', 'hero_background')
            ->where('path', $heroPath);

        if ($clientProfileId !== null) {
            $query->where('client_profile_id', $clientProfileId);
        }

        $asset = $query->first();
        $manifest = $asset?->meta_json['hero_lcp'] ?? null;

        return is_array($manifest) ? $manifest : null;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function intrinsicFromUrl(string $url): ?array
    {
        $path = $this->absolutePathFromUrl($url);
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);
        if (! is_array($size) || ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1) {
            return null;
        }

        return ['width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    private function mimeFromUrl(string $url): ?string
    {
        $lower = strtolower($url);
        if (str_ends_with($lower, '.webp')) {
            return 'image/webp';
        }
        if (str_ends_with($lower, '.avif')) {
            return 'image/avif';
        }
        if (str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg')) {
            return 'image/jpeg';
        }

        return null;
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

    private function absolutePathFromUrl(string $url): ?string
    {
        $relative = $this->storageRelativeFromUrl($url);
        if ($relative !== null && Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->path($relative);
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $publicPath = public_path(ltrim(rawurldecode($path), '/'));

        return is_file($publicPath) ? $publicPath : null;
    }
}
