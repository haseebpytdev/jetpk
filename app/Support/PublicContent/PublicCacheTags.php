<?php

namespace App\Support\PublicContent;

/**
 * Canonical Next.js ISR cache tags for JetPakistan public content.
 *
 * Laravel publishers and the Next revalidation webhook must stay aligned with
 * the tags declared on fetch() in the public frontend services.
 */
final class PublicCacheTags
{
    public const HOMEPAGE = 'public-homepage';

    public const CONFIG = 'public-config';

    public const SEO = 'public-seo';

    public const CMS = 'public-cms';

    public static function seoPage(string $pageKey): string
    {
        return 'public-seo-'.trim($pageKey);
    }

    public static function cmsSlug(string $slug): string
    {
        return 'public-cms-'.trim($slug);
    }
}
