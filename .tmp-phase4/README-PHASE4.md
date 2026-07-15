# Phase 4 — Agent Dashboard & Booking Operations

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION
**Baseline:** `claude/ui-master` @ `6fbfae4637bb00e4a35b8edf3170a150d529b0b2` (re-verified
unchanged). **Depends on Phase 1** (canonical shell + `<x-dashboard.breadcrumbs>`).
**Status:** proposed package for Cursor. **No repository file was modified, committed, pushed, or
deployed.**

## 1. Investigation summary & honest assessment

A fresh read of the agent dashboard + booking pages (controllers `Agent\DashboardController@index`
and `Agent\AgentBookingController@{index,show,create}`) found them **already well-built,
permission-gated, and portal-consistent** — the same result as the customer detail/support surface
in Phase 3:

- **Agent home** (`dashboard/agent/index`, 302 lines) is a complete dashboard: branded hero,
  permission-gated quick actions, a KPI grid from `bookingKpis`, a finance summary from
  `financeSummary` + `walletSummary`, a recent-bookings table (with `<x-dashboard.status-badge>`,
  supplier/payment operational statuses, commission column) and a support aside — every block gated
  by `$portalPermissions` and `Route::has()`. It already uses the payload the controller computes.
- **Bookings index** (112 lines): filter toolbar (same 5 filters), full operational table (Ref,
  Customer, Route, Travel date, Total, Status, Payment, PNR/supplier, Actions), `status-badge`,
  operational-status presenters, commission-aware, empty state, pagination — permission-gated.
- **Bookings detail** (`show`, 97 lines) delegates to the canonical `<x-bookings.detail-*>` suite
  (same as customer) plus an agent commission card and a policy-gated cancellation
  (`@can('request', …)`).
- **Create** (34 lines) is a correct agent booking-mode launcher (links into the shared flight
  search/checkout, back to bookings), not a duplicate booking form.

**Engineering decision:** these pages are at target; rebuilding them would add regression risk and
violate "reuse/extend, don't reinvent." Phase 4 is a **normalization + gap-fill**, and the concrete
gap on the nested booking pages is **breadcrumbs** (Phase 0 gap). Everything else is preserved.

## 2. Page-by-page disposition

| Page | Route | Disposition | Change here |
|---|---|---|---|
| Agent home | `agent.dashboard` | PRESERVE AS-IS WITH JUSTIFICATION | none — already a complete permission-gated dashboard |
| Bookings index | `agent.bookings.index` | VISUAL NORMALIZATION | + breadcrumbs |
| Booking detail | `agent.bookings.show` | VISUAL NORMALIZATION | + breadcrumbs; detail-* suite preserved |
| Create booking | `agent.bookings.create` | VISUAL NORMALIZATION | + breadcrumbs |
| Exit booking mode | `agent.bookings.exit-mode` | ACTION-ONLY / NO VIEW | none (redirect) |

## 3. Files in this package

```
resources/views/dashboard/agent/bookings/index.blade.php    REPLACE  + breadcrumbs
resources/views/dashboard/agent/bookings/show.blade.php     REPLACE  + breadcrumbs
resources/views/dashboard/agent/bookings/create.blade.php   REPLACE  + breadcrumbs
tests/proposed-safe-tests/agent-bookings.spec.ts            NEW      proposed safe spec
```

Each REPLACE file is the **verbatim baseline** with only a `<x-dashboard.breadcrumbs>` block added
at the top of `@section('account_content')` — the diff is exactly that block.

## 4. Data-contract notes (no backend change)

No new controller variable. Breadcrumb hrefs use existing routes (`agent.dashboard`,
`agent.bookings.index`) and existing data (`$booking->display_reference`). All permission gates
(`hasAgentPermission`, `isAgentAdmin`), `@can` policies, presenter data, `data-testid`
(`agent-bookings-filters`, `agent-bookings-create-link`, `agent-booking-commission`, …), and routes
are preserved.

## 5. Asset-version changes

**None.** No CSS/JS asset content changed. Breadcrumbs are styled by `ota-dashboard-foundation.css`
(Phase 1).

## 6. Proposed tests

`tests/proposed-safe-tests/agent-bookings.spec.ts` (local fixtures only; excluded from
`*-live.config.ts`): breadcrumbs render on bookings index/detail; the operational table or empty
state always shows; the detail page renders the detail-* layout; the "New booking" link is gated by
`BookingsCreate`. Wire the agent fixture + seeded booking first. Gate stays
`ota:route-page-health-audit --all` (`fail=0`) + PHPUnit + local Playwright (agent-critical).

## 7. Known limitations & scope

- **Depends on Phase 1** (`<x-dashboard.breadcrumbs>` + foundation CSS).
- **Agent home preserved as-is** — it is already a complete, permission-gated dashboard; forcing a
  redesign would risk regressions across many gated blocks.
- **Shared travelers views** (customer/agent/staff) still get their breadcrumb/normalization pass in
  **Phase 5** (agent finance, deposits, commissions, staff, support, travelers), so one change covers
  all portals — as noted in Phase 3.
- Generated statically; the integration manifest runs the real gates.
