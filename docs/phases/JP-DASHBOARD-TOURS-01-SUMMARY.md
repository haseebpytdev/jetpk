# JP-DASHBOARD-TOURS-01 — SUMMARY

## Phase name
JP-DASHBOARD-TOURS-01 — Role-aware first-use dashboard onboarding wizards

## Branch name
`phase/jp-dashboard-tours-01` (based on `phase/jp-flight-perf-01`; `claude/ui-master` lacks tracked `frontend/`/`dashboard/` trees)

## Objective
Add first-use guided tours for Customer, Agent, Staff, and Admin dashboards with Laravel-authoritative tour state in `users.meta.dashboard_tours`. Never mount on public homepage/search/booking/checkout.

## Included scope
- Persist tour completion/skip under `users.meta.dashboard_tours`
- Customer/Agent GET+PATCH JSON routes
- Admin/Staff GET+PATCH `/api/dashboard/tours`
- Lazy-loaded tour hosts in CustomerDashboardShell, AgentDashboardShell, DashboardShell only
- Permission-filtered Agent/Staff steps; Admin API Settings / Google OAuth mention
- Manual restart via “Take Dashboard Tour”
- Feature tests (IDOR, staff filtering, public exclusion, complete/skip/restart)

## Excluded scope
- Public homepage / search / booking / checkout surfaces
- Owner email dirty files
- Production deploy / push
- Live supplier or payment flows

## Investigation findings
- Portal JSON APIs already use session user + account-type middleware (IDOR-safe by omitting foreign user ids)
- Dashboard navigation is capability-driven; Staff tour steps must derive from that navigation

## Root causes
N/A (new capability)

## Exact files changed
See commit file list.

## Routes changed
- `customer.dashboard-tours.show` / `customer.dashboard-tours.update`
- `agent.dashboard-tours.show` / `agent.dashboard-tours.update`
- `api.dashboard.tours.show` / `api.dashboard.tours.update`

## Database changes
None (uses existing `users.meta` JSON)

## Backend changes
- `DashboardTourAuthority` (WIZARD_STATE_AUTHORITY)
- `DashboardTourCatalog` + `DashboardTourService`
- Three small tour controllers

## Frontend changes
- `frontend/features/dashboard-tours/*` (lazy host + SVG guide)
- Shell mounts + `data-tour-target` on portal nav
- Dashboard shell mount + sidebar targets + help restart

## Tests executed
```
php artisan test --filter=DashboardTourStateTest
```
Result: **7 passed**, 52 assertions

## Assertion counts
52

## Screenshots
Not captured in this pass (API + mount exclusion covered by feature tests)

## Responsive / accessibility
- Overlay dialog with labelled title; Previous/Next/Skip/Finish
- `prefers-reduced-motion` disables guide wave animation
- Missing targets skipped safely

## Known limitations
- Tour highlight is target-based (sidebar selectors); deep page widgets are not spotlighted
- Staff step set depends on live navigation permissions

## Risks
Low — opt-in UX; state isolated per authenticated user

## Rollback instructions
Revert the phase commit; no migrations to roll back.

## Commit SHA
(filled after commit)

## Final status
Implementation complete locally; **not pushed**. Do not report production FINAL until deploy review.
