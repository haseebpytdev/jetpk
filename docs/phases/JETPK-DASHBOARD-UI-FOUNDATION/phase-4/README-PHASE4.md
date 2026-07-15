# Phase 4 — Agent Booking Operations Navigation

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION  
**Baseline:** `98ae4a8`  
**Depends on:** Phase 1 (`<x-dashboard.breadcrumbs>`)  
**Status:** integrated @ Cursor audit (breadcrumb-only normalization)

## Disposition

| Page | Route | Change |
|---|---|---|
| Agent home | `agent.dashboard` | preserved (no breadcrumb added) |
| Bookings index | `agent.bookings.index` | + breadcrumbs (legacy + JetPakistan themed) |
| Booking detail | `agent.bookings.show` | + breadcrumbs (legacy + JetPakistan themed) |
| Create booking | `agent.bookings.create` | + breadcrumbs (launcher preserved) |
| Exit booking mode | `agent.bookings.exit-mode` | unchanged (redirect) |

## Files

- `resources/views/dashboard/agent/bookings/{index,show,create}.blade.php`
- `resources/views/themes/agent/jetpakistan/bookings/{index,show,create}.blade.php`
- `tests/Feature/Dashboard/AgentBookingsNavigationTest.php`
- `tests/playwright/jetpk-dashboard/agent-bookings.spec.ts`
- `playwright.jetpk-dashboard.config.ts` (projects for Phase 3/4 specs)

## Verification

```bash
php artisan view:clear
php artisan test tests/Feature/Dashboard/AgentBookingsNavigationTest.php
php artisan test tests/Feature/Agent/AgentPortalPermissionMatrixFinalTest.php
npx playwright test -c playwright.jetpk-dashboard.config.ts --project=agent-bookings --project=agent-bookings-staff
```
