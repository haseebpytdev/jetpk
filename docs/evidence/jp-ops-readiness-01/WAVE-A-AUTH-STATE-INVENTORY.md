# Wave A — Authenticated Continuation — QA Auth State Inventory

```
PROBED_AT_UTC=2026-10-14T23:15:00Z
PROTECTED_MAIN_SHA=a136337226f3ece40c90f90971e29e1768d4618b
PRODUCTION_RUNTIME_SHA=a138eb570b05910046df7fb251beeef839b25395
LIVE_DASHBOARD_BUILD_ID=7-iZf4RbaJmGpuDjO1tsh
AUTH_METHOD_USED=loginUsingId_server_side
BROWSER_PLAYWRIGHT_AUTH=BLOCKED_NO_PASSWORD_ENV
QA_AUTH_STATES_AVAILABLE=YES_SERVER_SIDE
```

## Decision (Phase 1–2)

1. Reused existing production QA accounts from JP-DASH-03 and JP-FINAL-11 certification tooling.
2. Did **not** create new production users.
3. Browser/Playwright `storageState` regeneration blocked: `JP_DASH_03_QA_*_PASSWORD` keys absent/empty in production `.env` (presence checked only; values never printed).
4. Server-side `Auth::loginUsingId` against existing QA user IDs succeeded for all required roles.
5. No arbitrary account provisioning performed.

## Infrastructure sources

| Source | Role |
|--------|------|
| `App\Console\Commands\JetpkDashboardProdCertQaCommand` | JP-DASH-03 QA markers / lifecycle |
| `App\Console\Commands\JetpkDashboardFinal11QaCommand` | FINAL-11 role staff (Manager/Accountant/Support/Viewer/…) |
| AgencyRole templates + `AgentPermission` | Capability expectations |
| `BookingCancellationPolicy@request` | Viewer cancel authorization |

## Password / session generator inventory (no secrets)

| KEY | PRESENT_IN_DOTENV | NON_EMPTY | NOTES |
|-----|-------------------|-----------|-------|
| JP_DASH_03_QA_ADMIN_PASSWORD | NO | NO | Blocks browser auth |
| JP_DASH_03_QA_STAFF_PASSWORD | NO | NO | Blocks browser auth |
| JP_DASH_03_QA_AGENT_PASSWORD | NO | NO | Blocks browser auth |
| JP_DASH_03_QA_AGENT_STAFF_PASSWORD | NO | NO | Blocks browser auth |
| JP_DASH_03_QA_CUSTOMER_PASSWORD | NO | NO | Blocks browser auth |

`SESSION_GENERATOR_AVAILABLE=YES` (cert commands exist) but `PASSWORD_SOURCE_AVAILABLE=NO` on production.

## Role inventory

| QA_ROLE | ACCOUNT_MARKER | ACCOUNT_EXISTS | PASSWORD_SOURCE_AVAILABLE | SESSION_GENERATOR_AVAILABLE | SAFE_TO_USE | NOTES |
|---------|----------------|----------------|---------------------------|-----------------------------|-------------|-------|
| PLATFORM_ADMIN | jp-dash-03-qa-admin | YES | NO | YES | YES_SERVER_SIDE | loginUsingId PASS |
| STAFF | jp-dash-03-qa-staff | YES | NO | YES | YES_SERVER_SIDE | 14 staff permissions |
| AGENCY_OWNER | jp-dash-03-qa-agent | YES | NO | YES | YES_SERVER_SIDE | agency_id=4 agent_id=4 |
| SUPPORT_STAFF_LEGACY | jp-dash-03-qa-agent-staff | YES | NO | YES | CAUTION | Over-permissioned vs SupportStaff template |
| CUSTOMER | jp-dash-03-qa-customer | YES | NO | YES | YES_SERVER_SIDE | |
| MANAGER | jp-final-11-qa-staff-manager | YES | NO | YES | YES_SERVER_SIDE | agency_id=4 |
| ACCOUNTANT | jp-final-11-qa-staff-accountant | YES | NO | YES | YES_SERVER_SIDE | |
| SUPPORT_STAFF | jp-final-11-qa-staff-support | YES | NO | YES | YES_SERVER_SIDE | Correct narrow perms |
| TICKETING_STAFF | jp-final-11-qa-staff-ticketing | YES | NO | YES | YES_SERVER_SIDE | |
| VIEWER | jp-final-11-qa-staff-viewer | YES | NO | YES | YES_SERVER_SIDE | BookingsView+AgencyView only |
| SALES_AGENT | jp-final-11-qa-staff-sales | YES | NO | YES | YES_SERVER_SIDE | |
| AGENT_B_OWNER | jp-final-11-qa-agent-b | YES | NO | YES | YES_SERVER_SIDE | Cross-agency scope control (agency_id=5) |

## Auth results (server-side)

All listed roles: `LOGIN=PASS_SERVER_SIDE`, `SESSION_VALID=YES` (in-process), `ROLE_CONTEXT_VALID=YES` for FINAL-11 templates.

Browser session cookies/storageState: **not generated** (password env missing).

## Safety

- No supplier/payment/wallet mutations.
- No new production users.
- No secrets/cookies/tokens written to evidence.
