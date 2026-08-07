# JETPK-UI-01 â€” Gap Register

**Phase:** JETPK-UI-01 â€” Final UI Closure Audit
**Baseline SHA:** `8d62db8c2a37038e52e3130d45b9ad284510bfee`
**Branch:** `phase/jetpk-ui-01-final-ui-closure-audit`
**Generated:** 2026-08-07
**Machine-readable:** [JETPK-UI-01-GAP-REGISTER.json](./JETPK-UI-01-GAP-REGISTER.json)

## Summary

| Metric | Count |
|--------|------:|
| Total confirmed gaps | 22 |
| BLOCKER | 1 |
| HIGH | 8 |
| MEDIUM | 9 |
| LOW | 4 |
| Accessibility | 2 |
| Responsive | 2 |
| Leakage (production-visible) | 0 |
| CMS | 3 |

All gaps status: **CONFIRMED_OPEN**

---

## JETPK-UI-001 â€” Production frontend preview server renders client-side exception

| Field | Value |
|-------|-------|
| Severity | **BLOCKER** |
| Status | CONFIRMED_OPEN |
| Category | test_coverage |
| Route | `/` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1440x900 / light |
| Observed | next start on port 3002 shows 'Application error: a client-side exception has occurred' for all public routes; jp-ui-01 visual audit aborts on missing search-module. |
| Expected | Production preview server renders homepage and public routes without client-side exceptions when started after npm run build with Playwright fixture env. |
| Evidence | TEMP screenshot homepage-1440-light.png; audit:visual:jp-ui-01 exit 1; playwright-server requires .next/BUILD_ID |
| Backend impact | none |
| Phase | JETPK-UI-09 |

## JETPK-UI-002 â€” Homepage hero uses gradient illustration not approved photographic aircraft scene

| Field | Value |
|-------|-------|
| Severity | **HIGH** |
| Status | CONFIRMED_OPEN |
| Category | visual |
| Route | `/` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1440x900 / light |
| Observed | Hero displays green gradient band with abstract shapes; no full-bleed photographic aircraft/city imagery per Backup Safe mockup #1. |
| Expected | Full-bleed photographic hero with aircraft/city dominant visual plane per approved mockup inventory. |
| Evidence | TEMP homepage-dev-1440-light.png; Backup Safe mockup #1; frontend/docs/visual/MOCKUP-VS-ACTUAL-MISMATCH-REGISTER.md |
| Backend impact | none |
| Phase | JETPK-UI-03 |

## JETPK-UI-003 â€” Public header nav missing Hotels Offers Travel Services links from approved mockup

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | content |
| Route | `/*` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1440x900 / light |
| Observed | Desktop nav shows Flights, Groups (NEW), Support only; Hotels, Offers, Travel Services absent. |
| Expected | Nav matches approved mockup family or documented CMS nav contract when modules are enabled. |
| Evidence | TEMP homepage-dev-1440-light.png; JP-UI mockup inventory header spec |
| Backend impact | contract verification only |
| Phase | JETPK-UI-03 |

## JETPK-UI-004 â€” Flight results page lacks approved hero band and shows empty-state without fixture search

| Field | Value |
|-------|-------|
| Severity | **HIGH** |
| Status | CONFIRMED_OPEN |
| Category | visual |
| Route | `/flights/results` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1280x800 / light |
| Observed | Results page renders minimal toolbar, empty filters panel, 'Unable to load results' when search_id/fixture not supplied; no 'Choose Your Perfect Flight' hero band. |
| Expected | Structured results layout with hero band, populated filters, and result cards when authoritative search data is present; honest empty state when backend unavailable. |
| Evidence | TEMP results-dev-1280-light.png; mockup #13; MOCKUP-VS-ACTUAL-MISMATCH-REGISTER.md |
| Backend impact | contract verification only |
| Phase | JETPK-UI-04 |

## JETPK-UI-005 â€” Dedicated fare-selection page diverges from mockup inline results pattern

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | visual |
| Route | `/flights/fare-selection` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1280x800 / light |
| Observed | Fare families render inline on results and via drawer; separate /flights/fare-selection route exists but does not match mockup #11 full-page comparison layout. |
| Expected | Fare family comparison matches approved layout with branded fare cards and segment detail per mockup #11 or documented route consolidation decision. |
| Evidence | JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json; mockup inventory note on inline vs dedicated route |
| Backend impact | contract verification only |
| Phase | JETPK-UI-04 |

## JETPK-UI-006 â€” Booking progress stepper uses pill chips not connected mockup stepper

| Field | Value |
|-------|-------|
| Severity | **HIGH** |
| Status | CONFIRMED_OPEN |
| Category | design_system |
| Route | `/booking/*` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1280x800 / light |
| Observed | Booking flow pages use pill-style progress chips duplicated per page rather than unified connected horizontal stepper with labels from mockup family. |
| Expected | Connected multi-step progress bar with clear labels Search through Payment per approved booking mockups. |
| Evidence | MOCKUP-VS-ACTUAL-MISMATCH-REGISTER.md booking flow section; TEMP booking-passengers-1280-light.png |
| Backend impact | none |
| Phase | JETPK-UI-05 |

## JETPK-UI-007 â€” Dashboard sidebar shows duplicate PLANNED nav entries for Staff and Roles

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | design_system |
| Route | `/admin/dashboard` |
| Surface | dashboard |
| Role | Admin |
| Viewport / Theme | 1440x900 / light |
| Observed | Customers & partners group lists Staff Management and Roles & Permissions as PLANNED pointing to /planned/users while Access control group has live Users/Roles/Permissions routes. |
| Expected | No duplicate planned stubs when live routes exist; nav reflects authoritative Laravel modules only. |
| Evidence | TEMP admin-overview-1440-light.png; dashboard/lib/nav-config.ts |
| Backend impact | none |
| Phase | JETPK-UI-07 |

## JETPK-UI-008 â€” CMS module list view lacks page-builder settings density from prior CMS audit

| Field | Value |
|-------|-------|
| Severity | **HIGH** |
| Status | CONFIRMED_OPEN |
| Category | CMS |
| Route | `/admin/dashboard/cms/pages` |
| Surface | dashboard |
| Role | Admin |
| Viewport / Theme | 1280x800 / light |
| Observed | CMS pages tab shows filter bar and table list; Page Settings editor with section navigation, preview placement, and media controls not exercised in this audit path. |
| Expected | Page Settings is structured with clear active section, large preview, normalized file inputs, consistent media card heights per CMS audit requirements. |
| Evidence | TEMP admin-cms-pages-1280-light.png; prior CMS phase findings in docs/dashboard/ |
| Backend impact | contract verification only |
| Phase | JETPK-UI-08 |

## JETPK-UI-009 â€” Customer portal Playwright suite fails without Laravel session fixture wiring

| Field | Value |
|-------|-------|
| Severity | **HIGH** |
| Status | CONFIRMED_OPEN |
| Category | test_coverage |
| Route | `/customer/dashboard` |
| Surface | frontend |
| Role | Customer |
| Viewport / Theme | 390x844 / light |
| Observed | customer-dashboard.spec.ts 5/5 targeted tests failed when run against production smoke server without session fixture cookies. |
| Expected | Portal tests pass with documented fixture or Laravel local session without fabricating operational data. |
| Evidence | Playwright run 2026-08-07: 46 passed 11 failed including customer-dashboard.spec.ts |
| Backend impact | contract verification only |
| Phase | JETPK-UI-06 |

## JETPK-UI-010 â€” Agent portal Playwright suite fails without Laravel session fixture wiring

| Field | Value |
|-------|-------|
| Severity | **HIGH** |
| Status | CONFIRMED_OPEN |
| Category | test_coverage |
| Route | `/agent/dashboard` |
| Surface | frontend |
| Role | Agent |
| Viewport / Theme | 390x844 / light |
| Observed | agent-dashboard.spec.ts 4/4 targeted tests failed on smoke server without agent session. |
| Expected | Agent and Agent Staff portal tests pass with Laravel RBAC enforcement and fixture or live local session. |
| Evidence | Playwright run 2026-08-07 agent-dashboard.spec.ts failures |
| Backend impact | contract verification only |
| Phase | JETPK-UI-06 |

## JETPK-UI-011 â€” Homepage airport picker keyboard selection test fails

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | accessibility |
| Route | `/` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1440x900 / light |
| Observed | homepage.spec.ts keyboard airport picker test expects LHE value after Enter key selection but assertion fails. |
| Expected | Airport autocomplete supports keyboard selection with visible focus and value commit per a11y requirements. |
| Evidence | Playwright homepage.spec.ts failure at line 128 |
| Backend impact | none |
| Phase | JETPK-UI-09 |

## JETPK-UI-012 â€” Homepage full hero and search shell test fails on production smoke path

| Field | Value |
|-------|-------|
| Severity | **HIGH** |
| Status | CONFIRMED_OPEN |
| Category | test_coverage |
| Route | `/` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1440x900 / light |
| Observed | homepage.spec.ts 'homepage loads with full hero and search shell' fails when production preview server throws client exception. |
| Expected | Test passes on production preview server with search-module visible and data-search-layout compact. |
| Evidence | Playwright homepage.spec.ts failure; related to JETPK-UI-001 |
| Backend impact | none |
| Phase | JETPK-UI-09 |

## JETPK-UI-013 â€” Dashboard smoke suite could not complete in audit environment

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | test_coverage |
| Route | `/admin/dashboard` |
| Surface | dashboard |
| Role | Admin |
| Viewport / Theme | 1280x800 / light |
| Observed | npm run test:smoke timed out waiting for webServer on port 3003 or failed EADDRINUSE when manual server occupied port. |
| Expected | Dashboard smoke suite completes with documented port isolation. |
| Evidence | Playwright dashboard test:smoke exit 1 timeout 180000ms |
| Backend impact | none |
| Phase | JETPK-UI-09 |

## JETPK-UI-014 â€” Footer column count and newsletter stub diverge from mockup

| Field | Value |
|-------|-------|
| Severity | **LOW** |
| Status | CONFIRMED_OPEN |
| Category | visual |
| Route | `/*` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1440x900 / light |
| Observed | Footer renders 4 columns plus newsletter with preventDefault stub; mockup shows 5 columns. |
| Expected | Footer matches approved column structure or documented CMS nav contract. |
| Evidence | MOCKUP-VS-ACTUAL-MISMATCH-REGISTER.md global shell; TEMP results-dev screenshot footer |
| Backend impact | contract verification only |
| Phase | JETPK-UI-03 |

## JETPK-UI-015 â€” Destination and offers sections use fixture SVG not photography

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | visual |
| Route | `/` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 1440x900 / light |
| Observed | Destinations on the Rise and offers sections visible but use placeholder/fixture card imagery not mockup photography. |
| Expected | Photo destination cards and styled promo cards per mockup #1. |
| Evidence | TEMP homepage-dev-1440-light.png; JP-FULL-NEXT-FRONTEND-DEFERRED-VISUAL-POLISH.md |
| Backend impact | none |
| Phase | JETPK-UI-03 |

## JETPK-UI-016 â€” Frontend and dashboard typography token families differ

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | design_system |
| Route | `cross-surface` |
| Surface | frontend+dashboard |
| Role | all |
| Viewport / Theme | 1440x900 / light |
| Observed | Public frontend uses Fraunces/Instrument Sans pairing; dashboard uses dashboard-specific shell typography and spacing tokens. |
| Expected | Documented shared token map for brand typography with surface-appropriate density not conflicting hierarchy. |
| Evidence | Runtime compare homepage-dev vs admin-overview screenshots; docs/dashboard/DASHBOARD-VISUAL-SYSTEM.md |
| Backend impact | none |
| Phase | JETPK-UI-02 |

## JETPK-UI-017 â€” Tablet portrait viewport 768x1024 not fully matrix-audited

| Field | Value |
|-------|-------|
| Severity | **LOW** |
| Status | CONFIRMED_OPEN |
| Category | responsive |
| Route | `representative` |
| Surface | frontend+dashboard |
| Role | all |
| Viewport / Theme | 768x1024 / light+dark |
| Observed | Audit captured 1440, 1280, 1024, 390 viewports; 768x1024 tablet portrait not explicitly captured in TEMP evidence set. |
| Expected | All six mandatory viewports audited for representative routes. |
| Evidence | TEMP evidence inventory gap; audit limitation |
| Backend impact | none |
| Phase | JETPK-UI-09 |

## JETPK-UI-018 â€” Dark theme matrix incomplete for portal surfaces in this audit pass

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | responsive |
| Route | `representative` |
| Surface | frontend+dashboard |
| Role | all |
| Viewport / Theme | 1440x900 / dark |
| Observed | Theme toggle Auto present on public shell; dark theme TEMP captures not completed for customer/agent/admin routes in this audit pass. |
| Expected | Light and dark parity verified on all theme-capable representative routes. |
| Evidence | Audit limitation; jp-ui-03a historical 119/119 pass on public pages only |
| Backend impact | none |
| Phase | JETPK-UI-09 |

## JETPK-UI-019 â€” Laravel JetPakistan Blade admin design tests return 302 redirect

| Field | Value |
|-------|-------|
| Severity | **LOW** |
| Status | CONFIRMED_OPEN |
| Category | test_coverage |
| Route | `/admin (Blade)` |
| Surface | Laravel Blade fallback |
| Role | Admin |
| Viewport / Theme | n/a / n/a |
| Observed | 4/21 JetPakistan-filtered Laravel tests failed with 302 instead of expected 200 on Blade admin dashboard routes. |
| Expected | Tests pass or are classified as Blade-fallback-only with Next dashboard as primary surface. |
| Evidence | php artisan test --filter=JetPakistan: 17 passed 4 failed |
| Backend impact | none |
| Phase | JETPK-UI-07 |

## JETPK-UI-020 â€” Mobile compact viewport 360x800 not captured in TEMP evidence

| Field | Value |
|-------|-------|
| Severity | **LOW** |
| Status | CONFIRMED_OPEN |
| Category | responsive |
| Route | `representative` |
| Surface | frontend |
| Role | guest |
| Viewport / Theme | 360x800 / light |
| Observed | Mandatory 360x800 viewport not present in TEMP screenshot set; 390x844 captured instead. |
| Expected | 360x800 audited for representative public and portal routes. |
| Evidence | TEMP evidence inventory |
| Backend impact | none |
| Phase | JETPK-UI-09 |

## JETPK-UI-021 â€” Staff dashboard uses same shell as Admin without visual role distinction beyond nav

| Field | Value |
|-------|-------|
| Severity | **MEDIUM** |
| Status | CONFIRMED_OPEN |
| Category | design_system |
| Route | `/staff/dashboard` |
| Surface | dashboard |
| Role | Platform Staff |
| Viewport / Theme | 1280x800 / light |
| Observed | Staff overview screenshot shows identical shell geometry to admin; role distinction relies on nav items and backend authority only. |
| Expected | Staff vs Admin distinction understandable without exposing internal permission implementation; nav reflects authorized modules only. |
| Evidence | TEMP staff-overview-1280-light.png vs admin-overview-1440-light.png |
| Backend impact | contract verification only |
| Phase | JETPK-UI-07 |

## JETPK-UI-022 â€” Customer and agent portal screenshots show gate redirect without session

| Field | Value |
|-------|-------|
| Severity | **HIGH** |
| Status | CONFIRMED_OPEN |
| Category | visual |
| Route | `/customer/dashboard, /agent/dashboard` |
| Surface | frontend |
| Role | Customer, Agent |
| Viewport / Theme | 390x844 / light |
| Observed | Unauthenticated screenshot capture of portal routes does not show authenticated shell; visual audit of portal interiors incomplete without session fixture. |
| Expected | Portal interior layouts audited at mobile/tablet/desktop with fixture or local Laravel session. |
| Evidence | TEMP customer-dashboard-390-light.png agent-dashboard-390-light.png captured without session |
| Backend impact | contract verification only |
| Phase | JETPK-UI-06 |

---

## Proposed implementation phases

| Phase | Included gap IDs | Backend changes |
|-------|------------------|-----------------|
| JETPK-UI-02 | 016 | Prohibited |
| JETPK-UI-03 | 002, 003, 014, 015 | Prohibited |
| JETPK-UI-04 | 004, 005 | Contract verification only |
| JETPK-UI-05 | 006 | Prohibited |
| JETPK-UI-06 | 009, 010, 022 | Contract verification only |
| JETPK-UI-07 | 007, 019, 021 | Prohibited |
| JETPK-UI-08 | 008 | Contract verification only |
| JETPK-UI-09 | 001, 011, 012, 013, 017, 018, 020 | Prohibited |
