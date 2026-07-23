# Admin Route Inventory

Phase: **JETPK-DASH-01**  
Generated from: [`routes/admin.php`](../../routes/admin.php), [`routes/admin-page-settings.php`](../../routes/admin-page-settings.php), [`bootstrap/app.php`](../../bootstrap/app.php)

## Registration

| Surface | URL prefix | Name prefix | Middleware (outer) |
|---------|------------|-------------|-------------------|
| Platform admin | `/admin` | `admin.*` | `web`, `auth`, `agency.context`, `account.type:platform_admin` |
| Page settings (shared) | `/admin/page-settings` | `admin.page-settings.*` | `web`, `auth`, `agency.context`, `account.type:platform_admin,staff` |

There is **no** `routes/api.php`. Admin JSON endpoints are authenticated **web** routes.

Approximate declarative route count: **~224** in `admin.php`, **~11** in `admin-page-settings.php`.

## Module gates (`platform.module:*`)

Seen on admin routes: `supplier_booking`, `ticketing`, `agent_deposits`, `agent_applications`, `markup_settings`, `api_settings`, `finance_reports`, `branding_settings`, `notifications`, `support_system`, `public_umrah_groups` (group ticketing visibility via `PlatformModuleGate` in nav).

## Route groups (summary)

| Group | Approx. routes | Named prefix examples |
|-------|----------------|----------------------|
| Dashboard | 1 | `admin.dashboard` |
| Customers | 3 | `admin.customers.*` |
| Bookings & ops | ~43 | `admin.bookings`, `admin.bookings.*` |
| Commissions | 7 | `admin.commissions.*` |
| Agent deposits | 5 | `admin.agent-deposits.*` (module) |
| Users | 10 | `admin.users.*` |
| Agencies | 6 | `admin.agencies.*` |
| Agents | 6 | `admin.agents`, `admin.agents.*` |
| Agent applications | 8 | `admin.agent-applications.*` (module) |
| Staff directory | 1 | `admin.staff` |
| CMS pages | 8 | `admin.cms-pages.*` |
| Promo codes | 6 | `admin.promo-codes.*` |
| Markups | 7 | `admin.markups*` (module) |
| API / suppliers | 8 | `admin.api-settings*` (module) |
| Finance / ledger | ~26 | `admin.ledger.*`, `admin.accounting.*`, `admin.finance.*`, `admin.reports*` (module) |
| Settings hub | ~46 | `admin.settings.*`, `admin.branding` |
| Communications | subset | `admin.settings.communications.*` (module) |
| System | 3 | `admin.system-health`, `admin.deployment-checklist`, `admin.go-live-checklist` |
| Group ticketing | ~16 | `admin.group-ticketing.*` |
| Group bookings | 6 | `admin.group-bookings.*` |
| Support | 6 | `admin.support.tickets.*` (module) |
| Roles matrix | 1 | `admin.roles-permissions` |
| Page settings | ~11 | `admin.page-settings.*` (separate file) |

## Web AJAX / JSON (not REST API)

| Route name | Method | URI pattern | Controller |
|------------|--------|-------------|------------|
| `admin.bookings.data` | GET | `/admin/bookings/data` | `BookingManagementController@data` |
| `admin.bookings.suggestions` | GET | `/admin/bookings/suggestions` | `BookingManagementController@suggestions` |
| `admin.bookings.preview` | GET | `/admin/bookings/{booking}/preview` | `BookingManagementController@preview` |
| `admin.agents.data` | GET | `/admin/agents/data` | `AdminSectionController@agentsData` |
| `admin.agents.suggestions` | GET | `/admin/agents/suggestions` | `AdminSectionController@agentsSuggestions` |
| `admin.agents.search` | GET | `/admin/agents/search` | alias of suggestions |
| `admin.agents.preview` | GET | `/admin/agents/{agent}/preview` | `AdminSectionController@agentPreview` |
| `admin.agent-applications.data` | GET | `/admin/agent-applications/data` | `AgentApplicationController@data` |
| `admin.agent-applications.suggestions` | GET | `/admin/agent-applications/suggestions` | `AgentApplicationController@suggestions` |
| Logo background / branding | POST/GET | `/admin/settings/branding/logo-background/*` | `BrandingLogoBackgroundController` |

## Staff portal contrast

Staff booking list does **not** expose `bookings.data` AJAX in [`routes/staff.php`](../../routes/staff.php). Staff uses full-page routes under `/staff/bookings`.

## `/testdash` mapping

| Laravel area | Next status (DASH-01) |
|--------------|----------------------|
| `admin.dashboard` | **Implemented** — overview at `/testdash` |
| All other `admin.*` GET pages | **Planned** — nav stub only |

See [`docs/dashboard/dashboard-page-map.md`](../dashboard/dashboard-page-map.md) for page-level detail.
