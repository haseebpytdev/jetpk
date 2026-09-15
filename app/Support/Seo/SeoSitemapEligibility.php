<?php

namespace App\Support\Seo;

use App\Models\ClientPage;
use App\Models\CmsPage;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageSeoResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientManagedPageReservedSlugs;

/**
 * Determines whether a public path should appear in the live sitemap feed.
 */
final class SeoSitemapEligibility
{
    /** @var array<string, string> */
    private const MANAGED_PATH_KEYS = [
        '/' => ClientPageKeys::HOME,
        '/about-us' => ClientPageKeys::ABOUT,
        '/support' => ClientPageKeys::SUPPORT,
        '/faq' => ClientPageKeys::FAQ,
        '/terms' => ClientPageKeys::TERMS,
        '/privacy' => ClientPageKeys::PRIVACY,
    ];

    public function __construct(
        private readonly ClientPageSeoResolver $seoResolver,
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    public function isManagedPathEligible(string $path): bool
    {
        $pageKey = self::MANAGED_PATH_KEYS[$path] ?? null;
        if ($pageKey === null) {
            return true;
        }

        $content = $this->contentResolver->contentFor($pageKey);
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];
        if (($seo['sitemap_eligible'] ?? '1') === '0') {
            return false;
        }

        $resolved = $this->seoResolver->forPage($pageKey);

        return SeoRobotsHelper::isIndexable($resolved['robots'] ?? 'index,follow');
    }

    public function isCmsPageEligible(CmsPage $page): bool
    {
        if (! $page->isActive()) {
            return false;
        }

        return $page->robots !== CmsPage::ROBOTS_NOINDEX;
    }

    public function isCustomPageEligible(ClientPage $page): bool
    {
        if (! $page->enabled) {
            return false;
        }

        $pageKey = ClientPageKeys::customKey((string) $page->slug);
        $content = $this->contentResolver->contentFor($pageKey);
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];
        if (($seo['sitemap_eligible'] ?? '1') === '0') {
            return false;
        }

        $resolved = $this->seoResolver->forPage($pageKey, (string) ($page->public_title ?? ''), '');

        return SeoRobotsHelper::isIndexable($resolved['robots'] ?? 'index,follow');
    }
}
