# OWNER UAT WAVE 2 — Progress Ledger

LAST_UPDATED_UTC: 2026-08-12T18:20:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
LOCAL_HEAD: `8ed171d46ad69d76a8dcc0c8bc424386b87e9445`  
REMOTE_HEAD: `8ed171d46ad69d76a8dcc0c8bc424386b87e9445`  
WAVE_1_FROZEN: `741f7d370518b5a4f32452851202653d0df9911f` (`OWNER_UAT_WAVE_1=OWNER_ACCEPTED`)

## CURRENT_TASK

W2-14 Agent Deposits / W2-15 Markup discoverability (next), after batch 1–2 deploy.

## CURRENT_FINDING

- Failed notifications production classify: 74 QA SMTP 550 bounces to `jp-dash-03-qa-*` (no booking-linked).
- Dashboard build required client-safe fetches for Profile + Failures workspaces (server-only import trap).

## CURRENT_ROOT_CAUSE

Client components must not import `session-service` / `laravel-client` (pulls `server-only`).

## LATEST_TESTS

- DashboardMoneyPresenterTest: 9 passed
- dashboard tsc after profile fix: exit 0
- Production `next build`: BUILD_RC=0, BUILD_ID=`6Hm55uTJAuaVGYH5BdNH_`

## LATEST_PRODUCTION_PROOF

- OLS MATCH
- Laravel money presenter + communications route present on disk
- HTTP smoke (unauthenticated): `/admin/dashboard`, `/profile`, `/notifications/failures`, `/reports` → **307** (auth redirect; not 404/500)
- PM2 `jetpk-dashboard` restarted online

## DEPLOYED_BUILDS

- Dashboard Next BUILD_ID=`6Hm55uTJAuaVGYH5BdNH_`
- Laravel batch files extracted from Wave-2 tip through money/profile/failures work

## SOURCE_PARITY

Intended Laravel snippets verified on production (`formatDisplayLabel`, `communications/failures`). Full file-hash manifest still pending end-of-wave.

## OLS_HASH

MATCH `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## QA_AUTH_STATE

OTP temporary Owner-UAT remains; OTP_DEMO_* preserved; QA identities active.

## BLOCKERS

None. Parallel typography branch noise — ignore for Wave-2 business closure.

## NEXT_ACTION

1. Audit Agent Deposits + Markup domain/UI discoverability.
2. Settings IA + Support page size 10.
3. Users vs Staff compact table.
