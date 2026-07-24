# JetPakistan Next Dashboard Architecture

Phase: **JETPK-DASH-06-07** (extends DASH-04-05)

## Overview

The preview admin dashboard is an isolated Next.js 15 App Router application in [`dashboard/`](../../dashboard/). It does **not** modify Laravel routes, auth, or Blade dashboards.

| Layer | Location | Role |
|-------|----------|------|
| Legacy ops UI | `/admin`, `/staff` Blade | Production (unchanged) |
| Preview UI | `http://localhost:3001/testdash` | Mock-only Next shell + overview + bookings + payments + PNRs + tickets + customers + suppliers + agents |

## Supersedes (new work only)

[`docs/jetpk/dashboard-implementation-plan.md`](../jetpk/dashboard-implementation-plan.md) described a **Blade theme migration**. The Next.js track starting with DASH-01 is the architecture authority for new dashboard UI; the Blade plan remains historical reference and is not deleted.

## Technical rules

1. **Server Components by default** — page composition and data read (mock service) on the server.
2. **Client Components** — sidebar drawer, header menus, charts (lazy), bookings/payments filters/pagination/drawer, URL state updates.
3. **`basePath: /testdash`** — all routes and assets prefixed in production build.
4. **Preview guards** — [`dashboard/lib/preview.ts`](../../dashboard/lib/preview.ts) enforces mock data and blocks mutations unless explicitly enabled.
5. **Future API seam** — [`dashboard/services/overview-service.ts`](../../dashboard/services/overview-service.ts), [`dashboard/services/booking-service.ts`](../../dashboard/services/booking-service.ts), [`dashboard/services/payment-service.ts`](../../dashboard/services/payment-service.ts), [`dashboard/services/customer-service.ts`](../../dashboard/services/customer-service.ts), [`dashboard/services/supplier-service.ts`](../../dashboard/services/supplier-service.ts), [`dashboard/services/agent-service.ts`](../../dashboard/services/agent-service.ts), [`dashboard/services/pnr-service.ts`](../../dashboard/services/pnr-service.ts), and [`dashboard/services/ticket-service.ts`](../../dashboard/services/ticket-service.ts) swap mock for Laravel JSON later.

## DASH-06-07 modules

| Route | Status | Data |
|-------|--------|------|
| `/testdash` | live (DASH-01) | overview mock |
| `/testdash/bookings` | live (DASH-02) | booking fixtures + client URL state |
| `/testdash/payments` | live (DASH-03) | payment/transaction fixtures + client URL state |
| `/testdash/customers` | live (DASH-04) | customer/traveller fixtures + client URL state |
| `/testdash/suppliers` | live (DASH-05) | supplier fixtures + client URL state |
| `/testdash/agents` | live (DASH-06) | agent/agency fixtures + client URL state |
| `/testdash/pnrs` | live (DASH-07) | GDS PNR + NDC/order fixtures + client URL state |
| `/testdash/tickets` | live (DASH-07) | ticket/fulfilment document fixtures + client URL state |
| `/testdash/planned/*` | planned stubs | n/a |

### GDS vs NDC (DASH-07)

- **GDS PNRs** (`referenceType: GDS PNR`, `channel: Sabre GDS`) are distinct from **NDC orders** (`referenceType: NDC Order`, `channel: Sabre NDC`).
- Sabre GDS ticketing is shown as blocked/informational only — no live issuance implied; no LNIATA values in fixtures or UI.
- Cancellation eligibility is a read-only fixture status; no cancel actions or `SABRE_CANCEL_*` gate changes.

## Future integration (not DASH-02)

- Session-authenticated read API mirroring booking list/detail endpoints
- RBAC-aware nav using `RolePermissionMatrix` + `StaffPermission`
- Same-origin deploy via static export to `public/testdash/` (see preview-routing doc)

## Directory map

```text
dashboard/
  app/           Route segments (overview, bookings, payments, pnrs, tickets, customers, suppliers, agents, planned stubs)
  components/    ui + dashboard chrome
  features/      overview + bookings + payments + pnrs + tickets + customers + suppliers + agents modules
  layouts/       DashboardShell
  lib/           preview, nav, query/filter libs per module, utils
  mocks/         fixture data only
  services/      data accessors
  types/         shared TS types
```
