/**
 * Canonical Next.js public-content cache tags for JetPakistan.
 * Keep aligned with App\Support\PublicContent\PublicCacheTags (Laravel).
 *
 * Persistent cross-request authority: unstable_cache (jp-public-*).
 * Legacy public-seo / public-config tags remain for dual-invalidation cutover.
 */
export const PUBLIC_CACHE_TAGS = {
  /** Root tag — invalidate all persistent public content. */
  content: "jp-public-content",
  /** Public site config (branding, verification, paths). */
  config: "jp-public-config",
  /** Per managed CMS page key (about, faq, terms, privacy, support, …). */
  page: (pageKey: string) => `jp-public-page-${pageKey.trim()}`,

  // Legacy ISR tags (still invalidated by SEO webhook during cutover)
  homepage: "public-homepage",
  legacyConfig: "public-config",
  seo: "public-seo",
  cms: "public-cms",
  seoPage: (pageKey: string) => `public-seo-${pageKey.trim()}`,
  cmsSlug: (slug: string) => `public-cms-${slug.trim()}`,
  /** @deprecated Intentional alias — invalidated alongside public-homepage during migration. */
  legacyHomepage: "homepage-cms",
} as const;

/** Allowlisted page keys for managed CMS + revalidation. */
export const PUBLIC_MANAGED_PAGE_KEYS = [
  "home",
  "about",
  "faq",
  "terms",
  "privacy",
  "support",
] as const;

export type PublicManagedPageKey = (typeof PUBLIC_MANAGED_PAGE_KEYS)[number];

/** Allowlisted public paths for on-demand revalidation. */
export const PUBLIC_REVALIDATE_PATH_ALLOWLIST = [
  "/",
  "/about-us",
  "/faq",
  "/terms",
  "/privacy",
  "/support",
  "/contact",
  "/sitemap.xml",
] as const;

export const PUBLIC_CONTENT_CACHE_TTL_SECONDS = 3600;
