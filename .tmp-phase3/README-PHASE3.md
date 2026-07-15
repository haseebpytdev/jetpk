# Phase 3 — Customer Booking Detail, Travelers, Account & Support

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION
**Baseline:** `claude/ui-master` @ `6fbfae4637bb00e4a35b8edf3170a150d529b0b2` (re-verified
unchanged). **Depends on Phase 1** (canonical shell + `<x-dashboard.breadcrumbs>` + foundation
assets). **Status:** proposed package for Cursor. **No repository file was modified, committed,
pushed, or deployed.**

## 1. Investigation summary & honest assessment

A fresh read of every Phase 3 customer page found them **already well-built, componentized, and
portal-consistent** — not the greenfield redesign the phase label implies:

- **Booking detail** (`dashboard/customer/bookings/show`, 77 lines) delegates entirely to a
  canonical `<x-bookings.detail-*>` suite: `detail-summary-card`, `detail-timeline`,
  `detail-itinerary`, `detail-passengers-contact`, `detail-updates`, `payment-documents-panel`,
  `detail-cancellation`, `detail-help-card`. It already uses the portal shell, the
  `ota-account-detail-grid` layout, presenter data, and `data-testid` hooks.
- **Support** (`tickets/index|create|show`) already uses `<x-support.ticket-timeline>`,
  `<x-customer.support-status-badge>`, `@can('reply'|'close')` gates, CSRF/`@method`, validation
  states, and the portal `ota-account-*` vocabulary with desktop-table + mobile-card parity.
- **Travelers** (`dashboard/travelers/{index,create,edit}`) is a mature **shared** view
  (customer/agent/staff via `$routePrefix` + `$portalLayout`) with a `_form` partial, reused
  `status-badge`/`empty-state`/`quick-action`, and preserved CSRF/`@method DELETE`/confirm dialogs.
- **Profile** (`profile/edit-frontend`) is a thin wrapper on the portal shell delegating to the
  shared `profile.partials.universal-settings`.

**Engineering decision:** rebuilding pages that are already at target would only add review burden
and regression risk and would violate the programme's "reuse/extend, don't reinvent" rule. Phase 3
is therefore a **focused normalization + gap-fill**, and the concrete UI gap on these nested pages
is **breadcrumbs** (a Phase 0 component gap). Everything else is preserved as-is with justification.

## 2. Page-by-page disposition

| Page | Route | Disposition | Change in this package |
|---|---|---|---|
| Booking detail | `customer.bookings.show` | VISUAL NORMALIZATION | + breadcrumbs; detail-* suite preserved |
| Support tickets index | `customer.support.tickets.index` | VISUAL NORMALIZATION | + breadcrumbs |
| Create support ticket | `customer.support.tickets.create` | VISUAL NORMALIZATION | + breadcrumbs |
| Support ticket detail | `customer.support.tickets.show` | VISUAL NORMALIZATION | + breadcrumbs |
| Profile settings | `profile.edit` (customer → `profile.edit-frontend`) | VISUAL NORMALIZATION | + breadcrumbs |
| Travelers index | `customer.travelers.index` (shared) | PRESERVE AS-IS WITH JUSTIFICATION | none — shared with Agent; normalized in the Agent phase to avoid cross-phase churn |
| Add / Edit traveler | `customer.travelers.{create,edit}` (shared) | PRESERVE AS-IS WITH JUSTIFICATION | none — shared `_form`; see above |
| Support hub | `customer.support.index` | ACTION-ONLY / NO VIEW | none — `supportHub()` is a redirect |

## 3. Files in this package

```
resources/views/dashboard/customer/bookings/show.blade.php            REPLACE  + breadcrumbs
resources/views/dashboard/customer/support/tickets/index.blade.php    REPLACE  + breadcrumbs
resources/views/dashboard/customer/support/tickets/create.blade.php   REPLACE  + breadcrumbs
resources/views/dashboard/customer/support/tickets/show.blade.php     REPLACE  + breadcrumbs
resources/views/profile/edit-frontend.blade.php                        REPLACE  + breadcrumbs
tests/proposed-safe-tests/customer-detail-support.spec.ts              NEW      proposed safe spec
```

Each REPLACE file is the **verbatim baseline** with only a `<x-dashboard.breadcrumbs>` block added
at the top of `@section('account_content')`. No other line changed (diff is the breadcrumb block).

## 4. Data-contract notes (no backend change)

No new controller variable is introduced. Breadcrumb hrefs use existing named routes
(`customer.dashboard`, `customer.bookings.index`, `customer.support.tickets.index`) and existing
model data (`$booking->display_reference`, `$ticket->id`). All presenter data, `@can` gates,
`@csrf`/`@method`, validation, and `data-testid` hooks are preserved exactly.

## 5. Asset-version changes

**None.** No CSS/JS asset content changed. Breadcrumbs are styled by
`ota-dashboard-foundation.css` (delivered in Phase 1). Note: `profile/edit-frontend` already links
`ota-public.css?v=101` — unchanged here; do not bump it (asset unchanged).

## 6. Proposed tests

`tests/proposed-safe-tests/customer-detail-support.spec.ts` (local fixtures only; excluded from
`*-live.config.ts`): breadcrumbs render on booking detail + ticket detail; the detail-* layout and
support timeline are present; the reply form appears only when `@can('reply')`; no horizontal
overflow at 360/390/desktop. Wire `loginAsCustomer()` + a seeded booking/ticket before running.

## 7. Known limitations & scope notes

- **Depends on Phase 1** (`<x-dashboard.breadcrumbs>` + `ota-dashboard-foundation.css`).
- **Shared travelers views are intentionally untouched here.** They are shared with the Agent
  surface; adding breadcrumbs there is deferred to the Agent phase (Phase 4/5) so a single change
  covers both portals without cross-phase conflicts. If you prefer breadcrumbs on customer travelers
  now, add the same `<x-dashboard.breadcrumbs>` block guarded by `str_starts_with($routePrefix,'customer.')`.
- The `<x-bookings.detail-*>` suite, `<x-support.ticket-timeline>`, `<x-customer.support-status-badge>`,
  and the traveler `_form` are confirmed **canonical** — downstream phases reuse them, not fork them.
- Generated statically (no `artisan`/Playwright here); the integration manifest runs the real gates.
