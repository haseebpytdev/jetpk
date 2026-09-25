# GOLDEN FRONTEND MANIFEST — JetPakistan

**Recovery program:** JETPAKISTAN-GOLDEN-FRONTEND-RECOVERY-AND-FULL-REINTEGRATION  
**Created:** 2026-09-16  
**Status:** LOCAL RESTORATION COMMITTED — DEPLOY / LIVE CERTIFICATION PENDING

---

## Three-baseline separation

| Baseline | Authority | Current SHA / marker | Notes |
|----------|-----------|----------------------|-------|
| **A. Functional / backend** | Current Laravel APIs, suppliers, booking, auth, groups | `27be8ece` + uncommitted flight-search hotfix | Production had hotfix layered on disk; must be committed |
| **B. SEO** | Phase 1 + Phase 2 architecture | `27be8ece` (commits `e54e1763`…`27be8ece`) | `generateMetadata`, `SeoJsonLd`, sitemap, robots, revalidate API — **main wins** |
| **C. Golden visual** | Previously finalized JetPakistan frontend | Composite below — **NOT** `27be8ece` | Presentation source of truth for recovery |

### Current production record (2026-09-16)

| Field | Value |
|-------|-------|
| CURRENT MAIN (post-recovery commit) | `45fd3e06abc4f5eed04d66493d81ff7577a9da3e` |
| PRIOR MAIN (functional/SEO only) | `27be8ece3e6e39826a7042f3712fc07fe1f7d52a` |
| PROD RUNTIME MARKER (last verified) | `27be8ece` (prior drift: `f3572b47` on AI release tree) |
| PROD FILE DRIFT | `SupplierProvider` enum hotfix applied on server only (now local uncommitted) |
| SERVER `jetpk_git` | `8c01ca4` (has `PairReturnCard`, `ai-assistant`; not visual-only golden) |
| NEXT BUILD_ID (post-restore session) | `hCUE77MrR3iVAugxUi2nw` |
| Production frontend deploy | **FROZEN** during golden discovery/restoration |

---

## Golden visual composite (approved sources)

| Priority | SHA / ref | Branch / archive | Role |
|----------|-----------|------------------|------|
| 1 | `67510da4` | `phase/jp-homepage-cms-authority-06` | **Primary recovery tree** — paired/segmented return (`efdfa0b3`), UX polish (`3c418a2f`), Ask JetPakistan FAB, homepage CMS authority, production UAT @ `8c50fc61` |
| 2 | `f593ddeb` | `phase/jp-ux-polish-02-deploy` | Production-isolated UX-02 polish evidence (PairReturn strip badges, PageHero, checkout footer) |
| 3 | `e33f3ea3` | `phase/jp-home-ui-01-codex` | Homepage hero/header/FAB responsive polish |
| 4 | `efdfa0b3` | (contained in `67510da4`) | Return paired + segmented booking flow restoration |
| 5 | `43c6081e` | merged to main | JETPK-UI-09 regression closure (functional baseline on main) |
| 6 | `89bc831a` | `phase/jetpk-full-next-frontend-ui-integration` | JP-FULL-NEXT-FRONTEND-01 accepted integration baseline (on main ancestry) |
| 7 | External | `JetPakistan-Full-NextJS-Frontend-UI` | Approved raster assets (`hero-pakistan.jpg`, destinations, offers) |
| 8 | Mockups | `C:\Users\khadi\Backup Safe\` (13 PNGs) | Geometry reference per `frontend/docs/visual/JP-UI-MOCKUP-INVENTORY-AND-SOURCE-OF-TRUTH.md` |

**Worktree mirrors:** `tmp/worktrees/jp-homepage-cms-authority-06`, `jp-ai-closure-deploy`, `jp-cms06-parity`

**Explicitly NOT visual authority:** `27be8ece` (SSR/SEO hotfix only), production simplified cards without `PairReturnCard`

---

## Per-area manifest

### Homepage

| Field | Value |
|-------|-------|
| **Approved reference** | Mockup #1 + `JETPK-UI-03` + `67510da4` / `e33f3ea3` |
| **SHA / archive** | `67510da4`, assets from `JetPakistan-Full-NextJS-Frontend-UI` |
| **Key files** | `PublicHero.tsx`, `RoutesSection.tsx`, `homepage-media.ts`, `homepage-content-service.ts` |
| **Expected** | Full-bleed hero photo, overlapping search, trending routes with approved imagery (LHE→DXB, LHE→JED, ISB→LHR, KHI→RUH), destinations, featured deals, why JetPakistan, support CTA |
| **On main `27be8ece`?** | Partial — CMS hero present; missing UX-02/e33f3ea3 polish and KHI→RUH mapping |
| **Current main difference** | Gradient/SVG fallbacks possible; RUH uses missing asset; hero→trending spacing polish absent |

### Header

| Field | Value |
|-------|-------|
| **Approved reference** | `JP-UI-02` + `JP-UX-POLISH-02` + `e33f3ea3` |
| **SHA** | `67510da4`, `f593ddeb` |
| **Key files** | `SiteHeader.tsx`, `resolve-header-logo.ts`, `navigation.ts` |
| **Expected** | JetPakistan logo proportions, compact header, account/currency/theme controls |
| **On main?** | Partial |
| **Difference** | Missing compact header polish from golden branches |

### Navigation

| Field | Value |
|-------|-------|
| **Approved reference** | `JETPK-UI-03` module authority |
| **SHA** | `43c6081e` (on main) |
| **Key files** | `navigation.ts`, `DesktopNavigation.tsx`, `MobileNavigation.tsx` |
| **Expected** | Flights, Groups, Support only (no Hotels/Offers/Travel Services) |
| **On main?** | Yes |
| **Difference** | None material |

### Flight search

| Field | Value |
|-------|-------|
| **Approved reference** | Mockup #1 compact search + `JP-FE-02` |
| **SHA** | `89bc831a`, `67510da4` |
| **Key files** | `SearchModule.tsx`, search panel on homepage |
| **Expected** | One-way / Return / Multi-city / Group tabs; autocomplete, swap, dates, travellers, cabin, direct, nearby, flexible dates |
| **On main?** | Yes (presentation) |
| **Difference** | Backend search fix required separately (SupplierProvider enum) |

### One-way results

| Field | Value |
|-------|-------|
| **Approved reference** | Mockup #13 + `JETPK-UI-04` |
| **SHA** | `43c6081e`, `583b025a` (UI-04 branch) |
| **Key files** | `FlightResultsPage.tsx`, `FlightResultCard.tsx`, `ResultsHeroBand.tsx` |
| **Expected** | Horizontal card: airline identity, route/timeline, price column, footer chips, details, baggage, branded-fare tray |
| **On main?** | Yes (structure present) |
| **Difference** | Golden has `FlightResultActions`, share actions, supplier badge from `67510da4` |

### Flight details

| Field | Value |
|-------|-------|
| **Approved reference** | `JP-FE-06` |
| **SHA** | `89bc831a` |
| **Key files** | `FlightDetailsDrawer.tsx`, fare revalidation hooks |
| **Expected** | Segments, layover, baggage policy, fare policy, breakdown — not raw supplier dump |
| **On main?** | Yes |
| **Difference** | Golden adds `legMode: "pair"` seeding for paired return |

### Branded fares

| Field | Value |
|-------|-------|
| **Approved reference** | Mockup #11 + `fare-selection-authority.ts` |
| **SHA** | `JETPK-UI-04` |
| **Key files** | `BrandedFareCarousel.tsx`, `/flights/fare-selection` |
| **Expected** | Carousel when >3 families; inline preview on cards |
| **On main?** | Yes |
| **Difference** | Minor carousel threshold sharing |

### Return Paired View

| Field | Value |
|-------|-------|
| **Approved reference** | **`efdfa0b3` + `67510da4`** |
| **SHA** | `efdfa0b3` (NOT on main) |
| **Key files** | `PairReturnCard.tsx`, `FlightResultsPage.tsx` (`view=pair`), `ReturnViewSelector.tsx`, backend `return_pair` flow |
| **Expected** | Single row: OUTBOUND \| RETURN \| PRICE; both journeys visible; details/fare behavior |
| **On main?** | **NO** — critical regression |
| **Difference** | Component and API path entirely absent |

### Return Segmented View

| Field | Value |
|-------|-------|
| **Approved reference** | **`efdfa0b3`** |
| **SHA** | `efdfa0b3` |
| **Key files** | `FlightResultsPage.tsx` (`view=segmented`), `OutboundOptionCard.tsx` |
| **Expected** | Outbound pick → return pick; distinct from paired |
| **On main?** | **NO** (only legacy split outbound) |
| **Difference** | No view toggle; no segmented mode |

### Return Options

| Field | Value |
|-------|-------|
| **Approved reference** | `JP-FE-05/06` + `efdfa0b3` |
| **SHA** | `67510da4` |
| **Key files** | `ReturnOptionsPage.tsx` |
| **Expected** | Return leg selection with golden polish |
| **On main?** | Partial |
| **Difference** | `efdfa0b3` enhancements not merged |

### Fare Selection

| Field | Value |
|-------|-------|
| **Approved reference** | Mockup #11 + `JETPK-UI-04` |
| **SHA** | `43c6081e` |
| **Key files** | `app/flights/fare-selection/page.tsx`, `FareFamilyDetails.tsx` |
| **Expected** | Full fare family comparison page |
| **On main?** | Yes |
| **Difference** | None material |

### Travellers / Contact / Review / Checkout / Payment

| Field | Value |
|-------|-------|
| **Approved reference** | Mockups #4/#8/#10 + `JETPK-UI-05` + `JP-UX-POLISH-02` |
| **SHA** | `67510da4`, `f593ddeb` |
| **Key files** | `booking/passengers`, `review`, `payment`, `BookingProgress.tsx` |
| **Expected** | JetPakistan progress stepper; skeleton on travellers; checkout footer visibility |
| **On main?** | Partial |
| **Difference** | UX-02 skeleton/footer fixes not on main |

### Group Search / Card / Detail / Passenger flow

| Field | Value |
|-------|-------|
| **Approved reference** | `JP-FE-07` + `JP-UX-POLISH-02` |
| **SHA** | `67510da4`, `f593ddeb` |
| **Key files** | `GroupSearchPage.tsx`, `GroupResultCard.tsx`, `groups/[packageId]/*` |
| **Expected** | Search, filters, cards, detail, seat/price, passengers, hold, review |
| **On main?** | Partial (functional; polish gap) |
| **Difference** | Hero/results modernization from UX-02; empty inventory is data not UI |

### Manage Booking / Support / FAQ / About

| Field | Value |
|-------|-------|
| **Approved reference** | Family matrix + `JETPK-UI-03` |
| **SHA** | `43c6081e`, `f593ddeb` (PageHero) |
| **Key files** | `lookup-booking`, `support`, `faq`, `about-us` pages |
| **Expected** | Public pages with shared PageHero where applicable |
| **On main?** | Yes (routes); partial hero polish |
| **Difference** | UX-02 PageHero variants not merged |

### Customer portal / Agent portal

| Field | Value |
|-------|-------|
| **Approved reference** | `JETPK-UI-06` / `43c6081e` |
| **SHA** | On main |
| **Key files** | `app/customer/*`, `app/agent/*` |
| **Expected** | JetPakistan-themed dashboards |
| **On main?** | Yes |
| **Difference** | Audit for drift only; no proven major regression |

### Ask JetPakistan FAB

| Field | Value |
|-------|-------|
| **Approved reference** | **`67510da4` / `e33f3ea3`** |
| **SHA** | `67510da4` |
| **Key files** | `AskJetPakistanChat.tsx`, `PublicFloatingActionDock.tsx`, `PublicShell.tsx`, `PublicFloatingLayoutProvider.tsx` |
| **Expected** | Visible FAB when `ai_assistant_enabled`; desktop/mobile safe area; chat open/close/loading/error |
| **On main?** | **NO** |
| **Difference** | Committed `PublicShell` has no AI mount; config gating `internal_canary` hides for anonymous |

### Footer / Mobile navigation

| Field | Value |
|-------|-------|
| **Approved reference** | `JP-UI-02` + `JETPK-UI-03` |
| **SHA** | On main |
| **Key files** | `SiteFooter.tsx`, `MobileNavigation.tsx` |
| **Expected** | 4-column footer; mobile drawer |
| **On main?** | Yes |
| **Difference** | None material |

---

## Recovery merge strategy

```
CURRENT BACKEND (main + flight hotfix)
+ CURRENT SEO (main shells — page.tsx, layout, sitemap, robots, revalidate)
+ GOLDEN PRESENTATION (path-scoped from 67510da4)
```

**Do:** adapt current API payloads → golden components  
**Do not:** replace golden UI with simplified main components  
**Do not:** blind checkout golden `app/page.tsx` or `(public)/layout.tsx` (SEO regression)

### Backend paired-return port required

Main lacks `return_pair` / `paired_options`. Must port from `67510da4`:

- `app/Http/Controllers/Frontend/FlightController.php`
- `app/Services/FlightSearch/ReturnSplitComboService.php`

---

## Asset authority (homepage routes)

| Route | Approved asset | Source |
|-------|----------------|--------|
| LHE → DXB | `destination-dubai.jpg` | `JetPakistan-Full-NextJS-Frontend-UI` |
| LHE → JED | `destination-jeddah.jpg` | same |
| ISB → LHR | `destination-london.jpg` | same |
| KHI → RUH | `offer-gcc.jpg` (GCC proxy per UI-03) | `homepage-media.ts` mapping |

---

## Known asset failures to fix

| Asset | Issue |
|-------|-------|
| `ClashDisplay-Bold.woff2` | 404 on production |
| `/groups` RSC prefetch | 404 |

---

## Restoration checklist (local before deploy)

- [ ] `PairReturnCard` + `ReturnViewSelector` restored
- [ ] Backend `return_pair` API restored
- [ ] Ask JetPakistan FAB wired (gated by config)
- [ ] Homepage route images (incl. KHI→RUH)
- [ ] Flight search hotfix committed
- [ ] SEO Phase 1+2 regression tests pass
- [ ] `npm run typecheck` + `npm run build`
- [ ] Playwright: paired view, segmented view, FAB, horizontal cards
- [ ] Visual comparison matrix captured
- [ ] Single canonical deploy (no SCP patches)

---

## Visual comparison matrix (to complete)

| Page | Golden ref | Current prod | Recovered local | Final prod |
|------|------------|--------------|-----------------|------------|
| Homepage | pending | pending | pending | pending |
| One-way Results | pending | pending | pending | pending |
| Paired Return | pending | pending | pending | pending |
| Segmented Return | pending | pending | pending | pending |
| Fare Details | pending | pending | pending | pending |
| Fare Selection | pending | pending | pending | pending |
| Travellers | pending | pending | pending | pending |
| Review/Checkout | pending | pending | pending | pending |
| Group Search | pending | pending | pending | pending |
| Group Detail | pending | pending | pending | pending |
| Customer Dashboard | pending | pending | pending | pending |
| Ask JetPakistan | pending | pending | pending | pending |

---

*This manifest is the frontend source of truth for golden recovery. Update as restoration progresses.*
