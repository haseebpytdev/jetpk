# JETPK-DASH-02 — Design System, Responsive Shell & Bookings Foundation

## Phase

**JETPK-DASH-02-DESIGN-SYSTEM-RESPONSIVE-SHELL-AND-BOOKINGS-FOUNDATION**

## Branch

`phase/jetpk-dash-02-design-system-responsive-shell-and-bookings-foundation`

## Baseline commit

`f1d6256` — feat(dashboard): add isolated Next.js admin dashboard foundation (JETPK-DASH-01)

## Objective

Establish reusable dashboard UI primitives, improve the responsive application shell, and deliver a read-only Booking Management module on mock data with filters, sorting, pagination, URL state, detail drawer, and loading/empty/error states—without Laravel, auth, or live APIs.

## Included scope

- Design system primitives (layout, inputs, table, pagination, drawer, status badges, metrics)
- Grouped sidebar navigation; Bookings route live at `/testdash/bookings`
- 25 deterministic mock bookings covering status/payment/ticketing/supplier/trip variants
- Server-driven list page + client URL state for filters, pagination, sort, drawer (`id` query)
- Playwright smoke expansion (bookings + DASH-01 regression)
- `docs/dashboard/*` updates for architecture, page map, mock policy

## Excluded scope

- Laravel routes, PHP, Blade, deployment, SFTP
- Authentication, RBAC enforcement, live APIs, mutations (cancel/refund/ticket/pay)
- DASH-03 work

## Investigation findings

- DASH-01 already provided shell, preview guards, and overview patterns; bookings needed a dedicated query/filter layer and client/server split for URL state.
- Desktop table and mobile cards both mount in DOM; tests must scope to `data-testid` regions to avoid strict-mode duplicates.

## Root causes addressed

- Bookings nav pointed at planned stub only; now primary Bookings item targets `/bookings`.
- No shared operational table/drawer patterns for modules; added reusable UI + bookings feature slice.

## Architecture decisions

| Decision | Rationale |
|----------|-----------|
| Server page reads `searchParams`, calls `getBookingsPage` | Keeps mock IO on server; default RSC |
| Client `BookingsWorkspace` updates URL via `router.push` | Back/forward and reload preserve state |
| Pure `lib/bookings-filter.ts` | Deterministic filter/sort/paginate; testable without React |
| Drawer selection via `?id=` | Filters unchanged when opening/closing detail |
| Apply-filters interaction | Explicit apply + clear-all; avoids partial URL drift |

## Design system additions

`PageContainer`, `PageHeader`, `SectionHeader`, `Breadcrumb`, `PreviewDataBanner`, `Input`, `SearchInput`, `DateInput`, `Select`, `Checkbox`, `IconButton`, `Divider`, `MetricCard`, `StatusBadge` variants, `Table`, `Pagination`, `Drawer` (focus + Escape).

## Responsive behavior

- **Desktop (lg+):** Persistent sidebar, bookings data table, right drawer (~max-w-xl).
- **Tablet:** Overlay sidebar (unchanged pattern), filters in responsive grid.
- **Mobile:** Off-canvas nav, booking cards (`md:hidden`), full-width drawer, pagination controls wrap.

## Booking data model

See `dashboard/types/booking.ts` — `BookingRecord` with id, PNR, supplier ref, dates, customer, route, trip type, airline, supplier, booking/payment/ticketing status, currency, amounts, agent/source, `lastUpdated`.

## Filtering semantics

- Free-text `q` matches id, PNR, customer, email, phone, route, airline, supplier ref.
- Enum filters: `status`, `payment`, `ticketing`, `supplier`, `airline`, `tripType`.
- Date boundaries: `bookingDateFrom/To`, `departureDateFrom/To` (inclusive ISO date strings).
- Summary metrics computed on **filtered** set (not page slice).

## Sorting semantics

Fields: `bookingDate`, `departureDate`, `customer`, `route`, `amount`, `status`, `lastUpdated`. Tie-break: `id`. Default: `bookingDate` desc. Toggle asc/desc on repeated column click.

## Pagination semantics

Client-side on filtered/sorted set. `pageSize` ∈ {10, 20, 50} (invalid → 20). Page clamped to `[1, pageCount]`. Filter/sort/pageSize changes reset or recalc page as implemented in workspace.

## URL state contract

| Param | Purpose |
|-------|---------|
| `q` | Search |
| `status`, `payment`, `ticketing` | Enum filters or omitted = all |
| `supplier`, `airline` | Exact match |
| `tripType` | `one_way` / `return` |
| `bookingDateFrom`, `bookingDateTo` | Booking date range |
| `departureDateFrom`, `departureDateTo` | Departure range |
| `page`, `pageSize` | Pagination |
| `sort`, `direction` | Sort field and `asc`/`desc` |
| `id` | Open booking drawer |
| `previewError=1` | Simulate recoverable service error |

## Drawer behavior

Read-only sections; preview banner; close via button, backdrop, or Escape; focus moves to panel on open.

## Accessibility

Semantic headings, labelled filters, table headers, icon button labels, drawer `role="dialog"`, status pills include non-color dot, `prefers-reduced-motion` respected in global CSS.

## Tests executed

From `dashboard/`:

```text
npm ci
npm list next react react-dom --depth=0
npm run typecheck
npm run lint
npm run build
npm run test:smoke
```

## Test results

| Command | Result |
|---------|--------|
| `npm ci` | Pass (clean install: Node processes stopped, `node_modules` deleted, then `npm ci`; recharts deprecation + 3 high npm audit advisories reported — not remediated per phase rules) |
| `npm list next react react-dom --depth=0` | next@15.5.21, react@19.2.8, react-dom@19.2.8 |
| `npm run typecheck` | Pass |
| `npm run lint` | Pass |
| `npm run build` | Pass |
| `npm run test:smoke` | **27 passed, 0 failed** |

## Playwright inventory

Command: `npx playwright test -c playwright.config.ts --list` (from `dashboard/`).

**Total: 27 tests in 2 files** (single Chromium project).

| File | Tests | Breakdown |
|------|------:|-----------|
| `dashboard/tests/bookings.smoke.spec.ts` | **18** | 2 DASH-01 regressions (overview + planned route), 16 bookings module tests |
| `dashboard/tests/overview.smoke.spec.ts` | **9** | 8 responsive overview viewports + 1 planned-module stub |

**Reconciliation:** A prior narrative listed “18 bookings + 8 overview viewports + 1 planned stub + 1 overview regression” (= 28) by double-counting the two DASH-01 regression tests already inside `bookings.smoke.spec.ts`. Correct total remains **27**.

## Assertion counts

Playwright: **27** tests, **27** passed (verified on commit-readiness review).

## Responsive verification

Playwright viewports: 360, 390, 430, 768, 1024, 1280, 1440, 1920 (overview); bookings desktop 1280 + mobile 360/390; horizontal overflow check at 360.

## Screenshots

Not captured in this pass (CI-style smoke only).

## Known limitations

- Global header search remains disabled preview stub.
- Payments/Tickets/Cancellations nav still route to planned stubs with queue query hints.
- No export, bulk actions, or column customization.
- Error demo requires `?previewError=1` on bookings URL.

## Risks

- Future live API must reimplement query contract server-side for parity.
- Large datasets will need server pagination (currently client-side on mock set).

## Deferred work

- Laravel JSON booking list/detail API
- Session auth + RBAC nav filtering
- Mutations and payment/ticketing workflows

## Rollback scope

Revert all changes under `dashboard/**` and `docs/dashboard/**` and remove `docs/phases/JETPK-DASH-02-SUMMARY.md`. No Laravel files touched.

## Files changed

### Modified

- `dashboard/components/dashboard/sidebar.tsx`
- `dashboard/layouts/dashboard-shell.tsx`
- `dashboard/lib/nav-config.ts`
- `docs/dashboard/architecture.md`
- `docs/dashboard/dashboard-page-map.md`
- `docs/dashboard/mock-data-policy.md`

### Added

- `dashboard/app/bookings/page.tsx`
- `dashboard/app/bookings/loading.tsx`
- `dashboard/components/ui/checkbox.tsx`
- `dashboard/components/ui/divider.tsx`
- `dashboard/components/ui/drawer.tsx`
- `dashboard/components/ui/icon-button.tsx`
- `dashboard/components/ui/input.tsx`
- `dashboard/components/ui/metric-card.tsx`
- `dashboard/components/ui/page-layout.tsx`
- `dashboard/components/ui/pagination.tsx`
- `dashboard/components/ui/select.tsx`
- `dashboard/components/ui/status-badge.tsx`
- `dashboard/components/ui/table.tsx`
- `dashboard/features/bookings/booking-detail-drawer.tsx`
- `dashboard/features/bookings/bookings-error-panel.tsx`
- `dashboard/features/bookings/bookings-filters.tsx`
- `dashboard/features/bookings/bookings-mobile-cards.tsx`
- `dashboard/features/bookings/bookings-page-content.tsx`
- `dashboard/features/bookings/bookings-summary.tsx`
- `dashboard/features/bookings/bookings-table.tsx`
- `dashboard/features/bookings/bookings-workspace.tsx`
- `dashboard/lib/bookings-filter.ts`
- `dashboard/lib/bookings-query.ts`
- `dashboard/lib/format.ts`
- `dashboard/mocks/booking-fixtures.ts`
- `dashboard/services/booking-service.ts`
- `dashboard/tests/bookings.smoke.spec.ts`
- `dashboard/types/booking.ts`
- `docs/phases/JETPK-DASH-02-SUMMARY.md`

## Routes changed

- **New:** `/testdash/bookings` (dynamic, `searchParams`)

## Database changes

None.

## Backend changes

None (Next mock services only).

## Frontend changes

Design system, shell nav groups, full bookings module.

## Commit SHA

*(placeholder — not committed per phase instructions)*

## Deployment status

**Not deployed**

## Commit-readiness review (final)

- Branch confirmed: `phase/jetpk-dash-02-design-system-responsive-shell-and-bookings-foundation`
- Changed paths restricted to `dashboard/**`, `docs/dashboard/**`, `docs/phases/JETPK-DASH-02-SUMMARY.md`
- No `.gitignore` changes; `.next`, `node_modules`, `test-results` gitignored and untracked
- One defect found during review: flaky drawer Escape close — fixed in `dashboard/components/ui/drawer.tsx` (capture-phase listener + stable `onClose` ref); re-verified 27/27 smoke pass

## Final status

**READY_FOR_COMMIT** — validation green after drawer Escape fix; not staged/committed/pushed/deployed in this review.
