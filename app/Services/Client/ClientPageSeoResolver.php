<?php

namespace App\Services\Client;

use App\Support\Client\ClientPageKeys;

/**
 * Resolves SEO metadata for managed public pages from CMS with global defaults.
 */
final class ClientPageSeoResolver
{
    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    /**
     * @return array{title: string, description: string, canonical: string, robots: string, og_title: string, og_description: string, og_image: ?string}
     */
    public function forPage(string $pageKey, string $fallbackTitle = '', string $fallbackDescription = '', ?string $canonical = null): array
    {
        $pageContent = $this->contentResolver->contentFor($pageKey);
        $pageSeo = is_array($pageContent['seo'] ?? null) ? $pageContent['seo'] : [];
        $globalContent = $this->contentResolver->contentFor(ClientPageKeys::GLOBAL);
        $globalSeo = is_array($globalContent['seo'] ?? null) ? $globalContent['seo'] : [];

        $pageTitle = trim((string) ($pageSeo['title'] ?? ''));
        $globalTitle = trim((string) ($globalSeo['title'] ?? ''));
        $title = $pageTitle !== '' ? $pageTitle : ($globalTitle !== '' ? $globalTitle : $fallbackTitle);
        if ($pageTitle === '' && $globalTitle === '' && $title !== '') {
            $suffix = trim((string) ($globalSeo['title_suffix'] ?? ''));
            if ($suffix !== '' && ! str_contains($title, $suffix)) {
                $title = rtrim($title).' | '.$suffix;
            }
        }

        $pageDescription = trim((string) ($pageSeo['description'] ?? ''));
        $description = $pageDescription !== ''
            ? $pageDescription
            : (trim((string) ($globalSeo['description'] ?? '')) ?: $fallbackDescription);

        $pageOgImage = trim((string) ($pageSeo['og_image'] ?? ''));
        $globalOgImage = trim((string) ($globalSeo['og_image'] ?? ''));
        $ogImage = $pageOgImage !== '' ? $pageOgImage : $globalOgImage;

        $pageOgTitle = trim((string) ($pageSeo['og_title'] ?? ''));
        $globalOgTitle = trim((string) ($globalSeo['og_title'] ?? ''));
        $ogTitle = $pageOgTitle !== '' ? $pageOgTitle : ($globalOgTitle !== '' ? $globalOgTitle : $title);

        $pageOgDescription = trim((string) ($pageSeo['og_description'] ?? ''));
        $globalOgDescription = trim((string) ($globalSeo['og_description'] ?? ''));
        $ogDescription = $pageOgDescription !== ''
            ? $pageOgDescription
            : ($globalOgDescription !== '' ? $globalOgDescription : $description);

        $resolvedCanonical = trim((string) ($pageSeo['canonical'] ?? ''));
        if ($resolvedCanonical === '' && $canonical !== null) {
            $resolvedCanonical = trim($canonical);
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $resolvedCanonical,
            'robots' => trim((string) ($pageSeo['robots'] ?? $globalSeo['robots'] ?? 'index,follow')),
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image' => $ogImage !== '' ? $ogImage : null,
        ];
    }
}
