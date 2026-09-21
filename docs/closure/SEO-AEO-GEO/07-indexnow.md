# IndexNow

**Status:** NOT_APPLICABLE  
**Date:** 2026-09-21

## Search result

No IndexNow client, key file route, or `INDEXNOW_*` / Bing IndexNow env pattern exists in this repository (Laravel `app/Services/Seo/*`, Next revalidate routes, `.env.example`, or config).

## Why not implemented now

IndexNow requires:

1. A site-owned API key
2. A publicly hosted key verification file (`https://jetpakistan.pk/{key}.txt` or equivalent)
3. Authenticated notify calls on URL change

None of those credentials or hosting patterns are present. Inventing a key or wiring a silent no-op client would not improve freshness and could imply a live integration that is not configured.

## Freshness already covered

| Mechanism | Role |
|---|---|
| CMS / SEO publish → `NextPublicCacheRevalidator` | On-demand Next cache revalidation for managed public paths |
| `/api/internal/revalidate-public-content` (+ legacy SEO route) | Allowlisted invalidation; does not mutate soft-nav architecture |
| `frontend/app/sitemap.ts` + Laravel `sitemap-routes` | Crawl discovery with optional `lastmod` |
| ISR `revalidate` on public pages | Bounded stale-while-revalidate for public CMS |

These cover authentic publish → live public content without IndexNow.

## When IndexNow would become applicable

Implement a **minimal** notify (indexable paths only) next to `NextPublicCacheRevalidator` only when:

- `INDEXNOW_KEY` (or equivalent) is provisioned in env/config
- Key file is served on the canonical host
- Notify is limited to sitemap-eligible public URLs (never `/admin`, booking, or private portals)

Until then, this gate remains **NOT_APPLICABLE** (not a failure).
