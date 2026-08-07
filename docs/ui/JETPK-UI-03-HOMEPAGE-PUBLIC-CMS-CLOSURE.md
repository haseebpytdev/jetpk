# JETPK-UI-03 — Homepage, Public Shell, CMS Content and Photography Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JETPK-UI-03 |
| Branch | `phase/jetpk-ui-03-homepage-public-cms-closure` |
| Baseline (parent) | `44053c09b14f86a29da3215ae1e62be9543adc09` |
| Gaps targeted | JETPK-UI-002, JETPK-UI-003, JETPK-UI-014, JETPK-UI-015 |
| Commit subject | `feat: close JetPakistan homepage and public shell gaps` |
| Deployment | NOT PERFORMED |
| Main merge | NOT PERFORMED |

## Historical JP-UI-03 reconciliation

| Gap | Historical JP-UI-03 work | Current runtime (pre-fix) | Why still open | Residual fix applied |
|-----|--------------------------|---------------------------|----------------|----------------------|
| JETPK-UI-002 | `PublicHero` full-bleed layout existed; fallback was SVG | Production smoke showed gradient/SVG fallback plane | No approved photographic asset in repo | Copied `hero-pakistan.jpg`; wired `approvedHeroMedia` fallback |
| JETPK-UI-003 | Hotels/Offers intentionally omitted in historical JP-UI-03 | Nav showed only Flights, Groups, Support | Mockup nav exceeded operational modules | Documented authoritative enabled-module contract in `lib/navigation.ts`; tests assert hidden unsupported modules |
| JETPK-UI-014 | Newsletter removed in JP-UI-02 | Footer already 4 columns, no subscribe UI | Audit register still referenced newsletter stub | Confirmed 4-column honest contract; exported `footerInformationArchitecture` |
| JETPK-UI-015 | Destination/offer cards used SVG fixtures | `RoutesSection` passed `src={null}`; offers were gradient-only | No approved raster assets in `frontend/public` | Copied approved JPGs; `resolveRouteMedia` / `resolveOfferMedia` presentation fallbacks |

**Authority:** August 2026 JETPK-UI-01 gap register and production smoke evidence take precedence over historical “phase closed” JP-UI-03 statements.

## Approved reference

- Mockup #1 (homepage geometry): `C:\Users\khadi\Backup Safe\ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png` — layout reference only; **not** copied into `public/`.
- Inventory: `frontend/docs/visual/JP-UI-MOCKUP-INVENTORY-AND-SOURCE-OF-TRUTH.md`

## Asset authority table

| Asset ID | Final path | Source | Classification | Bytes |
|----------|------------|--------|----------------|------:|
| hero | `frontend/public/images/home/hero-pakistan.jpg` | `JetPakistan-Full-NextJS-Frontend-UI/public/assets/hero-pakistan.jpg` | APPROVED_SOURCE_ASSET | 120,775 |
| destination-dubai | `frontend/public/images/home/destination-dubai.jpg` | `.../destination-1.jpg` | APPROVED_SOURCE_ASSET | 24,613 |
| destination-jeddah | `frontend/public/images/home/destination-jeddah.jpg` | `.../destination-2.jpg` | APPROVED_SOURCE_ASSET | 22,666 |
| destination-london | `frontend/public/images/home/destination-london.jpg` | `.../destination-3.jpg` | APPROVED_SOURCE_ASSET | 28,405 |
| destination-istanbul | `frontend/public/images/home/destination-istanbul.jpg` | `.../destination-4.jpg` | APPROVED_SOURCE_ASSET | 24,342 |
| offer-gcc | `frontend/public/images/home/offer-gcc.jpg` | `.../inspiration-1.jpg` | APPROVED_SOURCE_ASSET | 26,693 |
| offer-uk | `frontend/public/images/home/offer-uk.jpg` | `.../inspiration-2.jpg` | APPROVED_SOURCE_ASSET | 29,179 |
| offer-domestic | `frontend/public/images/home/offer-domestic.jpg` | `.../inspiration-3.jpg` | APPROVED_SOURCE_ASSET | 19,988 |
| **Total raster added** | | | | **296,661** |

Legacy SVG placeholders remain in repo for non-photographic fallback paths but are no longer referenced by homepage fixtures.

## Hero asset provenance

- **Authoritative asset:** `/images/home/hero-pakistan.jpg`
- **Provenance:** Standalone JetPakistan frontend asset from read-only reference repo `JetPakistan-Full-NextJS-Frontend-UI/public/assets/` (not extracted from mockup PNG).
- **Integration:** `PublicHero` + `HomepageContentService.heroFallbackImage` + `approvedHeroMedia` in `frontend/lib/homepage-media.ts`.

## Navigation module authority

| Module | Status | Visible | Notes |
|--------|--------|---------|-------|
| Flights | ENABLED_REAL_ROUTE | Yes (dropdown) | Search + manage booking |
| Groups | ENABLED_REAL_ROUTE | Yes | `/groups/search` |
| Support | ENABLED_REAL_ROUTE | Yes (dropdown) | Help, contact, FAQ |
| Hotels | NONEXISTENT | No | No operational route |
| Offers | NONEXISTENT | No | No standalone offers module |
| Travel Services | NONEXISTENT | No | No travel services hub |

**Visible desktop nav:** Flights, Groups, Support  
**Visible mobile nav:** Flights, Groups, Support (drawer)  
**Intentionally hidden:** Hotels, Offers, Travel Services

## Footer authority

| Column | Links |
|--------|-------|
| Explore | `/`, `/groups/search`, `/lookup-booking` |
| Company | `/about-us`, `/sitemap` |
| Support | `/support`, `/contact`, `/faq`, `/lookup-booking` |
| Legal | `/terms`, `/privacy` |

- **Content column count:** 4 (+ brand column in layout grid)
- **Newsletter disposition:** Not supported — no backend subscription endpoint; no interactive subscribe UI rendered.

## Destination / offer image authority

| Card | Image | Alt |
|------|-------|-----|
| Dubai | `destination-dubai.jpg` | Dubai skyline at sunset |
| Jeddah | `destination-jeddah.jpg` | Jeddah Red Sea coastline |
| London | `destination-london.jpg` | London cityscape along the Thames |
| Istanbul | `destination-istanbul.jpg` | Istanbul skyline with historic architecture |
| GCC offer | `offer-gcc.jpg` | Gulf city skyline at dusk |
| UK offer | `offer-uk.jpg` | United Kingdom travel destination |
| Domestic offer | `offer-domestic.jpg` | Pakistan domestic travel scenery |

Presentation resolver: `frontend/lib/homepage-media.ts` (`resolveRouteMedia`, `resolveOfferMedia`).

## Application files changed

- `frontend/lib/homepage-media.ts` (new)
- `frontend/lib/navigation.ts`
- `frontend/features/public-visual/services/homepage-content-service.ts`
- `frontend/features/public-visual/types/homepage.ts`
- `frontend/features/public-visual/hero/PublicHero.tsx`
- `frontend/features/public-visual/destinations/RoutesSection.tsx`
- `frontend/features/public-visual/offers/FeaturedOffersSection.tsx`
- `frontend/features/home/fixtures/destinations.ts`
- `frontend/features/home/fixtures/offers.ts`

## Test files changed / added

- `frontend/tests/jetpk-ui-03-homepage-public-shell.spec.ts` (new)
- `frontend/tests/jetpk-ui-03-homepage-photography.spec.ts` (new)
- `frontend/tests/regression/jetpk-ui-03-homepage-media.test.mjs` (new)
- `frontend/playwright.jetpk-ui-03.config.ts` (new — dev server for photography fixture verification; does not alter UI-001 production smoke)
- `frontend/tests/homepage.spec.ts`
- `frontend/tests/jp-ui-02-header-footer.spec.ts`

## Verification

| Check | Result |
|-------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `node tests/regression/jetpk-ui-03-homepage-media.test.mjs` | PASS (3/3) |
| Playwright UI-03 shell (production smoke) | PASS 6/6 targeted |
| Playwright UI-03 photography (dev fixtures) | PASS 1/1 |
| `homepage.spec.ts` hero assertion | PASS |
| `homepage.spec.ts` destinations heading | **PRE_EXISTING_CMS_DEPENDENCY** — requires Laravel CMS routes in production smoke when fixtures disabled |
| `php artisan test --filter=JetPakistanLegacyTypographyAuthorityTest` | PASS (2 tests, 9 assertions) |

## Responsive / theme evidence

Captured outside git under `%TEMP%\jetpk-ui-03-evidence\` (homepage viewports 1440/1280/768/390/360 light; dark capture attempted on dev server).

## Gap closure status

| Gap | Status | Rationale |
|-----|--------|-----------|
| JETPK-UI-002 | **CLOSED** | Approved photographic hero integrated; production smoke hero `img` src matches `hero-pakistan` |
| JETPK-UI-003 | **CLOSED** | Authoritative nav contract documented; all visible links resolve; unsupported modules hidden |
| JETPK-UI-014 | **CLOSED** | Four-column footer contract documented; no newsletter stub |
| JETPK-UI-015 | **CLOSED** | Approved JPGs integrated; fixture + resolver paths verified; dev photography Playwright PASS |

## Remaining registered UI gaps

**17** (21 pre-phase minus 4 closed here). JETPK-UI-016 remains closed from UI-02A.

## Out of scope (untouched)

JETPK-UI-001, 004–013, 017–022; dashboard/; Laravel business logic; database; routes; suppliers; booking; OTP; RBAC; typography system.

## Leakage / honesty scans

- Brand leakage in modified production UI: **0**
- Fabricated business data introduced: **0** (sample price labels preserved)
- Production connections during phase: **0**

## Rollback

```bash
git checkout main
git branch -D phase/jetpk-ui-03-homepage-public-cms-closure
```

Remove added JPGs under `frontend/public/images/home/` if reverting assets only.
