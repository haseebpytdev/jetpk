# Phase 4 — Component Map & Cursor Integration

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION · baseline `6fbfae4`

## Components reused / extended / created

**Reused as canonical (confirmed):**
- `<x-bookings.detail-summary-card|detail-timeline|detail-itinerary|detail-passengers-contact|
  detail-updates|payment-documents-panel|detail-cancellation|detail-help-card>` — the shared
  booking-detail suite, here with `viewer-mode`/`audience="agent"` and the agent commission card.
- `<x-dashboard.status-badge>` — agent home + bookings tables.
- `App\Support\Bookings\{PaymentOperationalStatus,SupplierOperationalStatus,TicketingOperationalStatus,
  BookingPaymentSummaryPresenter,BookingItineraryOverviewPresenter,BookingDetailTimelinePresenter}` —
  the operational-status/presenter layer (unchanged).
- Portal `ota-account-*` + `ota-dashboard-*` vocabulary (hero, kpis, actions, cards, table, dl,
  finance grid, help card).

**Extended:** the three agent booking pages now render `<x-dashboard.breadcrumbs>` at the top of
their content — the only change.

**Created:** none.

**Deliberately NOT done:** no rebuild of the already-complete agent home; no new namespace; no change
to shared travelers (Phase 5).

## Contract-preservation checklist (verified)

`@extends(client_layout('agent-portal','agent'))`; `@section('account_*')`; `hasAgentPermission(...)`
+ `isAgentAdmin()` gates; `@can('request', [...])`; `route('agent.bookings.*'|'agent.dashboard'|
'agent.commissions.index')`; every `data-testid`; `<x-dashboard.status-badge>`; pagination
`$bookings->links()`. Diff = breadcrumb block only.

---

## Cursor integration steps

1. **Prerequisites:** Phase 1 integrated (`<x-dashboard.breadcrumbs>` + `ota-dashboard-foundation.css`
   in the agent-portal shell); ValidationException fix landed. Read local `.cursor` rules; adapt
   conflicts. Confirm baseline `6fbfae4`.
2. **Apply the three REPLACE files.** `git diff` should show only the added breadcrumb block per file.
3. **No asset change** → no `?v=` bump.
4. **Preserve contracts** (checklist above). No route/controller/model touched.
5. **Verify (gate):**
   ```
   php artisan ota:route-page-health-audit --all      # fail=0, server_errors=0
   php artisan test
   npx playwright test -c playwright.agent-critical.config.ts   # + responsive.agent
   npx playwright test tests/proposed-safe-tests/agent-bookings.spec.ts   # wire fixtures first
   grep -rInE "Parwaaz|YoursDomain|YD Travel|haseeb" resources/views/dashboard/agent   # no hits
   ```
   Exclude live configs. Check the nine viewports, focus ring, breadcrumb `aria-current`, no cyan glow.
   Because agent permissions vary, run at least one limited `agent_staff` session to confirm gated
   nav/actions still hide correctly (Phase 6 covers this depth).
6. **Commit in one reviewable layer.** Do not deploy. **Stop for review** before Phase 5.
