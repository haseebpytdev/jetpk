# Admin and Staff Production Routing

## Canonical routes

| Portal | Entry | Nested example |
|--------|-------|----------------|
| Platform Admin | `/admin/dashboard` | `/admin/dashboard/bookings` |
| Internal Staff | `/staff/dashboard` | `/staff/dashboard/bookings` |
| Generic resolver | `/dashboard` | Redirects by `AccountType` |

## Role resolution (`/dashboard`)

Laravel `DashboardRedirectController` delegates to `ClientRedirectResolver::dashboardPathForUser()`:

| Account type | Redirect target |
|--------------|-----------------|
| Unauthenticated | Login (`client_route('login')`) |
| `platform_admin` | `/admin/dashboard` |
| `staff` | `/staff/dashboard` |
| `agent`, `agent_staff` | `/agent` (existing agent dashboard) |
| `customer` | `/customer/bookings` |
| `agency_admin` | `/account/legacy` (403 legacy gate) |

Agent, Agent Staff, and Customer accounts **never** enter the back-office Next.js shell.

## Next.js routing strategy

- **Single shared implementation** under `dashboard/app/[portal]/dashboard/*`
- `portal` is `admin` or `staff` (validated in layout via `generateStaticParams`)
- **No `basePath`** — canonical URLs are `/admin/dashboard/*` and `/staff/dashboard/*`
- Shared components, hooks, and read-only API client; portal prefix controls navigation only
- Laravel remains authoritative for session auth and RBAC

## Production asset strategy

1. `npm run build:production` in `dashboard/` (standard Next.js build)
2. On server: `npm ci && npm run build && npm run start` (port **3001**, or `DASHBOARD_NEXT_SERVER_URL`)
3. Laravel `BackOfficeDashboardController` checks auth, then:
   - Serves static HTML from `storage/app/back-office-dashboard/` when an export is present (optional future path)
   - Otherwise **proxies** to the local Next.js server (`config/dashboard.php` → `DASHBOARD_NEXT_SERVER_URL`)
4. Shared client assets are served by Next.js (`/_next/static/*`)

Module pages use `searchParams` for filters; static export is deferred until those routes are client-hydrated for export compatibility.

## Staff permissions

Staff module visibility and API access use existing `DashboardPermissionResolver` keys (`bookings.view`, `payments.view`, etc.) mapped from `StaffPermission` and Laravel gates. Unauthorized modules return API `403`; the UI must not present forbidden modules as usable.

## `/testdash` disposition

- Route: `GET /testdash/{path?}` → `BackOfficeDashboardController@testdashRedirect`
- Authenticated: redirects to role-appropriate dashboard
- Unauthenticated: redirects to login
- **Not** a public preview mount after cutover

## Legacy Blade dashboards

| Legacy route | Disposition |
|--------------|-------------|
| `GET /admin` | 302 → `/admin/dashboard` |
| `GET /staff` | 302 → `/staff/dashboard` |
| Blade `DashboardController@index` (admin/staff) | Retired from active routes; source retained for rollback |

Agent (`/agent`), Agent Staff, and Customer dashboards are unchanged.
