<?php

namespace App\Support\PublicContent;

/**
 * Canonical Next.js public-content cache tags for JetPakistan.
 *
 * Persistent cross-request authority uses jp-public-* tags via Next unstable_cache.
 * Legacy public-* tags remain for dual-invalidation during cutover.
 * Keep aligned with frontend/lib/public-cache-tags.ts.
 */
final class PublicCacheTags
{
    public const CONTENT = 'jp-public-content';

    public const CONFIG = 'jp-public-config';

    /** @deprecated Prefer CONFIG — legacy ISR tag. */
    public const LEGACY_CONFIG = 'public-config';

    public const HOMEPAGE = 'public-homepage';

    public const SEO = 'public-seo';

    public const CMS = 'public-cms';

    public static function page(string $pageKey): string
    {
        return 'jp-public-page-'.trim($pageKey);
    }

    public static function seoPage(string $pageKey): string
    {
        return 'public-seo-'.trim($pageKey);
    }

    public static function cmsSlug(string $slug): string
    {
        return 'public-cms-'.trim($slug);
    }
}
