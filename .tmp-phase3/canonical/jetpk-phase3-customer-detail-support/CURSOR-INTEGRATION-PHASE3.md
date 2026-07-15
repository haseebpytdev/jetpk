# Phase 3 — Component Map & Cursor Integration

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION · baseline `6fbfae4`

## Components reused / extended / created

**Reused as canonical (confirmed — do not fork in later phases):**
- `<x-bookings.detail-summary-card>`, `<x-bookings.detail-timeline>`, `<x-bookings.detail-itinerary>`,
  `<x-bookings.detail-passengers-contact>`, `<x-bookings.detail-updates>`,
  `<x-bookings.payment-documents-panel>`, `<x-bookings.detail-cancellation>`,
  `<x-bookings.detail-help-card>` — the customer booking-detail suite (also serves agent/guest via
  `viewer-mode` / `shell` props).
- `<x-support.ticket-timeline>`, `<x-customer.support-status-badge>` — support surfaces.
- `<x-dashboard.status-badge>`, `<x-dashboard.empty-state>`, `<x-dashboard.section-header>`,
  `<x-dashboard.quick-action>` — shared widgets.
- `dashboard/travelers/_form`, `dashboard/customer/partials/default-traveler-card`,
  `dashboard/support/_thread`, `profile/partials/universal-settings` — shared partials.
- Portal `ota-account-*` vocabulary (cards, tables, badges, buttons, empty, detail-grid, stack,
  form-card/grid/actions, alert).

**Extended:** the five customer nested pages now render `<x-dashboard.breadcrumbs>` (Phase 1
component) at the top of their content — the only change.

**Created:** none. No new component or CSS — the Phase 1 breadcrumbs component fills the gap.

**Deliberately NOT done:** no rebuild of already-canonical pages; no new namespace; no change to
the shared travelers views (deferred to the Agent phase to avoid touching an agent-shared file in a
customer phase).

## Contract-preservation checklist (verified)

`@extends(client_layout('customer-account','customer'))`; `@section('account_*')`; `@can('reply'|'close')`
gates; `@csrf`/`@method('patch'|'DELETE')`; validation (`@error`/`is-invalid`/`invalid-feedback`);
`route('customer.support.tickets.*'|'customer.bookings.*'|'customer.dashboard')`; every `data-testid`
(`customer-booking-detail-layout`, `customer-support-reply-form`, `customer-support-ticket-form`,
`customer-support-tickets-table`, `customer-support-tickets-empty`, …). Diff = breadcrumb block only.

---

## Cursor integration steps

1. **Prerequisites:** Phase 1 integrated (`<x-dashboard.breadcrumbs>` + `ota-dashboard-foundation.css`
   present in the customer-account shell); ValidationException fix landed (Phase 0 gate). Read local
   `.cursor/rules/*.mdc` + `ui-design-brain/SKILL.md`; adapt any conflict. Confirm baseline `6fbfae4`.
2. **Apply the five REPLACE files.** Because each is the verbatim baseline plus a breadcrumb block,
   `git diff` should show only the added `<x-dashboard.breadcrumbs>` block per file — review that the
   diff is exactly that.
3. **No asset change** → no `?v=` bump. Leave `ota-public.css?v=101` in `profile/edit-frontend` as-is.
4. **Preserve contracts** (see checklist above). No route/controller/model/migration touched.
5. **Verify (gate):**
   ```
   php artisan ota:route-page-health-audit --all      # fail=0, server_errors=0
   php artisan test                                     # existing PHPUnit green (support/booking/traveler)
   npx playwright test -c playwright.responsive.config.ts        # + public-critical, agent-critical (regression)
   npx playwright test tests/proposed-safe-tests/customer-detail-support.spec.ts   # wire fixtures first
   grep -rInE "Parwaaz|YoursDomain|YD Travel|haseeb" resources/views/dashboard/customer resources/views/profile/edit-frontend.blade.php   # no hits
   ```
   Exclude live configs. Check the nine Phase-0 viewports, focus ring, breadcrumb keyboard nav
   (`aria-current="page"` on the last crumb), no cyan glow.
6. **Commit in one reviewable layer** (breadcrumbs + test). Do not deploy. **Stop for review** before
   Phase 4 (Agent dashboard + booking operations), where the shared travelers views get their pass.
