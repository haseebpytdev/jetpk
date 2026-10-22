# OPS-DEF-0029 — SMTP Recipient Failure Handling

**Loop:** JP-OPS-DEF-0029-SMTP-RECIPIENT-CLOSURE-01  
**Protected main base:** `27961bb210bcd52d0509a0e2c1149ff6fc915714`  
**Branch:** `fix/ops-def-0029-smtp-recipient-failures`  
**Mode:** CODE + TEST + READ-ONLY prod forensics  
**Status:** **CLOSED_PROD** (natural agency-summary cycle proven 2026-10-22T07:10:04Z; no new permanent 5.1.1 failed_jobs).  
**Deploy evidence:** `OPS-DEF-0029-PRODUCTION-DEPLOY.md`  
**Merged main:** `273c59c84dcc68a615a9445ad5b66816e503dbdb` (from approved head `1f1f493…`)

## Absolute mutation assertions

| Assertion | Value |
|-----------|-------|
| EXECUTE_FAILED_JOB_RETRY | NO |
| FAILED_JOBS_DELETED | 0 |
| FAILED_JOB_ROWS_MUTATED | 0 |
| COMMUNICATION_ROWS_MUTATED | 0 |
| HISTORICAL_EMAIL_REPLAY | 0 |
| CUSTOMER_EMAIL_SENT_DURING_TESTS | 0 |
| PRODUCTION_FILES_DEPLOYED | 0 |
| PRODUCTION_DATABASE_MUTATIONS | 0 |
| AIRBLUE_EXTERNAL_NETWORK_CALLS | 0 |
| SABRE_PNR_CREATE_CALLS | 0 |
| SUPPLIER_BOOKING_CALLS | 0 |
| TICKETING_CALLS | 0 |
| LIVE_PAYMENT_CALLS | 0 |

## Phase 1–3 — sanitized forensics (final historical baseline = 9)

Original investigation recorded **8** SMTP-ish `failed_jobs` (5 permanent + 3 transient). A **ninth** row was created at `2026-10-20 07:17:05 UTC` **before** deployment: also `agency_booking_activity_summary` / `550` / `5.1.1` / same OPS-DEF-0029 family. Final reconciled baseline:

| Aggregate | Value |
|-----------|-------|
| FAILED_JOBS_HISTORICAL_BASELINE | 9 |
| PERMANENT_550_5_1_1_HISTORICAL | 6 |
| TRANSIENT_450_4_7_1_HISTORICAL | 3 |
| FAILED_EVENT_COUNTS (original 8 sample) | `agency_booking_activity_summary`:5; `payment_proof_submitted`:1; `booking_status_changed`:1; `payment_verified`:1 |
| NINTH_JOB | pre-deploy permanent `agency_booking_activity_summary` (historical; not post-deploy regression) |
| TRANSIENT_RECIPIENT_LATER_SUCCESS_COUNT | 0 |
| TRANSIENT_FAILURE_STILL_ACTIVE_COUNT | 3 (historical; manual review; not retried this loop) |

### Source classification

| Class | Count | Notes |
|-------|-------|-------|
| PERMANENT → `AGENCY_ADMIN` | 6 | Same hashed mailbox family; daily activity summary (includes ninth pre-deploy row) |
| TRANSIENT → staff/agent contact | 3 | Not matched to `users.email`; booking-linked operational/agent buckets |

| Gate | Value |
|------|-------|
| INVALID_RECIPIENT_IS_CONFIGURATION_DEFECT | NO |
| INVALID_RECIPIENT_IS_USER_DATA_DEFECT | YES |
| PERMANENT_REJECT_SOURCE_TYPES | AGENCY_ADMIN |
| TRANSIENT_REJECT_SOURCE_TYPES | PLATFORM_STAFF/AGENT contact (non-user email rows) |

No recipient addresses exposed in evidence.

### Permanent 5.1.1 recipient cardinality (read-only failed_jobs probe)

| Aggregate | Value |
|-----------|-------|
| PERMANENT_SINGLE_TO_COUNT | 6 |
| PERMANENT_MULTI_TO_COUNT | 0 |
| PERMANENT_WITH_CC_COUNT | 0 |
| PERMANENT_WITH_BCC_COUNT | 0 |
| PERMANENT_SINGLE_RECIPIENT_FAILURES | 6 |
| PERMANENT_MULTI_RECIPIENT_FAILURES | 0 |

Original permanent cardinality probe: first five permanent jobs were single-TO (CC/BCC empty). Ninth permanent job (`failed_jobs.id=156`, hash `0fecf9247f3d`) independently proven read-only: `TO=1`, `CC=0`, `BCC=0` (`NINTH_JOB_SINGLE_TO=YES`). Not inferred from family alone.

| Claim | Value |
|-------|-------|
| KNOWN_BAD_RECIPIENT_FILTERING_IN_MULTI_RECIPIENT_BUNDLE | PASS (exact known-bad address filtered; valid peers continue) |
| FIRST_TIME_MULTI_RECIPIENT_PERMANENT_FAILURE_LEARNING | NOT_REQUIRED_FOR_CURRENT_0029 / DOCUMENTED_LIMITATION |

`recipient_email` on queued multi-TO logs may be an implode of addresses; first-time permanent rejection attribution across multiple TO addresses is **not** implemented in this PR because production evidence shows zero multi-TO permanent failures.

### Pre-merge legacy classification-meta bridge (read-only CommunicationLog)

| Aggregate | Value |
|-----------|-------|
| HISTORICAL_PERMANENT_LOGS_TOTAL | 5 |
| HISTORICAL_PERMANENT_LOGS_WITH_CLASSIFICATION_META | 0 |
| HISTORICAL_PERMANENT_LOGS_WITHOUT_CLASSIFICATION_META | 5 |
| LEGACY_5_1_1_FALLBACK | YES (error corpus still contains enhanced `5.1.1`; no DB backfill) |
| LEGACY_4XX_SUPPRESSION | NO |
| LEGACY_AMBIGUOUS_550_SUPPRESSION | NO |
| LEGACY_MULTI_RECIPIENT_INFERENCE | NO |

All five historical permanent single-TO failed logs pre-date classifier meta. Suppression uses `SmtpDeliveryFailureClassifier::classifyMessage` on stored `error_message` only when meta lacks `delivery_failure_class`.

## Phase 4–9 — code changes

| Component | Behavior |
|-----------|----------|
| `SmtpDeliveryFailureClassifier` | Enhanced-status-first; permanent only for `5.1.1`; transient for `4.x` / reply `4xx`; else unknown; `classifyMessage()` shared with legacy bridge |
| `DeliverQueuedOperationalMail` | Permanent: log `failed` + meta, **do not rethrow**; transient/unknown: log + **rethrow**; success clears failure meta keys |
| `RecipientDeliverySafetyService` | Exact-address suppression from latest **actual delivery** outcome (`failed`/`sent`/`delivered` only). Modern meta path first; legacy fallback for failed rows without classification meta when enhanced `5.1.1` is unambiguous and recipient is one exact address. Suppression `skipped` audit rows do **not** clear permanent-reject state. Later real `sent`/`delivered` clears suppression. |
| `NotificationErrorSanitizer` | Shared with `OtaNotificationService::safeError`; strips secrets + emails from error_message |
| `NotificationRecipientResolver::normalizeEmails` | Already `FILTER_VALIDATE_EMAIL` (proven by test) |

| Required gate | Value |
|---------------|-------|
| PERMANENT_550_RETRY | NO |
| PERMANENT_550_FAILED_JOB_CREATED | NO (new behavior) |
| COMMUNICATION_LOG_TERMINAL_FAILED | YES |
| TRANSIENT_450_RETRY | YES |
| TRANSIENT_450_EVENTUAL_SUCCESS_CAN_MARK_SENT | YES |
| TRANSIENT_EXHAUSTED_ATTEMPTS_CAN_REACH_FAILED_JOBS | YES |
| UNKNOWN_MAIL_EXCEPTION_SWALLOWED | NO |
| QUEUED_MAIL_ERROR_SANITIZATION | PASS |
| SECRET_LEAK_IN_ERROR_MESSAGE | NO |
| MALFORMED_EMAIL_QUEUED | NO |
| VALID_BUT_NONEXISTENT_EMAIL_PREDETECTABLE | NO |

Backoff `[15,45,90]` + `tries=5` retained (sensible for SMTP greylist).

## Phase 11 — disposition of current 8 (no mutation)

| Disposition | Count |
|-------------|-------|
| CURRENT_8_PERMANENT_NO_RETRY | 5 |
| CURRENT_8_TRANSIENT_REVIEW | 3 |
| CURRENT_8_ALREADY_SUPERSEDED | 0 |
| CURRENT_8_UNKNOWN_REVIEW | 0 |

**Later data disposition (not this loop):** `MARK_HISTORICAL_TERMINAL` for the 5 permanent rows after deploy; keep 3 transient for manual SMTP/recipient review.  
`FAILED_JOB_ROWS_MUTATED=0` / `COMMUNICATION_ROWS_MUTATED=0` this loop.

## Phase 12 — data correction plan

| Gate | Value |
|------|-------|
| RECIPIENT_DATA_CORRECTION_REQUIRED | YES |
| RECIPIENT_DATA_CORRECTION_SOURCE_TYPES | AGENCY_ADMIN |

Do **not** invent a replacement address. Agency-admin mailbox must be corrected via authorized operator/agency profile workflow.

## Tests

| Suite | Result |
|-------|--------|
| Classifier + DeliverQueued + RecipientDeliverySafety | See `_phpunit-ops-def-0029-summary.md` (includes durability regression) |
| Artifact | `_phpunit-ops-def-0029-summary.md` (sanitized; no local paths) |
| Broader Communication / routing / operational suites | Baseline-proven vs `27961bb` — see summary; no HEAD-only regressions allowed |

## Natural-cycle closure (2026-10-22)

| Gate | Value |
|------|-------|
| NATURAL_POSTDEPLOY_CYCLE_OCCURRED | YES (`2026-10-22 07:10:04` UTC) |
| KNOWN_PERMANENT_RECIPIENT_ATTEMPTED | NO |
| PERMANENT_RECIPIENT_SKIPPED_AUDIT_CREATED | YES |
| NEW_AGENCY_SUMMARY_5_1_1_FAILED_JOBS | 0 |
| NEW_PERMANENT_5_1_1_FAILED_JOBS_SINCE_DEPLOY | 0 |
| VALID_RECIPIENT_DELIVERY_PATH_UNCHANGED | YES |
| CLOSURE_DECISION | PASS |

## Defect status

| ID | Status |
|----|--------|
| OPS-DEF-0029 | **CLOSED_PROD** |
| OPS-DEF-0015 | CLOSED (unchanged) |
| OPS-DEF-0019 | CLOSED (unchanged; no default-queue regression) |

## Next (non-runtime)

1. Operator corrects rejected AGENCY_ADMIN mailbox (separate authorized data action).  
2. Optional later historical failed_job terminal marking — separate approval.  
3. Transient historical 450 rows remain manual-review; do not auto-retry.

END
