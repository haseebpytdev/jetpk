# Wave A — Authenticated Continuation — Exit

```
BRANCH=ops/wave-a-authenticated-continuation
COMMIT=9f2766952090026e789c7e5325bc95c97b6edca9
PR_CREATE_URL=https://github.com/haseebpytdev/jetpk/pull/new/ops/wave-a-authenticated-continuation
PR_STATUS=PUSHED_GH_UNAUTHENTICATED
PROTECTED_MAIN_SHA=a136337226f3ece40c90f90971e29e1768d4618b
PRODUCTION_RUNTIME_SHA=a138eb570b05910046df7fb251beeef839b25395
LIVE_DASHBOARD_BUILD_ID=7-iZf4RbaJmGpuDjO1tsh
EXIT_AT_UTC=2026-10-14T23:25:00Z
WAVE_A_COMPLETE=PARTIAL
PRODUCTION_OPERATIONAL_READY=NO
LOOP_STATUS=STOPPED
```

## Exit gate

```
QA_AUTH_STATES_AVAILABLE=YES_SERVER_SIDE
QA_ROLES_AUTHENTICATED=PLATFORM_ADMIN,STAFF,AGENCY_OWNER,MANAGER,ACCOUNTANT,SUPPORT_STAFF,TICKETING_STAFF,VIEWER,SALES_AGENT,CUSTOMER,AGENT_B_OWNER
BROWSER_PLAYWRIGHT_AUTH=BLOCKED_NO_PASSWORD_ENV
A2_AUTHORIZATION_MATRIX_COMPLETE=YES_CAPABILITY_LEVEL

OJ_B2B_MGR_001=PARTIAL
OJ_B2B_ACC_001=PARTIAL_BLOCKED_KNOWN_DEFECT_OPS_DEF_0006
OJ_B2B_SUP_001=PARTIAL
OJ_STAFF_001=PARTIAL
OJ_B2B_OWN_001=PARTIAL
OJ_B2C_001=PASS_TO_SAFE_BOUNDARY

OPS_DEF_0004_STATUS=OPEN_VERIFIED_POLICY (DENY on QA booking due to agent_id mismatch; ALLOW if agent_id matched — BookingsView-only)
OPS_DEF_0006_STATUS=OPEN_CONFIRMED
OPS_DEF_0007_STATUS=OPEN (live duplicates=0)
OPS_DEF_0013_STATUS=OPEN
OPS_DEF_0016_STATUS=OPEN (Sabre book+live_call ON; ticketing OFF; others OFF/MISSING)
OPS_DEF_0017_STATUS=OPEN (ABHIPAY_* MISSING; callback route ROUTE_FOUND)
OPS_DEF_0018_STATUS=PARTIAL (critical journeys reclassified off BLOCKED_NO_QA_SESSION)
OPS_DEF_0021_STATUS=OPEN (178 queued; 0 jobs; disposition recommended MARK_FAILED_HISTORICAL / MARK_SKIPPED; no mutation this loop)
OPS_DEF_0024_STATUS=OPEN_NEW (legacy jp-dash-03-qa-agent-staff over-permissioned vs SupportStaff)

FAILED_JOBS_POST=0
PENDING_DEFAULT_POST=0
PENDING_NOTIFICATION_QUEUES_POST=0
DEFAULT_WORKER_RUNNING_POST=NO
DISK=46% used (~52G free)

FRONTEND_REGRESSION=NO
HTTP=/up:200 /:200 /login:200 /admin:302 /agent:200
BUILD_ID_UNCHANGED=YES

SUPPLIER_MUTATIONS=0
PAYMENT_MUTATIONS=0
WALLET_MUTATIONS=0
HISTORICAL_NOTIFICATION_REPLAY=0
OPS_DEF_0020_TOUCHED=NO
```

## Supplier flags (read-only)

| Flag | Value |
|------|-------|
| SABRE_BOOKING_ENABLED | TRUE |
| SABRE_BOOKING_LIVE_CALL_ENABLED | TRUE |
| SABRE_TICKETING_ENABLED | FALSE |
| SABRE_PUBLIC_TICKETING_ENABLED | FALSE |
| SABRE_CHECKOUT_AUTO_TICKETING_ENABLED | FALSE |
| SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED | FALSE |
| AIRBLUE_OTA_V3_LIVE_ENABLED | MISSING |
| ONE_API_LIVE_BOOKING_ENABLED | MISSING |
| ALHAIDER_BOOKING_ENABLED | FALSE |

## AbhiPay (read-only)

| Check | Result |
|-------|--------|
| ABHIPAY_ENABLED / MODE / MERCHANT_ID / API_KEY | MISSING in env probe |
| Callback named route | ROUTE_FOUND (`payments.abhipay.callback`) |
| Live charge | NOT_EXECUTED |

## OPS-DEF-0021 disposition recommendation

| Metric | Value |
|--------|-------|
| COMM_QUEUED | 178 |
| jobs pending | 0 |
| WITH_PROVIDER_REF | 0 |
| WITH_SENT_AT | 0 |
| oldest | 2026-09-11 |
| newest | 2026-10-14 10:47:47 |

Top events: `admin_login_success` (80), `staff_login_success` (31), `auth_new_device_login` (20), booking/payment related remainder.

Recommended later mutation pass (not this loop):

- Login/device noise → `MARK_SKIPPED` or `MARK_FAILED_HISTORICAL`
- Booking/payment events without delivery proof → `MANUAL_REVIEW` then `MARK_FAILED_HISTORICAL`
- Do **not** resend

## Evidence paths

- `docs/evidence/jp-ops-readiness-01/WAVE-A-AUTH-STATE-INVENTORY.md`
- `docs/evidence/jp-ops-readiness-01/WAVE-A-AUTHORIZATION-MATRIX.csv`
- `docs/evidence/jp-ops-readiness-01/WAVE-A-AUTHENTICATED-JOURNEYS.csv`
- `docs/evidence/jp-ops-readiness-01/WAVE-A-AUTHENTICATED-EXIT.md`
- `docs/evidence/jp-ops-readiness-01/_raw-wavea-policy-probe.json`
- Probe scripts: `_wavea-auth-*.php`, `_wavea-mysql-*.sh` (no secrets)

## Next remediation priority (no code in this loop)

1. **OPS-DEF-0006** — atomic wallet approve + ledger  
2. **OPS-DEF-0007** — agency-wallet uniqueness architecture  
3. **OPS-DEF-0013** — booking_payments gateway uniqueness  
4. **OPS-DEF-0004** — Viewer cancel must require stronger than BookingsView  
5. Restore `JP_DASH_03_QA_*_PASSWORD` (ops secret inject) for browser depth  
6. **OPS-DEF-0016 / 0017** — activation certification (manual)

## Stop conditions honored

No supplier booking, PNR, ticketing, payment, refund, wallet debit/credit, deposit approve/reject, customer cancel, historical email resend, `public/storage` touch, or arbitrary user creation.
