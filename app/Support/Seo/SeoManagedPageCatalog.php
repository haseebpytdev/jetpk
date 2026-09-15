<?php

namespace App\Support\Seo;

use App\Support\Client\ClientPageKeys;

/**
 * Canonical catalog of JetPakistan pages managed through SEO Management.
 */
final class SeoManagedPageCatalog
{
    public const SOURCE_MANAGED = 'managed';

    public const SOURCE_CMS = 'cms';

    public const SOURCE_CUSTOM = 'custom';

    public const SOURCE_PROTECTED = 'protected';

    /** @var array<string, array{label: string, path: string}> */
    public const MANAGED_PAGES = [
        ClientPageKeys::HOME => ['label' => 'Homepage', 'path' => '/'],
        ClientPageKeys::ABOUT => ['label' => 'About us', 'path' => '/about-us'],
        ClientPageKeys::SUPPORT => ['label' => 'Support', 'path' => '/support'],
        ClientPageKeys::FAQ => ['label' => 'FAQ', 'path' => '/faq'],
        ClientPageKeys::TERMS => ['label' => 'Terms of service', 'path' => '/terms'],
        ClientPageKeys::PRIVACY => ['label' => 'Privacy policy', 'path' => '/privacy'],
    ];

    /** @var list<array{path: string, label: string, robots: string, sitemap_eligible: bool, reason: string}> */
    public const PROTECTED_ROUTES = [
        [
            'path' => '/contact',
            'label' => 'Contact redirect',
            'robots' => 'redirect',
            'sitemap_eligible' => false,
            'reason' => 'Permanent redirect to /about-us',
        ],
        [
            'path' => '/flights',
            'label' => 'Flight search',
            'robots' => 'application',
            'sitemap_eligible' => false,
            'reason' => 'Protected application route',
        ],
        [
            'path' => '/lookup-booking',
            'label' => 'Manage booking',
            'robots' => 'noindex,follow',
            'sitemap_eligible' => false,
            'reason' => 'Utility page — noindex,follow',
        ],
        [
            'path' => '/groups/search',
            'label' => 'Group search',
            'robots' => 'noindex,follow',
            'sitemap_eligible' => false,
            'reason' => 'Search utility — noindex,follow',
        ],
        [
            'path' => '/admin',
            'label' => 'Admin portal',
            'robots' => 'noindex,nofollow',
            'sitemap_eligible' => false,
            'reason' => 'Protected admin area',
        ],
        [
            'path' => '/api',
            'label' => 'Public API',
            'robots' => 'noindex,nofollow',
            'sitemap_eligible' => false,
            'reason' => 'Protected API prefix',
        ],
    ];

    /** @return list<string> */
    public static function managedPageKeys(): array
    {
        return array_keys(self::MANAGED_PAGES);
    }

    public static function managedPath(string $pageKey): ?string
    {
        return self::MANAGED_PAGES[$pageKey]['path'] ?? null;
    }

    public static function managedLabel(string $pageKey): string
    {
        return self::MANAGED_PAGES[$pageKey]['label'] ?? $pageKey;
    }

    public static function isManagedPageKey(string $pageKey): bool
    {
        return isset(self::MANAGED_PAGES[$pageKey]);
    }
}
