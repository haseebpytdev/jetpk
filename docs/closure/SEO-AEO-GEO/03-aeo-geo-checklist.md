# AEO / GEO checklist (WP8)

**Status:** AEO/GEO gates closed for this pass; IndexNow NOT_APPLICABLE  
**Date:** 2026-09-21

## AEO (Answer Engine Optimization)

| Gate | Status | Evidence |
|---|---|---|
| AEO_DIRECT_ANSWER_STRUCTURE | PASS | `/support` shows concise visible contact facts (`ContactDetailsCard` dl) from `SiteContactService` / PublicConfig when phone, email, WhatsApp, office, or hours are configured |
| AEO_VISIBLE_SCHEMA_ALIGNMENT | PASS | `FaqJsonLd` FAQPage mirrors visible Q&A only (`buildFaqPageJsonLd` filters empty); Support facts align with `SeoJsonLd` TravelAgency email/telephone/address from the same contact source |
| AEO_FAQ_STRUCTURE | PASS | `/faq` categories + items from CMS via `FaqPageClient` |
| AEO_FAQ_ALIGNMENT | PASS | Schema omitted when no visible Q&A (no empty FAQ spam) |

## GEO (Generative Engine Optimization)

| Gate | Status | Evidence |
|---|---|---|
| GEO_ENTITY_CONSISTENCY | PASS | `SeoJsonLd` TravelAgency + WebSite from PublicConfig: brand JetPakistan fallback, `app_url`, logo, contact when present; `sameAs` from `social_links` when present |
| GEO_CRAWLABILITY | PASS | `robots.ts` / `public/robots.txt` allow public SEO; disallow private; `sitemap.ts` + Laravel sitemap-routes |
| GEO_CITATION_READY_CONTENT | PASS | About/Support/FAQ/Terms/Privacy are crawlable SSR public pages (see route inventory) |
| GEO_FRESHNESS_SIGNALS | PASS | CMS publish → `NextPublicCacheRevalidator` → revalidate-public-content; sitemap `lastmod` when provided |
| GEO_AI_CRAWLER_POLICY | PASS | Intentional policy: `docs/closure/SEO-AEO-GEO/06-ai-crawler-policy.md`; explicit GPTBot / ClaudeBot / Google-Extended Allow matching `*` without opening private paths |

## IndexNow

| Gate | Status | Evidence |
|---|---|---|
| INDEXNOW | NOT_APPLICABLE | No key/env/key-file pattern; freshness via revalidate + sitemap — `docs/closure/SEO-AEO-GEO/07-indexnow.md` |

## Explicit non-claims

- No fabricated AggregateRating / review schema
- No fake local business coordinates
- No IndexNow spam (and no unimplemented IndexNow client)
- No GEO manipulation language in public copy
- Soft-nav / public cache architecture unchanged in this pass

## Related docs

- `06-ai-crawler-policy.md` — AI discovery vs training stance
- `07-indexnow.md` — IndexNow NOT_APPLICABLE rationale
- `04-ai-crawler-robots.txt` — earlier live probe snapshot (pre–named-bot rules)
