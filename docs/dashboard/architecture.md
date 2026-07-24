# JetPakistan Next Dashboard Architecture

Phase: **JETPK-DASH-02** (extends DASH-01)

## Overview

The preview admin dashboard is an isolated Next.js 15 App Router application in [`dashboard/`](../../dashboard/). It does **not** modify Laravel routes, auth, or Blade dashboards.

| Layer | Location | Role |
|-------|----------|------|
| Legacy ops UI | `/admin`, `/staff` Blade | Production (unchanged) |
| Preview UI | `http://localhost:3001/testdash` | Mock-only Next shell + overview + bookings |

## Supersedes (new work only)

[`docs/jetpk/dashboard-implementation-plan.md`](../jetpk/dashboard-implementation-plan.md) described a **Blade theme migration**. The Next.js track starting with DASH-01 is the architecture authority for new dashboard UI; the Blade plan remains historical reference and is not deleted.

## Technical rules

1. **Server Components by default** — page composition and data read (mock service) on the server.
2. **Client Components** — sidebar drawer, header menus, charts (lazy), bookings filters/pagination/drawer, URL state updates.
3. **`basePath: /testdash`** — all routes and assets prefixed in production build.
4. **Preview guards** — [`dashboard/lib/preview.ts`](../../dashboard/lib/preview.ts) enforces mock data and blocks mutations unless explicitly enabled.
5. **Future API seam** — [`dashboard/services/overview-service.ts`](../../dashboard/services/overview-service.ts) and [`dashboard/services/booking-service.ts`](../../dashboard/services/booking-service.ts) swap mock for Laravel JSON later.

## DASH-02 modules

| Route | Status | Data |
|-------|--------|------|
| `/testdash` | live (DASH-01) | overview mock |
| `/testdash/bookings` | live (DASH-02) | booking fixtures + client URL state |
| `/testdash/planned/*` | planned stubs | n/a |

## Future integration (not DASH-02)

- Session-authenticated read API mirroring booking list/detail endpoints
- RBAC-aware nav using `RolePermissionMatrix` + `StaffPermission`
- Same-origin deploy via static export to `public/testdash/` (see preview-routing doc)

## Directory map

```text
dashboard/
  app/           Route segments (overview, bookings, planned stubs)
  components/    ui + dashboard chrome
  features/      overview + bookings modules
  layouts/       DashboardShell
  lib/           preview, nav, bookings query/filter, utils
  mocks/         fixture data only
  services/      data accessors
  types/         shared TS types
```
