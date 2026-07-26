# JETPK-DASH-08-09 — Reports, Analytics and Theme-Aware CMS Foundation

## Phase

**JETPK-DASH-08-09-REPORTS-ANALYTICS-THEME-AWARE-CMS-FOUNDATION**

## Purpose

Deliver read-only **Reports & Analytics** and **theme-aware CMS preview** modules in the JetPakistan Next.js test dashboard, with deterministic fixtures, URL-backed query state, accessible charts and previews, export safety, and a portable contract for future public Next.js integration. No Laravel, live APIs, persistence, or production mutations.

## Starting baseline

- Branch baseline commit: `52f3bb2` — docs(dashboard): record DASH-06-07 documentation commit SHA
- Prior dashboard modules: overview, bookings, payments, customers, suppliers, agents, PNRs, tickets (255 Playwright tests)
- Routes before phase: 15

## Branch

`phase/jetpk-dash-08-09-reports-analytics-theme-aware-cms`

## Reports routes

| Route | Module |
|-------|--------|
| `/testdash/reports` | Overview — executive KPIs, trends, attention queue |
| `/testdash/reports/sales` | Sales — revenue breakdowns, sorting, export |
| `/testdash/reports/bookings` | Bookings — lifecycle, funnel, drill-through |
| `/testdash/reports/payments` | Payments — collection, reconciliation proxies |
| `/testdash/reports/operations` | Operations — GDS/NDC distinction, fulfilment, ticketing limits |

## CMS routes

| Route | Module |
|-------|--------|
| `/testdash/cms` | Overview — metrics, distributions, attention queue |
| `/testdash/cms/pages` | Pages — composition, drawer preview, revisions |
| `/testdash/cms/sections` | Sections — registry-driven list, local preview form |
| `/testdash/cms/banners` | Banners — family filters, family-specific preview |
| `/testdash/cms/notices` | Notices — severity filters, placement preview |
| `/testdash/cms/assets` | Assets — metadata-only, variant display |

## Reports architecture

Operational fixture graph → `lib/reports/query-filters.ts` → `lib/reports/aggregations.ts` / `build-report.ts` → `ReportService` → `ReportsPageContent` → `ReportsModuleShell` → `ReportsWorkspace` (filters, metrics, Recharts + accessible tables, attention queue, export menu).

## CMS architecture

`cms-fixtures.ts` → `lib/cms/query-filters.ts` → `build-cms-module.ts` → `CmsService` → `CmsPageContent` → `CmsModuleShell` → `CmsWorkspace` (filters, table/cards, drawers, preview shell, local preview form).

## Fixture sources

| Source | Role |
|--------|------|
| `booking-fixtures.ts`, `payment-fixtures.ts`, `customer-fixtures.ts`, `supplier-fixtures.ts`, `agent-fixtures.ts`, `pnr-fixtures.ts`, `ticket-fixtures.ts` | Reports operational graph (existing DASH-01–07 data) |
| `report-fixtures.ts` | Saved views, export manifests, chart metadata |
| `cms-fixtures.ts` | Pages, section instances, banners, notices, assets, revisions, FAQs |

### Deterministic fixture counts

| Entity | Count |
|--------|-------|
| CMS pages | 20 |
| CMS section instances | 35 |
| CMS section registry definitions | 18 |
| CMS banners | 24 |
| CMS notices | 24 |
| CMS assets | 40 |
| CMS revisions | 48 |
| CMS FAQ items | 20 |
| Operational bookings | 26 (existing) |
| Report reference date | `2026-07-01` |

## Reports KPI model

See `docs/dashboard/REPORT-KPI-DEFINITIONS.md`. KPIs derive from filtered operational fixtures: gross booking value, collected payments, outstanding balance, booking/customer/agent counts, collection rate, GDS/NDC volumes, fulfilment and ticketing states. Comparison deltas when `comparison` query enabled.

## Currency rules

- PKR-only fixtures (`REPORT_SUPPORTED_CURRENCIES`)
- `sumSameCurrencyAmounts()` — no mixed-currency totals
- Unsupported currency filter returns unavailable monetary KPIs

## Date-reference rules

- `REPORT_REFERENCE_DATE = 2026-07-01T00:00:00.000Z`
- Default preset: **This year** (`current_year`) to include Jan–Feb 2026 fixture bookings
- Custom ranges validated; invalid ranges show inline error and suppress misleading totals
- No `Date.now()` or `Math.random()` in fixtures or tests

## Export safety

- Client-side CSV via `export-download.ts` + `csv-safe.ts`
- Formula injection neutralized (`escapeCsvCell`)
- Export manifest defines approved columns only
- No sensitive fields (card data, credentials, LNIATA, PCC)

## CMS section registry

`CMS_SECTION_REGISTRY` — 18 definitions with unique `frontendComponentKey` values. Drives filters, validation, preview component selection, and future Next.js trusted component resolution.

## Theme controls

Enums/tokens only: `themeMode` (automatic, day, night, dualAsset, neutral), `themeTreatment`, `contentWidth`, `spacing`. No arbitrary CSS, hex, or JavaScript in CMS fields.

## Preview modes

`desktop_day`, `desktop_night`, `tablet`, `mobile_day`, `mobile_night` — labelled **Dashboard preview only**. Approximates public layout; not pixel-perfect.

## Asset metadata

Metadata-only `CmsAsset` records with desktop/mobile/day/night variants, focal point, alt text, approval status. No upload, filesystem paths, or CDN writes.

## Validation model

`cms-validation.ts`, `link-validation.ts` — missing alt text, unapproved assets, invalid placement, publication window conflicts, unsafe URL protocols, carousel rule (>3 offers), duplicate singleton sections. Surfaced via `CmsValidationSummary` and attention queue.

## Local unsaved preview behavior

`CmsLocalPreviewForm` updates component state only. **Apply to preview** shows unsaved notice. **Reset preview** and page refresh restore fixture values. No persistence.

## Future Next.js compatibility

Typed `CmsPage` + `CmsSectionInstance[]` contract; `frontendComponentKey` maps to trusted React components. See `docs/dashboard/NEXTJS-INTEGRATION-ROADMAP.md`. No Blade or PHP references.

## GDS/NDC boundaries

- GDS PNRs: `referenceType: GDS PNR`, channel `Sabre GDS`
- NDC orders: `referenceType: NDC Order`, channel `Sabre NDC`
- One API, Manual, Mock channels remain separate lanes in operations reports

## Ticketing limitations

Sabre GDS ticketing blocked status is informational only. No live issuance, LNIATA, or ticket mutation controls.

## Cancellation boundaries

Cancellation eligibility is display-only fixture status. No cancel actions. `SABRE_CANCEL_*` gates untouched.

## Security boundaries

- No live APIs, `fetch`, axios, or XMLHttpRequest in feature code
- No `dangerouslySetInnerHTML` for CMS content
- No iframe, eval, or arbitrary executable content
- No localStorage/sessionStorage for CMS state
- JetPakistan-only branding; no brand selector

## Accessibility

- Chart titles, descriptions, and `.sr-only` summaries
- Parallel data tables for chart values
- Labelled filters and form controls
- Drawer focus trap; Escape closes and restores focus
- `aria-sort` on sortable columns
- Status text badges (not color alone)
- Accordion state for FAQ preview
- Validation issues associated with fields

## Responsive behavior

- Desktop tables (`md:block`), mobile cards (`md:hidden`)
- Verified at 360px, 390px, 768px, 1280px on representative routes
- No horizontal overflow assertions on Reports and CMS mobile viewports
- Preview frame scales by preview mode viewport width

## Test inventory

| File | Tests |
|------|-------|
| `reports-cms.foundation.spec.ts` | 36 |
| `reports-overview.smoke.spec.ts` | 23 |
| `reports-sales.smoke.spec.ts` | 13 |
| `reports-bookings.smoke.spec.ts` | 12 |
| `reports-payments.smoke.spec.ts` | 10 |
| `reports-operations.smoke.spec.ts` | 16 |
| **Reports subtotal** | **83** |
| `cms-overview.smoke.spec.ts` | 23 |
| `cms-pages.smoke.spec.ts` | 25 |
| `cms-sections.smoke.spec.ts` | 23 |
| `cms-banners.smoke.spec.ts` | 20 |
| `cms-notices.smoke.spec.ts` | 16 |
| `cms-assets.smoke.spec.ts` | 23 |
| **CMS subtotal** | **130** |
| `critical-regression.smoke.spec.ts` | 21 |
| Prior DASH-01–07 specs | 255 |
| **Total** | **527** |

Prior baseline 255 tests preserved. Playwright `retries: 0` unchanged.

## Validation commands

```bash
cd dashboard
npm run typecheck
npm run lint
npm run build
npx playwright test tests/reports-cms.foundation.spec.ts --retries=0
npx playwright test tests/reports-*.smoke.spec.ts --retries=0
npx playwright test tests/cms-*.smoke.spec.ts --retries=0
npx playwright test tests/critical-regression.smoke.spec.ts --retries=0
npx playwright test --retries=0
```

### Validation results (Prompt 04)

| Check | Result |
|-------|--------|
| `npm run typecheck` | pass |
| `npm run lint` | pass (no warnings) |
| `npm run build` | pass — **22 routes** |
| Foundation spec | 36 passed |
| Reports specs | 83 passed |
| CMS specs | 132 passed |
| High-risk repeat-each=2 | 40 passed |
| Critical regression | 21 passed |
| Full suite `--retries=0` | **527 passed**, 0 failed, 0 flaky, 0 skipped |

## Optimized Playwright strategy

Documented in `docs/dashboard/NEXTJS-INTEGRATION-ROADMAP.md`. Targeted specs during development; full suite once at phase gate. Retries remain 0.

## Known limitations

- Mock/fixture data only — no live Laravel or supplier APIs
- Booking/payment dates span Jan–Feb 2026; presets outside `current_year` may return zero rows
- CMS preview is dashboard-only; not pixel-perfect vs public Blade
- No CMS persistence, publishing, or media upload
- No WYSIWYG HTML editor
- Payment ageing bands omitted (no due-date field)
- Domestic/international derived from PK airport code set
- `previewLoading` / `previewEmpty` / `previewError` are QA triggers, not real network states

## Future integration work

- Laravel report aggregation API preserving `ReportModuleResult`
- CMS JSON API (`GET /api/v1/cms/pages/{slug}`)
- Public Next.js app with trusted component registry
- RBAC-scoped queries and signed export downloads
- Production media CDN and upload pipeline
- ISR caching and on-demand revalidation

## Explicit non-goals

- Live APIs, database access, Laravel/PHP/Blade changes
- CMS persistence, publishing, media uploads
- Sabre ticketing, cancellation mutations
- Brand switching / multi-tenant UI
- Second public Next.js app in this phase
- Deployment or merge to main

## Final commit SHA

`7448931` — feat(dashboard): add reports analytics and theme-aware CMS foundation

## Documentation commit SHA

`53c6389` — docs(dashboard): finalize DASH-08-09 phase summary

## Remote tracking branch

`jetpk/phase/jetpk-dash-08-09-reports-analytics-theme-aware-cms`

## Final status

**JETPK-DASH-08-09 COMPLETE**
