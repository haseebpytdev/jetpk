# Phase 2 — Component Map & Cursor Integration

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION · baseline `6fbfae4`

## Components reused / extended / created

**Reused (unchanged):**
- `<x-dashboard.status-badge :status>` — canonical status badge (`ota-bstat--*`); used on the
  home recent list + upcoming highlight and on the bookings table + cards. Portal-styled.
- Portal `ota-account-*` vocabulary — `ota-account-card`, `ota-account-card__body(--flush)`,
  `ota-account-table(-wrap)`, `ota-account-badge`, `ota-account-btn(--primary/--sm)`,
  `ota-account-empty`, `ota-account-filter-tabs`, `ota-account-list-card`, `ota-account-pagination`,
  `ota-portal-booking-actions--view-only`, `ota-r-text-safe` — reused exactly as the baseline
  bookings page uses them.
- `App\Support\Bookings\PaymentOperationalStatus` — same payment-label source as baseline.

**Extended:**
- `dashboard/customer/index.blade.php` — from stub to a populated home using the existing controller
  payload; now on the customer-account portal shell (was Tabler monolith).
- `dashboard/customer/bookings/index.blade.php` — de-duplicated (single `$rows` precompute) and
  pruned of unused computations; all columns/filters/actions/pagination preserved.

**Created:**
- `public/css/ota-customer-dashboard.css` — new home-only presentation (KPI grid, pending-payment
  banner, upcoming highlight, quick-action grid, recent list), scoped to `.ota-customer-dashboard`,
  fully tokenised (`var(--brand-*)`, `color-mix()`, design-system + Phase-1 tokens with fallbacks).

**Not created (deliberately):** no new status/badge/button/table primitives — the portal already
owns them; no `jp-dash-*` namespace; no Tabler `dashboard/kpi-stat`/`quick-action` in the portal
shell (those are Tabler-styled and belong to the admin/monolith world — the home uses portal-styled
equivalents instead).

## Data-contract preservation checklist (verified in the files)

`@extends(client_layout('customer-account','customer'))`; `@section('account_*')` contract;
`route('customer.bookings.index'|'customer.bookings.show'|'flights.search')` preserved;
`data-testid="customer-bookings-filters"` preserved; `<x-dashboard.status-badge>` preserved;
payment operational label + badge classes identical; pagination `$bookings->links()` preserved;
no `@csrf`/form/field change (these are read-only index pages). No controller variable added.

---

## Cursor integration steps

1. **Prerequisites:** Phase 1 integrated (customer-account shell + `ota-dashboard-foundation.css/js`
   linked); production ValidationException fix landed (Phase 0 gate). Read local `.cursor/rules/*.mdc`
   + `ui-design-brain/SKILL.md`; adapt any conflict. Confirm baseline SHA `6fbfae4`.
2. **Copy the new CSS** `public/css/ota-customer-dashboard.css`.
3. **Replace** `resources/views/dashboard/customer/index.blade.php` and
   `resources/views/dashboard/customer/bookings/index.blade.php` with the package versions.
4. **Confirm the layout switch:** the home now extends `client_layout('customer-account','customer')`.
   Verify that resolves for the JetPakistan tenant and that `@section('account_content')` renders in
   the Phase 1 shell. If a tenant overrides `client_layout('dashboard','customer')`, reconcile.
5. **Preserve contracts:** `client_route()`/`route()` as written, `data-testid`, status-badge,
   pagination, filters. No route/controller/model/migration touched.
6. **Asset version:** link `ota-customer-dashboard.css` via `ui_asset()`; set/record `?v=` per repo
   convention. No existing asset changed → no other bump.
7. **Verify (gate):**
   ```
   php artisan ota:route-page-health-audit --all      # fail=0, server_errors=0
   php artisan test                                     # existing PHPUnit green
   npm run build
   npx playwright test -c playwright.responsive.config.ts        # + public-critical
   npx playwright test tests/proposed-safe-tests/customer-dashboard.spec.ts   # wire loginAsCustomer() first
   grep -rInE "Parwaaz|YoursDomain|YD Travel|haseeb" resources/views/dashboard/customer public/css/ota-customer-dashboard.css   # no hits
   ```
   Exclude live configs. Check the nine Phase-0 viewports (desktop shell + mobile shell parity),
   visible focus ring, no cyan glow.
8. **Commit in reviewable layers** (CSS → home → bookings → test). Do not deploy. **Stop for review**
   before Phase 3.
