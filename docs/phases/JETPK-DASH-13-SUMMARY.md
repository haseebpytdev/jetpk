# JETPK-DASH-13 — Admin/Staff Production Cutover

## Phase

JETPK-DASH-13 — Admin/Staff production cutover, role routing, legacy dashboard retirement

## Branch

`phase/jetpk-dash-13-admin-staff-production-cutover`

## Starting HEAD

`903d9b5`

## Objective

Mount the Next.js back-office dashboard at `/admin/dashboard` and `/staff/dashboard`, retire `/testdash` and legacy Blade entry routes, preserve Agent/Customer dashboards.

## Route audit summary

| Route | Controller | Middleware | Final behavior |
|-------|------------|------------|----------------|
| `/admin/dashboard/*` | `BackOfficeDashboardController@admin` | web, auth, agency, platform_admin | Next.js shell (static or proxy) |
| `/staff/dashboard/*` | `BackOfficeDashboardController@staff` | web, auth, agency, staff | Next.js shell |
| `/admin` | redirect | same as admin group | → `/admin/dashboard` |
| `/staff` | redirect | same as staff group | → `/staff/dashboard` |
| `/dashboard` | `DashboardRedirectController` | auth | Role-based redirect |
| `/testdash/*` | `BackOfficeDashboardController@testdashRedirect` | web | Login or role redirect |
| `/agent` | unchanged | agent | Agent Blade dashboard |
| `/customer` | unchanged | customer | Customer dashboard |

## Next.js strategy

- Shared app at `dashboard/app/[portal]/dashboard/*` (`admin` \| `staff`)
- No `basePath`; canonical URLs `/admin/dashboard/*`, `/staff/dashboard/*`
- Production: `next build` + `next start` on port 3001; Laravel proxies after auth (`config/dashboard.php`)
- Internal navigation uses `useDashboardRouter` / `DashboardLink` for portal-prefixed paths

## Local validation

| Gate | Result |
|------|--------|
| Typecheck | Pass |
| Lint | Pass |
| Build | Pass (89 prerendered routes incl. admin+staff) |
| Laravel API tests | 30/30 (232 assertions) |
| Back-office routing tests | 12/12 |
| Role verification tests | 5/5 |
| **Laravel total (dashboard-related)** | **47/47, 270 assertions** |
| Targeted Playwright (cutover gate) | **102/102, retries=0** |
| Full Playwright suite (397 baseline) | **Deferred** — rerun `npx playwright test -c playwright.reuse.config.ts --retries=0` before live deploy |

## `/testdash` disposition

Laravel redirect only; not a public preview mount.

## Legacy disposition

`/admin` and `/staff` Blade dashboard entries retired (redirect). Controllers/views retained for rollback.

## Security

- Dashboard API remains GET\|HEAD only (38 routes)
- No localStorage auth tokens
- Sabre cancellation gates unchanged (not modified in this phase)

## Live deployment

**Not executed in this session** — SFTP/server commands documented in `docs/dashboard/DASHBOARD-PRODUCTION-DEPLOYMENT.md`.

## Known limitations

1. Full 397-test Playwright suite not rerun in this pass (targeted 102-test cutover gate passed).
2. Static HTML export deferred (searchParams on module pages); production uses Laravel auth proxy to `next start`.
3. Main integration and live verification pending operator deploy.

## Final status

**LOCAL IMPLEMENTATION COMPLETE** — live cutover and main merge pending deployment verification.

## JP-FE-01

Not started.
