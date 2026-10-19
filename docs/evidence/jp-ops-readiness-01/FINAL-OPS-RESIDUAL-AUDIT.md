# FINAL-OPS-RESIDUAL-AUDIT

**Loop:** JP-FINAL-OPS-RESIDUAL-CLOSURE-01  
**Protected main:** `aac68754042c2b16aa6d543203859455e3dc6b07`  
**Production patch chain includes:** `final-nonlive-hardening@1fa2f4ab7edfe9a74fa038ed30114669fba3257d`  
**Mode:** READ-ONLY audit + evidence (no runtime code deploy; no job retry/delete)  
**Date (UTC):** 2026-10-19

## Absolute mutation assertions

| Assertion | Value |
|-----------|-------|
| AIRBLUE_EXTERNAL_NETWORK_CALLS | 0 |
| SABRE_PNR_CREATE_CALLS | 0 |
| SUPPLIER_BOOKING_CALLS | 0 |
| TICKETING_CALLS | 0 |
| LIVE_PAYMENT_CALLS | 0 |
| PRODUCTION_BOOKINGS_MUTATED | 0 |
| PRODUCTION_PAYMENTS_MUTATED | 0 |
| PRODUCTION_WALLETS_MUTATED | 0 |
| PRODUCTION_LEDGERS_MUTATED | 0 |
| PRODUCTION_SUPPLIER_ROWS_MUTATED | 0 |
| PRODUCTION_FINANCIAL_ROWS_MUTATED | 0 |
| FAILED_JOBS_DELETED | 0 |
| EXECUTE_RETRY | NO |
| PUBLIC_STORAGE_MUTATED | NO |

## Phase 1 — health

| Probe | Value |
|-------|-------|
| HTTP_UP | 200 |
| HTTP_HOME | 200 |
| HTTP_LOGIN | 200 |
| HTTP_LOOKUP | 200 |
| HTTP_AGENT | 200 |
| HTTP_ADMIN | 302 |
| DISK_USED_PERCENT | 44 |
| DISK_FREE_GB | 54 |
| FAILED_JOBS_COUNT | 8 |
| PENDING_DEFAULT_QUEUE | 0 |
| AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED | **NO** |

## Phase 2–3 — failed jobs forensics (sanitized)

| Field | Value |
|-------|-------|
| FAILED_JOBS_TOTAL | 8 |
| FAILED_JOBS_BY_QUEUE | `notifications-transactional`: 8 |
| FAILED_JOBS_BY_JOB_CLASS | `DeliverQueuedOperationalMail`: 8 |
| FAILED_JOBS_BY_EXCEPTION_CLASS | `UnexpectedResponseException`: 8 |
| FAILED_JOBS_SMTP_CODES (sanitized) | `550 5.1.1` recipient rejected: 5; `450 4.7.1` recipient deferred/rejected: 3 |
| FAILED_JOBS_BY_CATEGORY | SMTP/MAIL: 8 |

| FAILED_JOBS_OLDEST_AT | 2026-10-15 07:17:08 |
| FAILED_JOBS_NEWEST_AT | 2026-10-19 07:17:06 |
| FAILED_JOBS_CREATED_AFTER_FINAL_HARDENING_DEPLOY | **0** (hardening backup stamp `20261019T200928Z`) |
| FAILED_JOBS_RECURRING_SAME_ROOT_CAUSE | **YES** (daily ~07:17 cluster + one 2026-10-16 18:39 burst) |
| FAILED_JOBS_CUSTOMER_IMPACT_POSSIBLE | PARTIAL (operational-mail job class on transactional queue; no customer payload inspected) |
| FAILED_JOBS_FINANCIAL_IMPACT_POSSIBLE | NO |
| FAILED_JOBS_BOOKING_IMPACT_POSSIBLE | NO |
| JOB_ALREADY_COMPLETED_BY_OTHER_PATH | UNKNOWN |
| DUPLICATE_REPLAY_RISK | HIGH if blind retry |
| SAFE_RETRY_CANDIDATES | 0 (document only; requires separate SMTP diagnosis + approval) |
| EXECUTE_RETRY | NO |

Classification vs OPS-DEF-0015: these are **not** the Wave1B archived 147 historical SMTP rejects. They are a **new recurring operational-mail failure stream**. Do **not** reopen 0015. Track as **OPS-DEF-0029**.

## Phase 4 — queue architecture

| Field | Value |
|-------|-------|
| QUEUE_WORKERS_CURRENT | cron `queue:work database --stop-when-empty` covering `notifications-critical,notifications-transactional,notifications-ops,notifications-bulk` |
| DEFAULT_QUEUE_PENDING | 0 |
| PENDING_ALL_JOBS | 0 |
| FAILED_JOB_ACTIVE_STREAM | YES (recurring SMTP/MAIL for DeliverQueuedOperationalMail; not growing post-hardening deploy) |
| OPS_DEF_0019_REGRESSION | **NO** |

## Phase 5–6 — ops alerts

| Field | Value |
|-------|-------|
| OPS_EXTERNAL_ALERTS_ENABLED | false |
| OPS_ALERT_EMAIL_PRESENT | NO |
| OPS_ALERT_EMAIL_APPROVED | NO (no approved non-customer ops mailbox found in project config/docs) |
| OPS_ALERT_TRIGGER_LOGIC | **PASS** (0 triggers at NORMAL/0; queue_failure at NORMAL/1; disk_pressure at HIGH/0; both at HIGH/1) |
| EXTERNAL_EMAIL_SENT | 0 |
| OPS_DEF_0022_STATUS | **PARTIAL_EXTERNAL_CONFIG** |

No destination invented. No production env mutation.

## Phase 7 — OPS-DEF-0023 root-owned artifacts

| PATH | SIZE | OWNER | CLASS | ACTIVE_REFERENCE | SAFE_DELETE_CANDIDATE | CATEGORY |
|------|------|-------|-------|------------------|----------------------|----------|
| `/home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z` | 763M | root:root | REDUNDANT_BACKUP | NO | YES (sudo) | SAFE_OBSOLETE |
| `/home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z` | 681M | root:root | REDUNDANT_BACKUP | NO | YES (sudo) | SAFE_OBSOLETE |
| `/home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z` | 674M | root:root | REDUNDANT_BACKUP | NO | YES (sudo) | SAFE_OBSOLETE |
| `/home/pkjetp/releases/jetpk-20260930T080534Z` | 51M | root:root | STALE_RELEASE | NO | YES (sudo) | SAFE_OBSOLETE |

| Aggregate | Value |
|-----------|-------|
| ROOT_OWNED_TOTAL_GB | ≈2.17 (documented candidates above; prior 4.35G included large `/tmp` trees now largely gone) |
| ROOT_OWNED_SAFE_OBSOLETE_GB | ≈2.17 |
| SUDO_REQUIRED | **YES** |
| OPS_DEF_0023_STATUS | **OPEN** (no deletion executed; disk healthy at 44%/54G free) |

Prepared one-shot (NOT executed):

```bash
# Requires root/sudo authorization — do not run without explicit owner approval
sudo rm -rf /home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z \
  /home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z \
  /home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z \
  /home/pkjetp/releases/jetpk-20260930T080534Z
```

Prefer leaving disk healthy over cleanup risk.

## Phase 8 — guest token telemetry

Observation start: `20261017T120725Z`

| Field | Value |
|-------|-------|
| LEGACY_TELEMETRY_TOTAL_EVENTS | 0 |
| LEGACY_TELEMETRY_SUCCESS_EVENTS | 0 |
| LEGACY_TELEMETRY_DENIED_EVENTS | 0 |
| LAST_SUCCESSFUL_LEGACY_USAGE_AT | NONE |
| OBSERVATION_DURATION | ≈2.36 days |
| GUEST_TOKEN_SUNSET_DECISION | **KEEP_OBSERVATION_ACTIVE** |
| OPS_DEF_0011_STATUS | SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE |

No routes removed. No token/URL/IP/PII exposed.

## Phase 9 — legacy Staff account (internal ID only)

| Field | UID 4 (legacy empty) | UID 8 (explicit) |
|-------|----------------------|------------------|
| EXPLICIT_PERMS | NO (empty/null) | YES |
| POLICY_UPDATE / PROOF / VIEW | DENY / DENY / DENY | ALLOW / ALLOW / ALLOW |
| LAST_LOGIN_AT | 2026-08-31 | 2026-10-14 |

| Decision | Value |
|----------|-------|
| LEGACY_STAFF_ACCOUNT_ACTIVE | YES (account exists; not recently used for mutations) |
| LEGACY_STAFF_REQUIRED_FOR_MUTATIONS | **NO** |
| LEGACY_STAFF_SENSITIVE_MUTATIONS_DENIED | **YES** (fail-closed) |
| INTENDED_ROLE_DETERMINISTIC | NO authoritative preset found |
| STAFF_PERMISSION_ACTION_REQUIRED | **NO** |

Safe as intentional non-mutating / fail-closed. Do not auto-grant.

## Defect / risk reconciliation

| ID | Status |
|----|--------|
| OPS-DEF-0015 | CLOSED (historical Wave1B stream; not these 8 jobs) |
| OPS-DEF-0019 | CLOSED (no default-queue backlog regression) |
| OPS-DEF-0022 | PARTIAL_EXTERNAL_CONFIG |
| OPS-DEF-0023 | OPEN |
| OPS-DEF-0011 | SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE |
| OPS-DEF-0029 | **OPEN** (new) recurring `DeliverQueuedOperationalMail` SMTP `UnexpectedResponseException` |

## Readiness

| Field | Value |
|-------|-------|
| CURRENT_NON_DEFERRED_P0_BLOCKERS | NONE |
| CURRENT_NON_DEFERRED_P1_BLOCKERS | NONE |
| CURRENT_NON_DEFERRED_P2_BLOCKERS | OPS-DEF-0029 (SMTP operational mail stream); OPS-DEF-0022 (external alert mailbox); OPS-DEF-0023 (sudo cleanup optional) |
| PRODUCTION_OPERATIONAL_READY | **NO** |
| SABRE_LIVE_PNR | DEFERRED_GO_LIVE_GATE |
| AIRBLUE_LIVE_ZAPWAYS | DEFERRED_GO_LIVE_GATE |
| ABHIPAY_LIVE_CERT | DEFERRED_GO_LIVE_GATE |

## Next required action

1. Diagnose SMTP/`UnexpectedResponseException` for `DeliverQueuedOperationalMail` under separate approved change (no blind retry).  
2. Optionally approve a non-customer `OPS_ALERT_EMAIL` then enable external alerts.  
3. Optional sudo cleanup for OPS-DEF-0023 when authorized.  
4. Continue OPS-DEF-0011 observation (≥7–14 days recommended before sunset discussion).  

END
