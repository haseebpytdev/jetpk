# JP-FINAL-OPS-RESIDUAL-CLOSURE + AIRBLUE-ZAPWAYS-LIVE-CERT-PREFLIGHT

**Date (UTC):** 2026-10-22  
**JETPK_MAIN_SHA (branch base):** `5174959f7604af8c47c808aa64636ea8a9f3fb64`  
**DOCS_HEAD:** see git tip on `docs/jp-final-ops-residual-closure`  
**Production root:** `/home/pkjetp/jetpk_app`  
**Loop network:** `AIRBLUE_EXTERNAL_NETWORK_CALLS=0` (no Zapways HTTP)  
**Machine-captured verify:** `_raw-final-residual-verify-capture.txt` (UTC `2026-10-22T10:18:15Z`)

## Phase 0 — Baseline

| Field | Value |
|-------|-------|
| PROD_HTTP_HEALTH | `/up` `/login` `/lookup-booking` = 200 |
| DISK_USED_PERCENT (before cleanup) | 44 |
| DISK_FREE_GB (before cleanup) | 53.68 |
| FAILED_JOBS_TOTAL | 9 (historical baseline; unchanged) |
| DEFAULT_QUEUE_PENDING | 0 |
| NOTIFICATIONS_TRANSACTIONAL_PENDING | 0 |
| AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED | NO |

## Phase 1–3 — OPS-DEF-0022 external alerts

| Field | Value |
|-------|-------|
| OPS_ALERT_ROUTER_PRESENT | YES |
| OPS_ALERT_TRIGGER_LOGIC_PRESENT | YES |
| DISK_HEALTH_ALERT_WIRING_PRESENT (app script) | YES |
| DISK_HEALTH_CRON_PRESENT | YES (`15 */6 * * *` → `/home/pkjetp/bin/disk-health-check.sh`) |
| OPS_ALERT_EMAIL_CONFIGURED | NO |
| OPS_ALERT_DESTINATION_STATUS | BLOCKED_NEEDS_APPROVED_DESTINATION |
| OPS_EXTERNAL_ALERTS_ENABLED_FINAL | false / NO |
| OPS_ALERT_DRY_RUN_FINAL | false / NO (default; delivery not enabled) |
| OPS_DISK_ALERT_DELIVERED | NO (`log_only` / `no_destination`) |
| OPS_QUEUE_ALERT_DELIVERED | NO (`log_only` / `no_destination`) |
| OPS_ALERT_TRANSPORT_ERRORS | 0 (no send attempted) |
| OPS_DEF_0022_STATUS | **PARTIAL_EXTERNAL_CONFIG** |

Ops performed (no secrets):

- Confirmed cron script previously lacked `jetpk:ops-health-alert` wiring (`BIN_HAS_ALERT=0`).
- Synced `/home/pkjetp/bin/disk-health-check.sh` from deployed `scripts/jetpk/disk-health-check.sh` (backup `disk-health-check.sh.bak-*`).
- Controlled disk-health run: `LEVEL=NORMAL`, exit 0; wiring now `BIN_HAS_ALERT=1`.
- Dry-run artisan probes for disk + queue: channel `log_only`, reason `no_destination`.
- No `OPS_ALERT_EMAIL` invented. No customer mail. No `.env` alert enablement.

## Phase 4 — Rejected AGENCY_ADMIN mailbox

| Field | Value |
|-------|-------|
| BAD_MAILBOX_HASH | 9755902a7672 |
| MATCHING_USER_COUNT | 1 |
| ACCOUNT_TYPE | agency_admin |
| STATUS | active |
| AGENCY_ID_OR_HASH | 3 |
| SAME_AGENCY_OTHER_USERS | 0 |
| AGENCY email columns | ABSENT (`email`/`contact_email`/`billing_email`/…) |
| AUTHORITATIVE_REPLACEMENT_FOUND | NO |
| REPLACEMENT_SOURCE_FIELD | n/a |
| AGENCY_ADMIN_MAILBOX_UPDATED | NO |
| NEW_ADDRESS_SUPPRESSED | n/a |
| OLD_ADDRESS_HISTORICAL_SUPPRESSION_PRESERVED | YES (OPS-DEF-0029 runtime; not mutated) |
| CUSTOMER_EMAIL_SENT | 0 |
| AGENCY_ADMIN_MAILBOX_STATUS | **BLOCKED_NEEDS_OPERATOR_EMAIL** |

No email field updated. No customer/agency mail sent.

## Phase 5–6 — OPS-DEF-0023 root-owned cleanup

Documented obsolete candidates (re-proven, then deleted as root):

| CANDIDATE | SIZE | OWNER | SAFETY_CLASS | AFTER |
|-----------|------|-------|--------------|-------|
| `/home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z` | 763M | root:root | PROVEN_OBSOLETE | ABSENT |
| `/home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z` | 681M | root:root | PROVEN_OBSOLETE | ABSENT |
| `/home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z` | 674M | root:root | PROVEN_OBSOLETE | ABSENT |
| `/home/pkjetp/releases/jetpk-20260930T080534Z` | 51M | root:root | PROVEN_OBSOLETE | ABSENT |

| Aggregate | Value |
|-----------|-------|
| ROOT_OWNED_OBSOLETE_COUNT_BEFORE | 4 |
| ROOT_OWNED_OBSOLETE_BYTES_BEFORE | 2268391007 (~2.11 GiB) |
| ROOT_OWNED_OBSOLETE_DELETED_COUNT | 4 |
| ROOT_OWNED_OBSOLETE_DELETED_BYTES | 2268391007 |
| ROOT_OWNED_OBSOLETE_COUNT_AFTER | 0 |
| ROOT_OWNED_OBSOLETE_BYTES_AFTER | 0 |
| DISK_USED_PERCENT_AFTER | 42 |
| DISK_FREE_GB_AFTER | 55.79 |
| OPS_DEF_0023_STATUS | **CLOSED** |

Other root-owned paths under `/home/pkjetp/backups` and `/home/pkjetp/releases` remain (recent deploy/rollback material, AI runtime release trees, runtime `/tmp` service dirs). They were **not** in the documented OPS-DEF-0023 obsolete set and were left untouched.

Forbidden broad deletes were not used.

## Phase 7 — Post-ops regression

| Field | Value |
|-------|-------|
| PROD_HTTP_HEALTH | 200 / 200 / 200 |
| FAILED_JOBS_TOTAL | 9 |
| FAILED_JOBS_RETRIED | 0 |
| FAILED_JOBS_DELETED | 0 |
| HISTORICAL_NOTIFICATION_REPLAY | 0 |
| DEFAULT_QUEUE_PENDING | 0 |
| NOTIFICATIONS_TRANSACTIONAL_PENDING | 0 |
| SUPPLIER_CALLS | 0 |
| PAYMENT_CALLS | 0 |
| BOOKING_MUTATIONS | 0 |
| FINANCIAL_MUTATIONS | 0 |
| EXCEPTION_ALLOWED_DATA_MUTATION | none (mailbox blocked) |

## Phases 8–12 — AirBlue/Zapways ZERO-NETWORK preflight

| Field | Value |
|-------|-------|
| AIRBLUE_CONNECTION_PRESENT | YES |
| AIRBLUE_CONNECTION_COUNT | 1 |
| AIRBLUE_CONNECTION_ACTIVE | YES |
| AIRBLUE_ENVIRONMENT | sandbox |
| AIRBLUE_PROTOCOL_VERSION | 2.0 |
| AIRBLUE_API_CHANNEL | zapways_ota |
| AIRBLUE_CLIENT_ID_CONFIGURED | YES |
| AIRBLUE_CLIENT_KEY_CONFIGURED | YES |
| AIRBLUE_AGENT_TYPE_CONFIGURED | YES |
| AIRBLUE_AGENT_ID_CONFIGURED | YES |
| AIRBLUE_AGENT_PASSWORD_CONFIGURED | YES |
| AIRBLUE_ENDPOINT_CONFIGURED | YES |
| AIRBLUE_ENDPOINT_HOST_ONLY | otatest4.zapways.com |
| AIRBLUE_TLS_CERT_CONFIGURED | YES |
| AIRBLUE_TLS_KEY_CONFIGURED | YES |
| AIRBLUE_TLS_FILES_READABLE | NO (configured paths **do not exist** on disk) |
| AIRBLUE_CERTIFICATION_STATUS | pending |
| AIRBLUE_SEARCH_CERTIFIED | NO |
| AIRBLUE_BOOKING_FEATURE_ENABLED | YES |
| AIRBLUE_TICKETING_FEATURE_ENABLED | NO |
| AIRBLUE_CANCEL_FEATURE_ENABLED | NO |
| AIRBLUE_EXTERNAL_CALLS_ENV_PRESENT | NO |
| AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED | NO |
| AIRBLUE_CLI_LIVE_ENABLED | NO |
| AIRBLUE_OTA_V3_LIVE_ENABLED | NO |

### Code readiness (deployed)

| Op | Code ready | Notes |
|----|------------|-------|
| Search | YES | `AirBlueTestSearchCommand` + `GuardsAirBlueLiveNetwork` |
| Booking | YES | uncertain-outcome + duplicate_booking_guard present |
| Retrieve | YES | clears uncertain when PNR/instance resolved |
| Cancel | YES | `AirBlueCancelCommitCommand` live-gated |
| Ticketing | YES (code) | requires connection `ticketing_enabled=true` — currently NO |
| Void | YES (explicit deny) | throws `void_unsupported` |

| Entitlement / contract | Value |
|------------------------|-------|
| AIRBLUE_TICKETING_PROVIDER_ENTITLEMENT_PROVEN | NO |
| AIRBLUE_VOID_PROVIDER_CONTRACT_PROVEN | NO |

### GO / NO-GO

| Gate | Value |
|------|-------|
| AIRBLUE_SANDBOX_SEARCH_CERT_READY | **NO** — TLS paths configured but files absent (unresolved config mismatch); kill switches correctly OFF |
| AIRBLUE_SANDBOX_BOOK_RETRIEVE_CANCEL_CERT_READY | **NO** — blocked on search preflight TLS mismatch (code/safeguards otherwise present; env=sandbox) |
| AIRBLUE_SANDBOX_TICKETING_CERT_READY | **BLOCKED_FEATURE_FLAG_AND_PROVIDER_ENTITLEMENT_CONFIRMATION** |
| AIRBLUE_SANDBOX_VOID_CERT_READY | **NO** |
| AIRBLUE_VOID_BLOCKER | VOID_PROVIDER_CONTRACT_NOT_IMPLEMENTED |
| Overall AirBlue posture | **READY_AWAITING_LIVE_CERTIFICATION** (unchanged) |

### Recommended next certification sequence (next loop only)

1. Resolve TLS material (place readable cert/key **or** clear stale credential paths if mTLS not required for QA).  
2. Controlled sandbox connectivity/search (`AIRBLUE_EXTERNAL_CALLS_ENABLED` + `AIRBLUE_CLI_LIVE_ENABLED` + `--allow-live` only for that loop).  
3. Validate fare/provenance/price/currency/taxes/baggage.  
4. Dedicated QA OTA booking from **same** fresh search result (one AirBook).  
5. Retrieve → verify PNR/instance/local persistence.  
6. Cancel same QA PNR.  
7. Retrieve again → prove cancelled.  
8. Ticketing only after entitlement + explicit `ticketing_enabled`.  
9. Void only after Zapways/AirBlue contract obtained and offline implementation.

**This loop did not enable external calls and did not contact Zapways.**

## Scorecard reconciliation

| Item | Before | After |
|------|--------|-------|
| OPS-DEF-0022 | PARTIAL (listed as P2) | PARTIAL_EXTERNAL_CONFIG (still P2) |
| OPS-DEF-0023 | OPEN (incorrectly listed under P2; ledger P3) | **CLOSED** |
| CURRENT_NON_DEFERRED_P2_BLOCKERS | OPS-DEF-0022; OPS-DEF-0023 | **OPS-DEF-0022** |
| AGENCY_ADMIN mailbox | operator action open | BLOCKED_NEEDS_OPERATOR_EMAIL |
| Deferred Sabre/AirBlue/AbhiPay | unchanged | unchanged |

## Mutation assertion summary

| Assertion | Value |
|-----------|-------|
| AIRBLUE_EXTERNAL_NETWORK_CALLS | 0 |
| AIRBLUE_LIVE_*_CALLS (all) | 0 |
| SUPPLIER_CALLS | 0 |
| PAYMENT_CALLS | 0 |
| BOOKING_MUTATIONS | 0 |
| FINANCIAL_MUTATIONS | 0 |
| optimize:clear / migrations / git pull on prod | NOT RUN |
| failed_jobs retry/delete | 0 |
