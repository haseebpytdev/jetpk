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
        $pageSeo = is_array($this->contentResolver->contentFor($pageKey)['seo'] ?? null)
            ? $this->contentResolver->contentFor($pageKey)['seo']
            : [];
        $globalSeo = is_array($this->contentResolver->contentFor(ClientPageKeys::GLOBAL)['seo'] ?? null)
            ? $this->contentResolver->contentFor(ClientPageKeys::GLOBAL)['seo']
            : [];

        $title = trim((string) ($pageSeo['title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($globalSeo['title'] ?? ''));
        }
        if ($title === '') {
            $title = $fallbackTitle;
        }

        $description = trim((string) ($pageSeo['description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($globalSeo['description'] ?? ''));
        }
        if ($description === '') {
            $description = $fallbackDescription;
        }

        $ogImage = trim((string) ($pageSeo['og_image'] ?? ''));
        if ($ogImage === '') {
            $ogImage = trim((string) ($globalSeo['og_image'] ?? ''));
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical ?? '',
            'robots' => trim((string) ($pageSeo['robots'] ?? $globalSeo['robots'] ?? 'index,follow')),
            'og_title' => trim((string) ($pageSeo['og_title'] ?? $title)),
            'og_description' => trim((string) ($pageSeo['og_description'] ?? $description)),
            'og_image' => $ogImage !== '' ? $ogImage : null,
        ];
    }
}
