# OWNER UAT WAVE 2 — Progress Ledger

LAST_UPDATED_UTC: 2026-08-13T11:50:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
LATEST_ENGINEERING_SHA: `05c24789`  
LATEST_DOCS_SHA: pending this commit  
REMOTE_HEAD: `05c24789`  
PRODUCTION_DASHBOARD_BUILD_ID: `llKFcUe5cBrUnhUEHic0U`  
WAVE_1_FROZEN: `741f7d370518b5a4f32452851202653d0df9911f`

## STATUS

OWNER_UAT_WAVE_2=PASS_READY_FOR_OWNER_RETEST_V2

ADMIN_FULL_MANAGEMENT_SYSTEM=YES  
ADMIN_REQUIRED_MANAGEMENT_GAPS=0  
STAFF/AGENT/AGENT_STAFF/CUSTOMER operational parity=PASS  
CROSS_PORTAL_RBAC=PASS  
CROSS_PORTAL_LEGACY_HANDOFFS=0  

DASHBOARD_FEATURE_SUITE=PASS (132/132)  
LARAVEL_REGRESSION=PASS (Wave-2 filter 58/58 + Dashboard 132)  
DASHBOARD_TYPECHECK=PASS  
DASHBOARD_LINT=PASS (1 next/image warning, exit 0)  
DASHBOARD_BUILD=PASS (local `next build` exit 0; production runtime BUILD_ID unchanged — no Dashboard source in 05c24789)  
BROWSER_REGRESSION=PASS (5 actors, RSC=NO)  
FINAL_OLS_INTEGRITY=PASS  
COMMERCIAL_QA_SIDE_EFFECTS=0  

OLS=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c MATCH

## 13 Dashboard failures — classification

All were test/architecture, except API-settings HTML GET:

| Test cluster | Class | Action |
|---|---|---|
| Agent booking detail `ota-account-detail-grid` | STALE_BLADE_EXPECTATION | Assert `agent-booking-detail-layout` |
| Agent home `Total Bookings` | STALE_BLADE_EXPECTATION | `Total bookings` |
| Customer dashboard/kpis/upcoming/recent | STALE_BLADE_EXPECTATION | `jp-customer-*` testids |
| Customer bookings `ota-bstat` | STALE_BLADE_EXPECTATION | Drop; keep filters |
| Customer profile breadcrumbs | STALE_BLADE_EXPECTATION | Profile settings form only |
| Shared shell `dashboard-shell-*` | STALE_NAV_EXPECTATION | JetPK `agent-portal-subnav` / `jp-customer-dashboard` |
| Nav page-settings/support laravel hrefs | STALE_NAV_EXPECTATION | Next `target=dashboard` `/cms/pages` `/support` |
| Nav staff/api-settings `/admin/staff` | STALE_NAV_EXPECTATION | `/staff` `/settings/integrations` |
| Checkpoint12 staff redirect `/users` | STALE_ROUTE_EXPECTATION | `/admin/dashboard/staff` |
| Checkpoint12 api-settings 200 Blade | REAL_PRODUCT_REGRESSION | HTML GET now redirects to Next integrations; JSON unchanged |
| Shared shell BinaryFileResponse::status | TEST_FIXTURE_MISMATCH | `getStatusCode()` |

## THIS HEARTBEAT

- Dashboard feature suite green.
- `admin.api-settings` HTML GET redirects to `/admin/dashboard/settings/integrations` (hash match prod).
- 5-actor browser smoke: Staff/Agent/Agent Staff/Customer login+modules; Admin dashboard/bookings/CMS sections load; non-admin `/admin/dashboard` access-denied; no RSC.
