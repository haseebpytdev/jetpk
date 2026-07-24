# Dashboard Page Map (Legacy → `/testdash`)

Phase: **JETPK-DASH-03** (extends DASH-02)

## Architecture authority

- **Functional:** Laravel routes/controllers (this map).
- **Visual (Next):** Approved green-accent mockup; operation-first KPIs from `AgencyDashboardService`.
- **Supersedes for new work:** [`docs/jetpk/dashboard-implementation-plan.md`](../jetpk/dashboard-implementation-plan.md) (Blade theme migration only — historical).

## Status legend

| Status | Meaning |
|--------|---------|
| **live** | Implemented in Next (DASH-01 overview, DASH-02 bookings, DASH-03 payments) |
| **planned** | Nav stub / placeholder page |
| **n/a** | No Laravel equivalent; mock-only |

## Overview

| Legacy route | Legacy name | Testdash path | Status |
|--------------|-------------|---------------|--------|
| `/admin` | `admin.dashboard` | `/testdash` | **live** |
| `/staff` | `staff.dashboard` | `/testdash` (shared preview shell) | planned overlay |

## Navigation map (JetPK sidebar → Next)

| Sidebar label | Laravel route(s) | Testdash path | Status |
|---------------|------------------|---------------|--------|
| Dashboard | `admin.dashboard` | `/testdash` | **live** |
| Bookings | `admin.bookings` / `staff.bookings.index` | `/testdash/bookings` | **live** |
| Payments | `admin.payments` (future) | `/testdash/payments` | **live** |
| Flight search | `flights.search` | `/testdash/planned/flights` | planned (public search) |
| Customers | `admin.customers.index` | `/testdash/planned/customers` | planned |
| Suppliers | `admin.api-settings` | `/testdash/planned/suppliers` | planned |
| Group ticketing | `admin.group-ticketing.index` | `/testdash/planned/group-ticketing` | planned |
| Reports | `admin.reports` / `staff.reports.index` | `/testdash/planned/reports` | planned |
| Accounting | `admin.ledger.index`, accounting.* | `/testdash/planned/accounting` | planned |
| Markups | `admin.markups` | `/testdash/planned/markups` | planned |
| Support tickets | `admin.support.tickets.index` | `/testdash/planned/support` | planned |
| Users | `admin.users.index` | `/testdash/planned/users` | planned |
| Agents | `admin.agents` | `/testdash/planned/agents` | planned |
| Page settings | `admin.page-settings.index` | `/testdash/planned/page-settings` | planned |
| Settings | `admin.settings.index` | `/testdash/planned/settings` | planned |
| Communications | `admin.settings.communications.index` | `/testdash/planned/communications` | planned |
| Diagnostics | `admin.system-health` | `/testdash/planned/diagnostics` | planned |

## Mockup-only nav items (no first-class Laravel list route)

| Mockup label | Maps to | Testdash |
|--------------|---------|----------|
| Payments | Booking payment ledger + reconciliation (future Laravel) | `/testdash/payments` |
| Tickets | `ticketing` queue + issue-ticket action | planned (bookings) |
| Cancellations | Cancellations queue + cancellation workflow | planned (bookings) |
| Audit Logs | `admin.bookings.audit.export`, wallet-audit | planned (diagnostics) |
| Notifications | Comms delivery log / failed notification KPIs | mock widget + planned comms |
| Roles & Permissions | `admin.roles-permissions` | planned (users) |
| Staff Management | `admin.staff` + staff users in `admin.users` | planned (users) |

## Overview widgets map

| Widget | Laravel data source | DASH-01 |
|--------|---------------------|---------|
| Action queue cards | `themes/admin/jetpakistan/index` + service counts | Mock shaped like service |
| Command center | `AgencyDashboardService::buildAdminCommandCenter` | Mock panels |
| Recent bookings | `build()` → `recentBookings` | Mock table |
| Supplier health | `DashboardController` supplier methods | Mock system health |
| Stats row (mockup) | `stats` array in service | Mock + “Preview data” label |
