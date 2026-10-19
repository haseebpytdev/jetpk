# FINAL-NONLIVE-HARDENING-CLOSURE

**Loops:** JP-OPS-READINESS-CONSOLIDATED-HARDENING-01 + **-02** (PR #106 blockers)  
**Branch:** `hardening/jp-final-nonlive-closure`  
**Protected main base:** `d279511c6aefd2fec9c82caade42bbbb9f146245`  
**PR:** #106  
**Mode:** CODE + TEST — **NO DEPLOY / NO MERGE**

## Absolute mutation assertions

| Assertion | Value |
|-----------|-------|
| PRODUCTION_FILES_DEPLOYED | 0 |
| PRODUCTION_DATABASE_MUTATIONS | 0 |
| PRODUCTION_BOOKINGS_MUTATED | 0 |
| PRODUCTION_PAYMENTS_MUTATED | 0 |
| PRODUCTION_WALLETS_MUTATED | 0 |
| PRODUCTION_LEDGERS_MUTATED | 0 |
| PRODUCTION_SUPPLIER_ROWS_MUTATED | 0 |
| PUBLIC_STORAGE_MUTATED | NO |
| AIRBLUE_EXTERNAL_NETWORK_CALLS | 0 |
| SABRE_PNR_CREATE_CALLS | 0 |
| SUPPLIER_BOOKING_CALLS | 0 |
| TICKETING_CALLS | 0 |
| LIVE_PAYMENT_CALLS | 0 |

## HARDENING-02 blocker closures

### Blocker 1 — strict staff mutation gates

`BookingPolicy::update` and `submitPaymentProof` now use `hasExplicitStaffPermission()` (fail-closed). Legacy staff with missing/null/malformed/empty `meta.staff_permissions` are **DENY**.

```text
BOOKING_UPDATE_LEGACY_STAFF_WITHOUT_PERMS=DENY
PAYMENT_PROOF_LEGACY_STAFF_WITHOUT_PERMS=DENY
AUTHORIZED_STAFF_UPDATE=PASS
AUTHORIZED_STAFF_PAYMENT_PROOF=PASS
PROD_STAFF_EXPLICIT_PERMISSION_RECONCILIATION_REQUIRED=YES
```

Prod staff accounts lacking explicit permission metadata must be inventoried **read-only** before deploy; no account mutation in this loop.

### Blocker 2 — communication actionable scope

Age alone no longer hides non-noise queued rows. Known login/auth noise remains non-actionable; old booking/payment queued rows stay actionable.

```text
OLD_NON_NOISE_QUEUED_REMAINS_ACTIONABLE=YES
KNOWN_LOGIN_NOISE_ACTIONABLE=NO
```

### Blocker 3 — QA money KPI exclusion

Weekly `gross_sales` now uses `QaFixtureBookingScope`. Daily/monthly money paths already excluded.

```text
DAILY_QA_MONEY_EXCLUDED=YES
WEEKLY_QA_MONEY_EXCLUDED=YES
MONTHLY_QA_MONEY_EXCLUDED=YES
PRODUCTION_FINANCIAL_ROWS_MUTATED=0
```

### Blocker 4 — independent failed_jobs alert

`OpsHealthAlertTriggers` + `jetpk:ops-health-alert --disk-level --failed-jobs` fire queue_failure when `failed_jobs>0` even if disk is NORMAL. Disk HIGH/CRITICAL still independent. Opt-in; non-fatal; no job retry/replay; no customer destinations. App/supplier/security external sources remain unwired.

```text
FAILED_JOBS_INDEPENDENT_ALERT_PATH=PASS
HISTORICAL_JOB_REPLAY=NO
CUSTOMER_ALERT_DESTINATION=NO
OPS_DEF_0022_STATUS=PARTIAL_EXTERNAL_CONFIG
```

## Test evidence (HARDENING-02)

| Suite | Result |
|-------|--------|
| Targeted (policy/comms/ops/qa/pricing/support + Promo + AgentStaff + StaffPermission) | **80 passed / 202 assertions** |
| Artifact | `_phpunit-hardening-02.txt` + `.xml` |
| PHP syntax | PASS |
| Frontend `tsc --noEmit` | PASS |
| Frontend lint | PASS (pre-existing warnings only) |

## Defect status matrix

| DEFECT | STATUS |
|--------|--------|
| OPS-DEF-0009 | CLOSED |
| OPS-DEF-0010 | CLOSED |
| OPS-DEF-0026 | CLOSED |
| OPS-DEF-0025 | CLOSED |
| OPS-DEF-0003 | CLOSED |
| OPS-DEF-0021 | CLOSED_HISTORICAL_RESIDUAL |
| OPS-DEF-0027 | CLOSED_HISTORICAL_QA_DATA |
| OPS-DEF-0020 | CLOSED_SAFE_AS_IS |
| OPS-DEF-0022 | PARTIAL_EXTERNAL_CONFIG |
| OPS-DEF-0024 | FIX_READY_PRODUCTION_ROLE_RECONCILIATION_PENDING |
| OPS-DEF-0011 | SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE |

## Go-live gates (unchanged)

```text
SABRE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
AIRBLUE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
PRODUCTION_OPERATIONAL_READY=NO
AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED=NO
```
