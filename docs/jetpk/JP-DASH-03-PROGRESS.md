# JP-DASH-03 — Operational Back Office Progress

## PHASE

`JP-DASH-03` — Operational Admin/Staff back office

## CURRENT_STATUS

`IN_PROGRESS` — Checkpoint 2–3 operational dashboard + Checkpoint 5 Inter typography (local)

## LAST_UPDATED_UTC

2026-08-10T00:10:00Z

## CURRENT_COMMIT

`944fab6` — docs(jetpk): update JP-DASH-03 checkpoint 3 progress and remote push status

Prior: `b731396` feat(dashboard): add operational OTA summary and attention queues

## CURRENT_BLOCKERS

- Authenticated production browser acceptance requires live admin session (API/RBAC tests pass locally)
- Staff production browser: `AWAITING_EXISTING_SAFE_STAFF_ACCOUNT` (seeded `staff@ota.demo` used for API/RBAC only)

## NEXT_AUTONOMOUS_ACTION

Deploy checkpoint 2–3 batch; production authenticated admin DOM proof; sidebar/notification audit (checkpoint 4); visual/responsive matrix (checkpoint 6).

| Scope | SHA256 |
|-------|--------|
| GLOBAL | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| VHOST | `8da510a8f911d8d711658abd8a110b04309d6295cf513f9f7dce4efdd794a42a` |

## COMPLETED_GATES

- Phase branch from baseline `959e190a`
- Checkpoint 1 SSR/session/preview mode repair (accepted)
- `NEXT_PUBLIC_DASHBOARD_MODE=production` → live mode
- Laravel private origin + cookie forwarding (SSR + proxy)
- POST logout path
- Dashboard production build (local)
- Operational overview API structure (`DashboardOverviewResource` expansion)
- Global search API + debounced UI (`/api/dashboard/search`)
- Supplier status presenter (sanitized operational posture)
- Dashboard browser/client fetch split (build-safe)
- Inter typography: dashboard + frontend + `jetpk-typography-authority.css`

## ACTIVE_GATES

| Gate | Status |
|------|--------|
| `ADMIN_DASHBOARD_SERVER_RENDER` | API PASS; production browser pending |
| `DASHBOARD_REAL_SESSION_IDENTITY` | API PASS (`admin.name` not Preview) |
| `PREVIEW_AUTH_UI_REMOVED` | Live path clean; preview infra gated by mode |
| `DASHBOARD_DATABASE_LOGO_BINDING` | Checkpoint 1 preload; DOM proof pending |
| `ADMIN_OPERATIONAL_DASHBOARD` | Local build PASS; production pending |
| `STAFF_PRODUCTION_BROWSER_ACCEPTANCE` | `AWAITING_EXISTING_SAFE_STAFF_ACCOUNT` |
| `DASHBOARD_NOTIFICATION_STATE` | Not audited (no fixture badges in header) |
| `JP_DASH_03_SOURCE_PARITY` | Not run |
| `PUBLIC_DATABASE_LOGO_LIVE_DOM` | Not re-verified this pass |

## FAILED_GATES

None.

## CHECKPOINT_COMMITS

| Checkpoint | Commit | Remote push |
|------------|--------|-------------|
| 1 | `a86c89e` fix(dashboard): restore authenticated production rendering | PENDING |
| 1 docs | `c6cece9` docs(jetpk): update JP-DASH-03 checkpoint 1 production deploy status | PENDING |
| 2–3 | `b731396` feat(dashboard): add operational OTA summary and attention queues | see REMOTE_PHASE_PROGRESS |
| 5 typography | included in `b731396` | see REMOTE_PHASE_PROGRESS |

## ROOT_CAUSES (CHECKPOINT 1 — ACCEPTED)

1. `NEXT_PUBLIC_DASHBOARD_MODE=production` treated as preview.
2. Dashboard SSR did not forward cookies to private Laravel.
3. `BackOfficeDashboardController` did not forward `Cookie` to Next.
4. Preview header/sidebar active in production builds.

## CHECKPOINT 2–3 IMPLEMENTATION

### Laravel

- `DashboardOverviewResource` — booking pipeline, payment ops, support ops, supplier status, system health, bounded KPIs
- `DashboardOverviewController` — support alerts + supplier status injection
- `DashboardSearchService` + `DashboardSearchController` — RBAC search (bookings, customers, agents)
- `DashboardSupplierStatusPresenter` — Sabre live, PIA NDC configured, Al Haider pending, IATI inactive, One API deferred
- `routes/api-dashboard.php` — `GET /search`

### Dashboard Next

- Operational layout (`overview-page-content.tsx`) — KPI strip, attention queue, pipeline, recent bookings, payments, suppliers, system health
- `operational-dashboard-panels.tsx`, `global-search.tsx`
- `laravel-origin.ts`, `laravel-browser-client.ts` — client/server fetch separation (fixes build)
- Nav: removed misleading `PLANNED` badges on mature modules
- Preview gated to `preview` mode only (`dashboard-shell`, `data-source-notice`)

### Typography (Checkpoint 5 — partial)

- Frontend: removed Space Grotesk; Inter for all display via `--font-display: var(--font-body)`
- `public/css/jetpk-typography-authority.css` — Inter-only display stack
- Dashboard already Inter-only in `typography-tokens.css`

## WIDGET / SOURCE MATRIX (OPERATIONAL OVERVIEW)

| Widget | Source | Query / endpoint | Admin | Staff |
|--------|--------|------------------|-------|-------|
| Summary KPIs | Agency dashboard stats + command summary | `DashboardOverviewController` → agency dashboard service | permitted | staff-scoped |
| Needs attention | `needsAttention` queues | existing agency dashboard | permitted | staff RBAC |
| Booking pipeline | stats + operational KPIs | `DashboardOverviewResource::bookingPipeline` | permitted | staff-scoped |
| Recent bookings | `recentBookings` | bounded agency query | permitted | staff-scoped |
| Payment ops | `commandSummary` | payment_review, pending_deposits | permitted | staff-scoped |
| Support ops | support alerts service | open tickets backlog | permitted | staff-scoped |
| Supplier status | `DashboardSupplierStatusPresenter` | config + connection state | permitted | permitted |
| System health | `hasLiveData` + scheduler meta | read-only flags | permitted | permitted |
| Global search | `DashboardSearchService` | limited LIKE on bookings/customers/agents | permitted | staff-scoped |

## TEST_RESULTS

- `php artisan test tests/Feature/Dashboard/BackOfficeSessionContractTest.php tests/Feature/Dashboard/DashboardOverviewOperationalTest.php`: **14 passed, 45 assertions**
- `dashboard npm run typecheck`: pass
- `dashboard npm run build`: pass (after browser-client split)

## KNOWN_DEFERRED_ITEMS

- Production authenticated admin browser proof
- Staff production browser (`staff@ota.demo` exists in seeds for API only)
- Checkpoint 4: notification badge audit, full sidebar route matrix verification
- Checkpoint 6: responsive/zoom/a11y matrix, source parity, full OTA regression
- Frontend `npm run build` not run this pass

## ROLLBACK

Restore checkpoint 1 backup on server; redeploy prior dashboard `.next` + Laravel files from backup path.

## FINAL_STATUS

`IN_PROGRESS` — not `COMPLETE`
