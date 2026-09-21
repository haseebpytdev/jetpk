# AEO / GEO checklist (WP8)

**Status:** Checklist + FAQPage schema landed; citation/content enrichment ongoing  
**Date:** 2026-09-21

## AEO (Answer Engine Optimization)

| Gate | Status | Evidence |
|---|---|---|
| AEO_DIRECT_ANSWER_STRUCTURE | PARTIAL | FAQ + Support topics use Q→A; homepage hero not fake FAQ |
| AEO_VISIBLE_SCHEMA_ALIGNMENT | PARTIAL | `FaqJsonLd` FAQPage mirrors visible Q&A only |
| AEO_FAQ_STRUCTURE | PARTIAL | `/faq` categories + items from CMS |
| AEO_FAQ_ALIGNMENT | PARTIAL | Schema omitted when empty (no spam) |

Remaining: ensure Support page exposes clear factual answers (hours, phone, WhatsApp) as visible text aligned with Organization JSON-LD.

## GEO (Generative Engine Optimization)

| Gate | Status | Evidence |
|---|---|---|
| GEO_ENTITY_CONSISTENCY | PARTIAL | TravelAgency + WebSite from PublicConfig; brand JetPakistan only |
| GEO_CRAWLABILITY | PARTIAL | robots allow public; disallow private; sitemap core paths |
| GEO_CITATION_READY_CONTENT | PARTIAL | About/Support/FAQ/Terms/Privacy are crawlable SSR |
| GEO_FRESHNESS_SIGNALS | PARTIAL | CMS publish → `revalidate-public-content` + sitemap lastmod |
| GEO_AI_CRAWLER_POLICY | PARTIAL | AI bots inherit `User-agent: *` Allow; no explicit bans — see `04-ai-crawler-robots.txt` |

## Explicit non-claims

- No fabricated AggregateRating / review schema
- No fake local business coordinates
- No IndexNow spam beyond authentic publish events
- No GEO manipulation language in public copy

## Next concrete steps

1. Extend `SeoJsonLd` with optional `sameAs` from config social_links (when present)
2. Probe live robots for AI crawler clarity (`docs/closure/SEO-AEO-GEO/04-ai-crawler-robots.md`)
3. After short-ref search URL lands + soft-nav recert, refresh §49 gate table
