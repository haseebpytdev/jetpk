# CMS CONNECTION MATRIX — JetPakistan Golden Frontend Recovery

**Certification date:** 2026-09-16  
**Production probed:** https://jetpakistan.pk (runtime `27be8ece`, pre-golden-deploy)  
**Local recovery HEAD:** `6f0412bd` (+ closure UI/favicon WIP; not yet deployed)  
**Gate status:** **LOCAL CMS CODE PASS** — production still on `27be8ece` until exact-SHA deploy  
**Local PHPUnit (6f0412bd):** JetpkHomepageMediaAuthorityTest 4/4, JetpkHomepageRouteMediaTest 5/5, NextPublicCacheRevalidatorTest 4/4

---

## Executive summary

| Gate | Status | Blocker |
|------|--------|---------|
| CMS connection matrix | **PASS** (documented) | — |
| CMS publish/restore proof | **NOT EXECUTED** | Requires live admin draft/publish cycle (QA creds available; deferred this session) |
| CMS revalidation | **PARTIAL / FAIL** | `ClientPageSettingsController::publish` does not call `NextPublicCacheRevalidator`; `homepage-cms` tag not in webhook |
| Production fixture safety | **PASS** | `NODE_ENV` set on server; no `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` / `OTA_ALLOW_CONTENT_FIXTURE` found |
| Homepage media (4 routes) | **FAIL** | KHI→RUH renders **no image** despite CMS asset existing on server |
| Featured deal binding | **PARTIAL** | Index-based static fallbacks; CMS deal images not bound per item |
| Public page matrix | **PASS** (HTTP) | All probed routes 200; font 404 remains |
| SEO matrix | **PASS** (sampled) | Title/description/canonical present on `/` and `/about-us` |
| Asset/network matrix | **FAIL** | `ClashDisplay-Bold.woff2` 404 |

**JETPAKISTAN CMS / MEDIA / PUBLIC PAGE CERTIFICATION — NOT PASS**

---

## 1. CMS CONNECTION MATRIX

| PAGE / SURFACE | ADMIN SOURCE | LARAVEL PUBLIC ENDPOINT | NEXT SERVICE / ADAPTER | EXPECTED CMS FIELDS | ACTUAL SOURCE | CACHE / REVALIDATION TAG | SEO SOURCE | MEDIA SOURCE | PASS / FAIL |
|----------------|--------------|---------------------------|------------------------|---------------------|---------------|---------------------------|------------|--------------|-------------|
| **Homepage** (/) | `/admin/page-settings/home` (`ClientPageSettingsController`) | `GET /api/public/content/homepage` | `HomepageContentService.getHomepage()` → `homepage-content-service.ts` | hero, trust_chips, routes, destinations, featured_deals, why_book, support_cta, feature_board | **cms** (`source` in API) | `homepage-cms` (120s ISR); **not** webhook-invalidated | `PublicConfig.default_seo` via `/config` (not homepage `seo` field) | Hero: CMS storage; routes: static fallback map; destinations: CMS storage | **PARTIAL** |
| **Homepage — Hero** | home publish + asset `hero_background` | homepage.hero | `mapHero()` → `PublicHero` | image.url, headline, subtitle | **cms** | `homepage-cms` 120s | global default_seo | CMS `hero_background-*.png` | **PASS** |
| **Homepage — Trust chips** | home settings | homepage.trust_chips | `mapTrustChips()` | label | **cms** | `homepage-cms` 120s | — | — | **PASS** |
| **Homepage — Routes** | home settings + route assets | homepage.routes.items | `mapRoutes()` → `RoutesSection` + `resolveRouteMedia()` | from, to, price, image_asset_key | **cms** text/prices; **static** images (LHE-DXB/JED/LHR); **empty** KHI-RUH | `homepage-cms` 120s | — | `homepage-media.ts` fallbacks; route CMS assets **not** wired in API | **FAIL** |
| **Homepage — Destinations** | home settings + destination assets | homepage.destinations.items | `mapDestinations()` → `DestinationsSection` | code, title, image | **cms** | `homepage-cms` 120s | — | CMS `destination_seed_*.png` | **PASS** |
| **Homepage — Featured Deals** | home + `HomepageFeaturedFareController` | homepage.featured_deals.items | `mapFeaturedDeals()` → `FeaturedOffersSection` + `resolveOfferMedia()` | airline, from, to, price | **cms** text/prices | `homepage-cms` 120s | — | Index fallbacks `offer-gcc/uk/domestic.jpg` | **PARTIAL** |
| **Homepage — Why JetPakistan** | home settings | homepage.why_book | `mapWhyBook()` | cards | **cms** | `homepage-cms` 120s | — | — | **PASS** |
| **Homepage — Support CTA** | home settings + `support_cta_background` | homepage.support_cta | `mapSupportCta()` | headline, image | **cms** | `homepage-cms` 120s | — | CMS storage asset | **PASS** |
| **Homepage — Feature board** | home settings | homepage.feature_board | `mapFeatureBoard()` | items | **cms** | `homepage-cms` 120s | — | — | **PASS** |
| **About** (`/about-us`) | `/admin/page-settings/about` | `GET /api/public/content/pages/about` | `PublicPageService.getAboutPage()` | hero, contact, seo | **cms** | `public-seo`, `public-seo-about` (60s) | page `content.seo` + global fallback | — | **PASS** |
| **Support** (`/support`) | `/admin/page-settings/support` | `GET /api/public/content/pages/support` | `SupportContentService` | hero, categories ref | **cms** | `public-seo-support` (60s) | page seo | — | **PASS** |
| **FAQ** (`/faq`) | `/admin/page-settings/faq` | `GET /api/public/content/pages/faq` | `FaqService` | faq items | **cms** | `public-seo-faq` (60s) | page seo | — | **PASS** |
| **Terms** (`/terms`) | `/admin/page-settings/terms` | `GET /api/public/content/pages/terms` | `LegalPageService.getTerms()` | legal body, seo | **cms** | `public-seo-terms` (60s) | page seo | — | **PASS** |
| **Privacy** (`/privacy`) | `/admin/page-settings/privacy` | `GET /api/public/content/pages/privacy` | `LegalPageService.getPrivacy()` | legal body, seo | **cms** | `public-seo-privacy` (60s) | page seo | — | **PASS** |
| **Custom CMS pages** (`/pages/{slug}`) | `/admin/cms-pages/*` | `GET /api/public/content/cms/{slug}` | `CmsPageService` | content, seo_* | **cms** | `public-cms`, `public-cms-{slug}` | CmsPage seo fields | featured_image (model; limited in API) | **PASS** (when published) |
| **Global public config** | `/admin/page-settings/global` + `/admin/seo/*` | `GET /api/public/content/config` | `PublicConfigService.getConfig()` | brand, contact, paths, default_seo, site_verification | **cms** | `public-config` (60s) | `default_seo`, `site_verification` | logo via branding resolver | **PASS** |
| **Site contact** | global settings | `GET /api/public/content/site-contact` | `site-contact-service.ts` / `laravel-api.ts` | phone, email, whatsapp, office, hours | **cms** | 300s (no tag) | — | — | **PASS** |
| **Header/footer globals** | global + navigation contract | `/config` + session bootstrap | `SiteHeader`, `SiteFooter`, `navigation.ts` | brand_name, social_links, footer columns | **cms** + static IA | `public-config` | — | logo from CMS/branding | **PASS** |
| **Social links** | global / SEO social admin | `/config`.social_links | `SiteFooter` | label, href | **cms** | `public-config` | — | — | **PASS** |
| **Default SEO** | `/admin/seo/global` | `/config`.default_seo | `generateMetadata` on `/` | title, description, robots | **cms** | `public-config` + `public-seo` | authoritative for homepage metadata | — | **PASS** |
| **Site verification** | `/admin/seo/verification` | `/config`.site_verification | `SiteVerificationMeta`, `(public)/layout` | google, bing | **cms** (empty on prod) | `public-config` | meta tags | — | **PASS** (empty values OK) |

---

## 2. CMS PUBLISH / RESTORE TEST RESULT

| Step | Surface | Status | Evidence |
|------|---------|--------|----------|
| 1–10 Admin draft → publish → restore | Homepage text, image, About, FAQ, Support, SEO field | **NOT EXECUTED** | QA admin credential available (`jp-dash-03-qa-admin@jetpakistan.pk`); live mutation deferred to avoid unattended prod CMS edits this session |

**Required before deploy:** Execute full 10-step cycle on staging or production QA window with identifiable marker (e.g. `JP-CMS-CERT-20260916`) and capture API + rendered diff.

---

## 3. CMS REVALIDATION RESULT

| Tag / path | Declared in Next fetch | Invalidated by SEO publish webhook | Invalidated by page-settings publish | Status |
|------------|------------------------|-----------------------------------|--------------------------------------|--------|
| `public-homepage` / `homepage-cms` | `homepage-cms` (120s ISR) | **No** | **No** | **FAIL** — relies on TTL only |
| `public-cms` | yes | via `cms_slugs` | **No** | **PARTIAL** |
| `public-cms-{slug}` | yes | via `cms_slugs` | **No** | **PARTIAL** |
| `public-seo` | yes | yes | **No** (SEO publish only) | **PARTIAL** |
| `public-seo-{page}` | yes | yes (SEO admin) | **No** | **PARTIAL** |
| `public-config` | yes | `global: true` (SEO global publish) | **No** | **PARTIAL** |

**Finding:** Content publish at `/admin/page-settings/{key}/publish` updates Laravel DB immediately but does **not** call `NextPublicCacheRevalidator`. Homepage can remain stale up to **120s** without SEO-side revalidation or server restart.

**Recommendation:** Wire `ClientPageSettingsController::publish` → `NextPublicCacheRevalidator` (include `homepage-cms` tag in webhook contract).

---

## 4. PRODUCTION FIXTURE SAFETY

| Check | Result |
|-------|--------|
| `NODE_ENV=production` on server | **Present** (var name confirmed; value redacted) |
| `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` | **Not set** on probed server env files |
| `OTA_ALLOW_CONTENT_FIXTURE` | **Not set** |
| Production homepage hero | CMS storage URL (not fixture SVG/gradient) |
| Production about body | CMS copy (not fixture placeholder) |
| `allowContentFixtures()` in production build | **false** per `content-policy-core.mjs` |

**PASS** — no evidence of silent fixture substitution on probed surfaces.

---

## 5. HOMEPAGE MEDIA MATRIX

| Asset | CMS / reference | Resolved URL (production) | HTTP | naturalWidth×H | Fallback? | PASS |
|-------|-----------------|---------------------------|------|----------------|-----------|------|
| Hero | CMS `hero_background` | `/storage/.../hero_background-20260913112237.png` | 200 | 1672×941 | no | **PASS** |
| Route LHE→DXB | `route_seed-khi-dxb` (CMS key; API no image) | `/_next/image?.../destination-dubai.jpg` | 200 | 450×225 | yes (static) | **PASS** |
| Route LHE→JED | `route_seed-lhe-jed` | `destination-jeddah.jpg` | 200 | 450×225 | yes (static) | **PASS** |
| Route ISB→LHR | `route_seed_isb_lhr` | `destination-london.jpg` | 200 | 450×225 | yes (static) | **PASS** |
| Route **KHI→RUH** | `route_seed_khi_ruh` | **null / no img element** | — | 0×0 | broken | **FAIL** |
| Dest DXB | CMS `destination_seed_dxb` | storage PNG | 200 | 1448×1086 | no | **PASS** |
| Dest JED | CMS | storage PNG | 200 | 1448×1086 | no | **PASS** |
| Dest LHR | CMS | storage PNG | 200 | 1448×1086 | no | **PASS** |
| Dest IST | CMS | storage PNG | 200 | 1448×1086 | no | **PASS** |
| Deal 1 (Air Arabia ISB-DXB) | CMS text | `offer-gcc.jpg` | 200 | 480×178 | yes (index 0) | **PARTIAL** |
| Deal 2 (AirBlue LHE-IST) | CMS text | `offer-uk.jpg` | 200 | 480×178 | yes (index 1) | **PARTIAL** |
| Deal 3 (AirSial ISB-JED) | CMS text | `offer-domestic.jpg` | 200 | 480×178 | yes (index 2) | **PARTIAL** |
| Support CTA bg | CMS `support_cta_background` | storage PNG | 200 | 2172×724 | no | **PASS** |
| Logo | branding | `/client-assets/jetpk/logo/logo.png` | 200 | 2172×724 | no | **PASS** |

**Unexpected placeholders on homepage:** **1** (KHI→RUH route card — missing image)

---

## 6. ROUTE IMAGE MATRIX (four approved routes)

| Route | Expected reference | CMS `image_asset_key` | CMS asset on server | Public asset URL | Frontend rendered | Visual match | PASS |
|-------|-------------------|----------------------|---------------------|------------------|-------------------|--------------|------|
| LHE → DXB | `destination-dubai.jpg` / CMS route asset | `route_seed-khi-dxb` | exists | partial | `destination-dubai.jpg` | approved static | **PASS** |
| LHE → JED | `destination-jeddah.jpg` | `route_seed-lhe-jed` | exists | partial | `destination-jeddah.jpg` | approved static | **PASS** |
| ISB → LHR | `destination-london.jpg` | `route_seed_isb_lhr` | exists | partial | `destination-london.jpg` | approved static | **PASS** |
| **KHI → RUH** | **Dedicated Riyadh/GCC route image** (not generic offer card) | `route_seed_khi_ruh` | **exists** `route_seed_khi_ruh-20260913115311.png` (HTTP 200) | **200** at storage URL | **no image rendered** | **no** | **FAIL** |

**Root cause (KHI→RUH):** `JetpkHomepageSectionData::routesForDisplay()` does not resolve route images (unlike destinations). Frontend `resolveRouteMedia()` has no `ruh`/`riyadh` key — returns empty `image: ""`. Approved CMS asset exists but is **not connected** to the render pipeline.

**Do not accept `offer-gcc.jpg` as KHI→RUH substitute** — production currently shows **no image**, not offer-gcc, on the route card.

---

## 7. FEATURED DEAL IMAGE MATRIX

| Index | CMS airline/route | Resolved image | Same as route card? | Intended per-deal CMS asset? | PASS |
|-------|-------------------|----------------|---------------------|------------------------------|------|
| 0 | Air Arabia ISB-DXB | `offer-gcc.jpg` | overlaps GCC visual language | no — index fallback | **PARTIAL** |
| 1 | AirBlue LHE-IST | `offer-uk.jpg` | no | no — index fallback | **PARTIAL** |
| 2 | AirSial ISB-JED | `offer-domestic.jpg` | no | no — index fallback | **PARTIAL** |

**Finding:** Featured deals use `resolveOfferMedia(offer, index)` modulo static fallbacks — not per-item CMS `image_asset_key`. No accidental same-image repeat across deals, but **not true CMS media binding**.

---

## 8. GLOBAL CONFIG RESULT (`/api/public/content/config`)

| Field | API value (production) | Rendered in header/footer/about | PASS |
|-------|------------------------|----------------------------------|------|
| brand_name | JetPakistan | JetPakistan | **PASS** |
| domain | jetpakistan.pk | — | **PASS** |
| phone | 0311 1222427 | About + footer | **PASS** |
| email | ota@jetpakistan.pk | About | **PASS** |
| default_seo.title | JetPakistan \| Affordable Flights… | `<title>` on `/` | **PASS** |
| site_verification.google | empty | not rendered | **PASS** |
| ai_assistant_enabled | false | FAB hidden | **PASS** |
| groups_path / support_path / booking_lookup_path | present | nav/footer links | **PASS** |

---

## 9. PUBLIC PAGE MATRIX (production browser/curl)

| Path | HTTP | Title/SEO | CMS content | Branding | Console/network issues | PASS |
|------|------|-----------|-------------|----------|----------------------|------|
| `/` | 200 | title + meta desc from config | cms hero, routes, deals | JetPakistan | ClashDisplay 404 | **PARTIAL** |
| `/about-us` | 200 | title, canonical, og | cms body + contact | JetPakistan | font 404 | **PASS** |
| `/support` | 200 | — | cms | JetPakistan | — | **PASS** |
| `/faq` | 200 | — | cms | JetPakistan | — | **PASS** |
| `/terms` | 200 | — | cms | JetPakistan | — | **PASS** |
| `/privacy` | 200 | — | cms | JetPakistan | — | **PASS** |
| `/lookup-booking` | 200 | — | n/a | JetPakistan | — | **PASS** |
| `/groups/search` | 200 | — | n/a | JetPakistan | — | **PASS** |
| `/sitemap` | 200 | — | n/a | JetPakistan | — | **PASS** |
| `/login` | 200 | — | n/a | JetPakistan | — | **PASS** |
| `/register` | 200 | — | n/a | JetPakistan | — | **PASS** |
| `/flights/results` | not fully swept | — | — | — | — | **PENDING** |
| `/groups` RSC prefetch | — | — | — | — | **200** (was 404 historically) | **PASS** |

No Parwaaz/Master/placeholder copy detected on sampled pages.

---

## 10. SEO MATRIX (sampled)

| Page | title | description | canonical | robots | og:title | Source | PASS |
|------|-------|-------------|-----------|--------|----------|--------|------|
| `/` | JetPakistan \| Affordable Flights… | yes | — | index | — | `default_seo` via config | **PASS** |
| `/about-us` | About us — JetPakistan \| JetPakistan | yes | `https://jetpakistan.pk/about-us` | — | About us — JetPakistan | page seo + global | **PASS** |

Phase 1/2 SEO architecture intact on probed pages. Homepage metadata intentionally from global config, not homepage API `seo` object.

---

## 11. ASSET / NETWORK MATRIX

| Failure | Page | Status | Notes |
|---------|------|--------|-------|
| `ClashDisplay-Bold.woff2` | `/` | **404** | Open blocker |
| `/groups` RSC prefetch | `/` | **200** | Previously reported 404 — **closed** on current prod |
| Homepage images (except KHI-RUH) | `/` | 200 | — |
| `/laravel/api/public/content/config` | all | 200 | — |
| Hydration errors | sampled | none observed | — |

**Final unexplained failures:** **1** (font 404)

---

## 12. RESPONSIVE CMS CONTENT TEST

| Viewport | Status | Notes |
|----------|--------|-------|
| 320–1440 | **NOT EXECUTED** | Required before deploy; golden recovery layout + CMS long-copy overflow not certified this session |

---

## 13. CONTENT SOURCE MATRIX (five-stage reconciliation)

| Surface | ADMIN | DB/CMS | LARAVEL API | NEXT SERVICE | RENDERED PAGE | Reconciles? |
|---------|-------|--------|-------------|--------------|---------------|-------------|
| Homepage hero | published home settings | ClientPageSetting + ClientPageAsset | hero.image.url | mapHero | CMS photo visible | **YES** |
| Homepage routes (3/4) | route items + keys | CMS JSON | prices/text; no image URL | resolveRouteMedia static | static JPGs | **PARTIAL** |
| Homepage route KHI-RUH | `route_seed_khi_ruh` asset uploaded | asset file exists | no image in item | empty fallback | **no image** | **NO** |
| About | page-settings/about | published JSON | `source: cms` | PublicPageService | live copy + contact | **YES** |
| Global config | global + SEO admin | published | config JSON | PublicConfigService | header/footer/meta | **YES** |

---

## 14. REQUIRED FIXES BEFORE CMS GATE PASS

1. **Wire KHI→RUH route image:** Either add `routesForDisplay()` image resolution (mirror destinations) **or** extend `resolveRouteMedia()` + pass CMS asset URL from API; use canonical asset `route_seed_khi_ruh-20260913115311.png`.
2. **Revalidation:** Call `NextPublicCacheRevalidator` on page-settings publish; add `homepage-cms` to webhook tag list.
3. **Font:** Restore or repoint `ClashDisplay-Bold.woff2`.
4. **Execute publish/restore QA cycle** (section 2).
5. **Responsive CMS sweep** (section 12).
6. **Featured deal CMS image binding** (optional improvement; currently PARTIAL not FAIL).

---

## 15. FIVE-LAYER FLOW (reference)

```
ADMIN (page-settings / SEO / cms-pages)
  → DATABASE (ClientPageSetting published JSON, ClientPageAsset, CmsPage)
    → LARAVEL API (/api/public/content/*)
      → NEXT SERVICE (HomepageContentService, PublicPageService, PublicConfigService, …)
        → RENDERED PAGE (PublicHero, RoutesSection, AboutPageContent, …)
```

Golden frontend recovery must preserve this chain. Current regression is at **Laravel API → Next adapter** for route images (KHI→RUH).

---

*Update this document after publish/restore QA, responsive sweep, and post-fix reverification.*
