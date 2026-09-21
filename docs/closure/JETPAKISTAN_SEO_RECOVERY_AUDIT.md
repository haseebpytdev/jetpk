# JetPakistan SEO Recovery Audit

**Status:** WP0 complete — classify before redesign  
**Captured:** 2026-09-21  
**Production tip at audit start:** `b45975e36004cf74c9370e71a358fcb38de8ab69` / BUILD `S49jLxNux1bMJZD6Wx_U6`  
**Same-SHA perf:** PASS (soft-nav / traveler / return / pair↔segmented)  
**Authority:** Reuse Laravel SEO Management + Next `publicSeoToMetadata` / `SeoJsonLd` / `robots.ts` / `sitemap.ts`. Do not invent a parallel SEO stack. Do not touch soft-nav / `unstable_cache` / route-groups.

Classification vocabulary (prompt §22): **RECOVERED** | **PARTIAL** | **MISSING** | **REGRESSED** | **OBSOLETE**

---

## 1. Historical SEO stack (KEEP)

| Capability | Location | Class | Notes |
|---|---|---|---|
| SEO Management admin (Phase 2) | `SeoManagementService`, Client Page SEO | RECOVERED | CMS SEO fields authoritative for managed pages |
| Managed page catalog | `SeoManagedPageCatalog` | RECOVERED | home, about-us, support, faq, terms, privacy |
| Canonical validator | `SeoCanonicalValidator` | RECOVERED | same-site host policy |
| Sitemap eligibility | `SeoSitemapEligibility` | RECOVERED | filters robots / empty content |
| Laravel sitemap inventory | `PublicContentApiPresenter::sitemapRoutes()` | RECOVERED | managed + CMS `/pages/{slug}` + custom `/{slug}` |
| Next sitemap consumer | `frontend/app/sitemap.ts` | RECOVERED | excludes `/contact`, `/flights`, utilities |
| Next robots | `frontend/app/robots.ts` | PARTIAL | production disallow list stronger than Laravel `public/robots.txt` |
| Laravel `public/robots.txt` | root `public/robots.txt` | PARTIAL | thinner disallow set; dual-source drift risk |
| Metadata mapper | `publicSeoToMetadata` (`absolute` titles) | RECOVERED | prevents `| Brand` double-suffix when used |
| Global JSON-LD | `SeoJsonLd` TravelAgency + WebSite | RECOVERED | accurate org/site; no fake ratings |
| FAQPage JSON-LD | — | MISSING | visible FAQ exists; schema not emitted |
| Contact page component | `app/(public)/contact/page.tsx` | OBSOLETE (runtime) | unreachable via permanent redirect; KEEP redirect policy |
| `/contact` → `/about-us` 308 | `next.config.ts` + catalog PROTECTED | RECOVERED | intentional alias; **not** a metadata bug |
| HTTPS / www policy | prior SEO Phase 1 | RECOVERED | phase1 probe shows HTTP→HTTPS 308 |
| Search Console hook | prior commits | RECOVERED | verification hook present historically |
| Short URL mint/resolve | — | MISSING | no `PublicShareLink` / short-ref service in repo |
| AEO / GEO packs | — | MISSING | gates not yet evidenced under `docs/closure/SEO-AEO-GEO/` |

---

## 2. `/contact` policy decision (WP0)

**Decision: KEEP permanent redirect `/contact` → `/about-us`.**

Evidence:

- `SeoManagedPageCatalog::PROTECTED_ROUTES` documents redirect; `sitemap_eligible: false`
- `next.config.ts` permanent redirect
- Laravel `sitemapRoutes()` deliberately omits `/contact`
- Next `EXCLUDED_SITEMAP_PATHS` includes `/contact`
- SEO Playwright expectations treat contact as non-indexable alias

Sep-15 `seo-phase1` “contact title/canonical = about-us” is **expected redirect behavior**, not REGRESSED metadata.

Do **not** restore a second indexable contact URL without a product decision that also updates catalog, sitemap, robots, nav, and tests together.

Contact form UX remains on `/support` (and about-us content as applicable).

---

## 3. Public route inventory summary

Full table: `docs/closure/SEO-AEO-GEO/01-route-inventory.md`.

| Class | Indexable | Examples |
|---|---|---|
| A — Public SEO | yes | `/`, `/about-us`, `/support`, `/faq`, `/terms`, `/privacy`, `/sitemap`, `/pages/*`, custom `/{slug}` |
| B — Transactional / app | no | `/flights/*`, `/booking/*`, `/lookup-booking`, `/groups/search`, group booking flows, dashboards |
| C — Share / token | no | `/guest/bookings/{id}/access/{token}` (legacy long token; short share MISSING) |

Redirect aliases (not sitemap): `/contact` → `/about-us`; `/flights` → `/`.

---

## 4. Known gaps (remediation targets)

| ID | Gap | Class | Package |
|---|---|---|---|
| G1 | `/lookup-booking`, `/groups/search` missing canonical + OG (title/robots OK) | PARTIAL | WP2 |
| G2 | HTML `/sitemap` missing canonical + OG (`absolute` title) | PARTIAL | WP2 |
| G3 | `/groups` landing has no `generateMetadata` | PARTIAL | WP2 |
| G4 | Title template risk on pages not using `publicSeoToMetadata` | PARTIAL | WP2 |
| G5 | FAQPage JSON-LD absent | MISSING | WP5 |
| G6 | Laravel vs Next robots disallow parity | PARTIAL | WP4 |
| G7 | Sitemap still uses `fetch(... next.revalidate)` (not CMS page cache path) | PARTIAL | WP4 (align later; not soft-nav) |
| G8 | Short SEO aliases (`/about` vs `/about-us`) per prompt §30 | MISSING | WP7 |
| G9 | Opaque search/booking short-refs (`/flights/s/{ref}`, `/b/{ref}`) | MISSING | WP7 |
| G10 | Images webp=0 (all PNG in Sep-15 crawl) | PARTIAL | WP6 progressive |
| G11 | AEO answer structure / GEO entity+crawler policy | MISSING | WP8 |
| G12 | Dual sitemap (Next + Laravel XML) drift | PARTIAL | WP4 monitor |

---

## 5. Do not touch

- Persistent public CMS `unstable_cache` / revalidate-public-content architecture
- Soft-nav route-groups, force-static churn, shared-shell, Suspense, speculative prefetch
- Homepage / search shell redesign
- Supplier / payment mutations for SEO probes
- Secrets in docs or evidence

---

## 6. Remediation order

1. **WP0** this audit — DONE  
2. **WP1** route inventory — DONE (`01-route-inventory.md`)  
3. **WP2–WP5** metadata + FAQ JSON-LD + robots parity (first code package)  
4. **WP6** technical quality (webp progressive; heading/alt already strong)  
5. **WP7** three-class short URLs + legacy 301s  
6. **WP8** AEO then GEO  
7. **WP9** §49 gates + evidence; then same-SHA perf recert only if public redirects/layout change  

Deploy of SEO package is allowed after WP2–WP5 land; soft-nav/perf recert only if redirects or public shell change.

---

## 7. Gate snapshot (pre-remediation)

```
SEO_FULL_PROJECT_AUDIT=PASS (this document)
SEO_METADATA=PARTIAL
SEO_CANONICALS=PARTIAL
SEO_ROBOTS=PARTIAL
SEO_SITEMAP=PASS (core managed paths; short-URL finalization pending WP7)
SEO_STRUCTURED_DATA=PARTIAL (org/site OK; FAQPage MISSING)
SEO_INDEXABILITY=PARTIAL
SEO_DUPLICATE_INDEXABLE_URLS=0 (contact kept non-indexable)
SEO_LEGACY_REDIRECTS=PARTIAL (contact OK; short aliases MISSING)
AEO_*=MISSING
GEO_*=MISSING
```
