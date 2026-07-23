# Admin Page Inventory

Phase: **JETPK-DASH-01**  
Per-page fields: route, name, method, middleware, controller@action, view, data source, filters, permissions, mutation risk, API readiness, `/testdash`.

Legend: **Mutation risk** — `none` (GET only page), `low`, `high` (forms/POST on same page). **Testdash** — `live` (DASH-01), `planned`.

## Dashboard

| Route | Name | Method | Middleware | Controller | View | Data source | Filters | Permissions | Mutation | API | Testdash |
|-------|------|--------|------------|------------|------|-------------|---------|-------------|----------|-----|----------|
| `/admin` | `admin.dashboard` | GET | platform_admin | `DashboardController@index` | `client_view('index','admin')` → `themes/admin/jetpakistan/index` | `AgencyDashboardService::build`, `buildAdminCommandCenter`, supplier readiness/health, support alerts | — | `Gate::authorize('viewAny', Booking)` | none | medium | **live** |

## Customers

| Route | Name | Method | Controller | View area | Permissions | Mutation | Testdash |
|-------|------|--------|------------|-----------|-------------|----------|----------|
| `/admin/customers` | `admin.customers.index` | GET | `CustomerManagementController@index` | themed customers index | Booking/customer policies | none | planned |
| `/admin/customers/{customer}` | `admin.customers.show` | GET | `CustomerManagementController@show` | show | policy | none | planned |
| `/admin/customers/guests/show` | `admin.customers.guests.show` | GET | `CustomerManagementController@showGuest` | guest show | policy | none | planned |

## Bookings

| Route | Name | Method | Controller | View | Data / filters | Mutation | Testdash |
|-------|------|--------|------------|------|----------------|----------|----------|
| `/admin/bookings` | `admin.bookings` | GET | `BookingManagementController@index` | `bookings` / themed | Queues, filters in query string | low (links to mutations) | planned |
| `/admin/bookings/{booking}` | `admin.bookings.show` | GET | `@show` | booking show | Booking model + relations | high (action panel) | planned |
| `/admin/bookings/{booking}/preview` | `admin.bookings.preview` | GET | `@preview` | partial/XHR | AJAX panel | none | planned |

## Users & agencies

| Route | Name | GET controller | View | Testdash |
|-------|------|----------------|------|----------|
| `/admin/users` | `admin.users.index` | `UserManagementController@index` | users index | planned |
| `/admin/users/create` | `admin.users.create` | `@create` | create | planned |
| `/admin/users/{user}` | `admin.users.show` | `@show` | show | planned |
| `/admin/users/{user}/edit` | `admin.users.edit` | `@edit` | edit | planned |
| `/admin/agencies` | `admin.agencies.index` | `AgencyManagementController@index` | agencies | planned |
| `/admin/agencies/{agency}` | `admin.agencies.show` | `@show` | show | planned |
| `/admin/agents` | `admin.agents` | `AdminSectionController@agents` | agents | planned |
| `/admin/staff` | `admin.staff` | `AdminSectionController@staff` | staff list | planned |
| `/admin/roles-permissions` | `admin.roles-permissions` | `@rolesPermissions` | matrix | planned |

## Finance (module: `finance_reports`)

| Route | Name | Controller | Testdash |
|-------|------|------------|----------|
| `/admin/reports` | `admin.reports` | `AdminSectionController@reports` | planned |
| `/admin/ledger` | `admin.ledger.index` | `AdminLedgerController@index` | planned |
| `/admin/finance/dashboard` | `admin.finance.dashboard` | `FinanceDashboardController@index` | planned |
| `/admin/finance/wallet-audit` | `admin.finance.wallet-audit.index` | `WalletAuditController@index` | planned |
| `/admin/accounting/ledger` | `admin.accounting.ledger.index` | `AccountingLedgerController@index` | planned |

## Settings & CMS

| Route | Name | Notes | Testdash |
|-------|------|-------|----------|
| `/admin/settings` | `admin.settings.index` | Hub | planned |
| `/admin/settings/payments` | `admin.settings.payments.index` | AbhiPay | planned |
| `/admin/settings/branding` | `admin.settings.branding.edit` | module branding | planned |
| `/admin/settings/communications` | `admin.settings.communications.index` | module notifications | planned |
| `/admin/cms-pages` | `admin.cms-pages.index` | Legacy CMS | planned |
| `/admin/page-settings` | `admin.page-settings.index` | JetPK page builder (staff allowed) | planned |
| `/admin/api-settings` | `admin.api-settings` | Suppliers | planned |
| `/admin/markups` | `admin.markups` | module | planned |
| `/admin/promo-codes` | `admin.promo-codes.index` | | planned |

## Operations modules

| Route | Name | Testdash |
|-------|------|----------|
| `/admin/group-ticketing` | `admin.group-ticketing.index` | planned |
| `/admin/group-bookings` | `admin.group-bookings.index` | planned |
| `/admin/support/tickets` | `admin.support.tickets.index` | planned |
| `/admin/system-health` | `admin.system-health` | planned |
| `/admin/deployment-checklist` | `admin.deployment-checklist` | planned |

## Staff portal pages (`/staff`, `account.type:staff`)

| Route | Name | Controller | View | Data | Testdash |
|-------|------|------------|------|------|----------|
| `/staff` | `staff.dashboard` | `Staff\DashboardController@index` | `client_view('index','staff')` | Assigned KPIs + `AgencyDashboardService` counts | planned (same shell) |
| `/staff/bookings` | `staff.bookings.index` | `Staff\BookingController@index` | staff bookings | Full page list | planned |
| `/staff/bookings/{booking}` | `staff.bookings.show` | `@show` | show | Booking ops | planned |
| `/staff/reports` | `staff.reports.index` | `ReportsController@index` | reports | `StaffPermission::ReportsView` | planned |
| `/staff/support/tickets` | `staff.support.tickets.index` | `SupportTicketController@index` | tickets | support module | planned |

## Navigation source

JetPK sidebar: [`resources/views/themes/admin/jetpakistan/partials/sidebar.blade.php`](../../resources/views/themes/admin/jetpakistan/partials/sidebar.blade.php) — `PlatformModuleGate` visibility, not a PHP config array.

Legacy fallback: [`resources/views/layouts/partials/dashboard-sidebar-admin.blade.php`](../../resources/views/layouts/partials/dashboard-sidebar-admin.blade.php).
