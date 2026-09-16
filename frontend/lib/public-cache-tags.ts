/**
 * Canonical Next.js ISR cache tags for JetPakistan public content.
 * Keep aligned with App\Support\PublicContent\PublicCacheTags (Laravel).
 */
export const PUBLIC_CACHE_TAGS = {
  homepage: "public-homepage",
  config: "public-config",
  seo: "public-seo",
  cms: "public-cms",
  seoPage: (pageKey: string) => `public-seo-${pageKey.trim()}`,
  cmsSlug: (slug: string) => `public-cms-${slug.trim()}`,
  /** @deprecated Intentional alias — invalidated alongside public-homepage during migration. */
  legacyHomepage: "homepage-cms",
} as const;
