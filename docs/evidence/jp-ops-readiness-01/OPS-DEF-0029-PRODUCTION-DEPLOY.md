# OPS-DEF-0029 — Production deployment evidence

**PR:** #109
**Approved head:** `1f1f49363df60f9a3b142e6b00e40915a9c3625a`
**Merge method:** squash (merge commits forbidden by branch protection)
**Merged main SHA:** `273c59c84dcc68a615a9445ad5b66816e503dbdb`
**Release guard (runtime):** `39438694764` PASS
**Deploy time (UTC):** 2026-10-20T14:45:34Z
**Observation start (UTC):** 2026-10-20T14:46:30Z
**Evidence PR (deploy docs):** #110 → `bce79f4a0b81ca38bede1fe8202179c32344293e`
**Closure docs PR:** #111
**Status:** **CLOSED_PROD** (natural-cycle observation proven 2026-10-22; deployment audit trail preserved below)

## Runtime files deployed

| Path | Action |
|------|--------|
| `app/Jobs/Notifications/DeliverQueuedOperationalMail.php` | replaced |
| `app/Services/Communication/OtaNotificationService.php` | replaced |
| `app/Services/Communication/RecipientDeliverySafetyService.php` | new |
| `app/Support/Communication/NotificationErrorSanitizer.php` | new |
| `app/Support/Communication/SmtpDeliveryFailureClassifier.php` | new |

Blob parity: merged-main tree blobs for all five paths match approved head `1f1f493…`.

Pre-deploy production copies of the two existing files matched reviewed base `27961bb…` (no unexplained customization).

## Hashes (SHA-256 content)

### Merged source / post-deploy production (parity PASS)

```
4b931dbdf2babe7578702eaea481f12875592053517ab42ec4466e5f83878ebe  app/Jobs/Notifications/DeliverQueuedOperationalMail.php
2befb7011301d6b717ad379e70c005a79c09515dad4bdd32e32d574fda7e1b80  app/Services/Communication/OtaNotificationService.php
75873ed55617b257b1a0143cbd7b82b38ba1cf3830ac850ed687177c43bef5ff  app/Services/Communication/RecipientDeliverySafetyService.php
09a20070f7436c7d6ea8e625c2a035a06af299b711a95b41c28e15977e40a45d  app/Support/Communication/NotificationErrorSanitizer.php
1ced7b776ce93a576c3973c7190fab5de4b71e56ee9d84414f6a992dcc9d3220  app/Support/Communication/SmtpDeliveryFailureClassifier.php
```

Closure re-check (2026-10-22): `PROD_RUNTIME_HASH_PARITY=PASS` (same five hashes).

### True pre-deploy production (existing files only)

Backup: `/home/pkjetp/backups/ops-def-0029-pre-deploy-20261020T144514Z`

```
aeaede7856715f5a3a7965fa3f3b878a05f2c8cb82a1c59c8ae0aa2b175de584  DeliverQueuedOperationalMail.php
e48e4664abeb2a34a3e0a5f1b409c87cbab43f855f766eb04d126fd9aa00d394  OtaNotificationService.php
```

New files at deploy time (not present pre-deploy):

- `RecipientDeliverySafetyService.php` — NEW_FILE
- `NotificationErrorSanitizer.php` — NEW_FILE
- `SmtpDeliveryFailureClassifier.php` — NEW_FILE

Successful install backup/meta: `/home/pkjetp/backups/ops-def-0029-pre-deploy-20261020T144534Z`

## Gates (deploy-time certified)

| Gate | Value |
|------|-------|
| PHP_SYNTAX | PASS (5/5) |
| LARAVEL_BOOTSTRAP | PASS |
| PROD_RUNTIME_HASH_PARITY | PASS |
| HTTP /up | 200 |
| HTTP /login | 200 |
| HTTP /lookup-booking | 200 |
| MIGRATIONS_RUN | 0 |
| optimize:clear | NOT RUN |
| queue:retry / flush | NOT RUN |

## Workers / queues (deploy-time certified)

| Item | Value |
|------|-------|
| WORKER_MANAGER | crontab |
| WORKER_RELOAD_METHOD | none_required (idle `--stop-when-empty`; next minute cron loads new code) |
| WORKERS_BEFORE | NO_LIVE_QUEUE_WORKER |
| WORKERS_AFTER | NO_LIVE_QUEUE_WORKER |
| NOTIFICATION_QUEUE_ROUTING_OK | YES (`mail.notification_queue=notifications-transactional`) |
| DEFAULT_QUEUE_PENDING | 0 |
| NOTIFICATIONS_TRANSACTIONAL_PENDING | 0 |
| UNSAFE_STALE_NOTIFICATION_BACKLOG | NO |
| QUEUE_RESTART_SAFE | YES |

Cron worker cmdline includes: `notifications-critical,notifications-transactional,notifications-ops,notifications-bulk`.

## Failed jobs at deploy (read-only)

| Item | Value |
|------|-------|
| FAILED_JOBS_TOTAL_BEFORE | 9 |
| FAILED_JOBS_TOTAL_AFTER | 9 |
| FAILED_JOBS_RETRIED | 0 |
| FAILED_JOBS_DELETED | 0 |
| HISTORICAL_SMTPISH_IDS_STILL_PRESENT | 9 |
| NEW_PERMANENT_5_1_1_FAILED_JOBS (at deploy) | 0 |

## Historical permanent recipient recognition (deploy-time read-only)

| Item | Value |
|------|-------|
| HISTORICAL_PERMANENT_RECIPIENT_RECOGNIZED | YES |
| LEGACY_5_1_1_FALLBACK_ACTIVE | YES |
| RECIPIENT_SHA256_PREFIX | 9755902a7672 |
| CONTROL_ADDRESS_SUPPRESSED | NO |
| Email send for proof | NO |

## Absolute mutation assertions (deploy loop)

| Assertion | Value |
|-----------|-------|
| CUSTOMER_EMAIL_SENT | 0 |
| HISTORICAL_NOTIFICATION_REPLAY | 0 |
| SUPPLIER_CALLS | 0 |
| PAYMENT_CALLS | 0 |
| BOOKING_MUTATIONS | 0 |
| FINANCIAL_MUTATIONS | 0 |
| AIRBLUE_EXTERNAL_NETWORK_CALLS | 0 (config external=false) |
| SABRE_PNR_CREATE_CALLS | 0 |
| TICKETING_CALLS | 0 |
| PRODUCTION_DATABASE_MUTATIONS | 0 (deploy was filesystem-only) |
| AGENCY_ADMIN mailbox correction | NOT DONE (separate operator action) |

---

## Natural-cycle closure evidence (appended 2026-10-22)

Does **not** replace the deployment audit trail above.

### Historical failed-jobs baseline (final reconciled)

| Aggregate | Value |
|-----------|-------|
| FAILED_JOBS_HISTORICAL_BASELINE | 9 |
| PERMANENT_550_5_1_1_HISTORICAL | 6 |
| TRANSIENT_450_4_7_1_HISTORICAL | 3 |

Original investigation recorded 8 SMTP-ish failures (5 permanent + 3 transient). A ninth row (`failed_jobs.id=156`, sanitized id hash `0fecf9247f3d`) was created at **2026-10-20 07:17:05 UTC** (before deployment): `agency_booking_activity_summary` / `550` / `5.1.1`, same OPS-DEF-0029 family. It is historical baseline, **not** post-deploy regression.

### Ninth job recipient cardinality (read-only, proven)

| Item | Value |
|------|-------|
| NINTH_JOB_ID | 156 |
| NINTH_JOB_ID_HASH | 0fecf9247f3d |
| NINTH_JOB_SINGLE_TO | YES |
| NINTH_JOB_TO_COUNT | 1 |
| NINTH_JOB_CC_COUNT | 0 |
| NINTH_JOB_BCC_COUNT | 0 |

Therefore final permanent recipient cardinality may state:

| Aggregate | Value |
|-----------|-------|
| PERMANENT_SINGLE_TO_COUNT | 6 |
| PERMANENT_MULTI_TO_COUNT | 0 |
| PERMANENT_WITH_CC_COUNT | 0 |
| PERMANENT_WITH_BCC_COUNT | 0 |

(Original probe of first five permanent jobs = single-TO; ninth job independently proven single-TO.)

### Natural cycle gates

| Item | Value |
|------|-------|
| Schedule | `ota:send-agency-booking-activity-summary --all-active-agencies` dailyAt `07:10` (app TZ = UTC) |
| NATURAL_POSTDEPLOY_CYCLE_OCCURRED | YES |
| NATURAL_CYCLE_TIMESTAMP | 2026-10-22 07:10:04 UTC |
| Prior post-deploy cycle | 2026-10-21 also showed permanent skip + no-recipients pattern |
| KNOWN_PERMANENT_RECIPIENT_ATTEMPTED | NO |
| PERMANENT_RECIPIENT_SKIPPED_AUDIT_CREATED | YES |
| SKIPPED_AUDIT_EVENT | agency_booking_activity_summary |
| SKIPPED_AUDIT_STATUS | skipped |
| SKIPPED_AUDIT_PERMANENT_FLAG | true |
| Known recipient SHA256 prefix | 9755902a7672 (no raw email) |
| VALID_RECIPIENT_DELIVERY_PATH_UNCHANGED | YES |
| FAILED_JOBS_TOTAL_NOW | 9 |
| NEW_FAILED_JOBS_SINCE_DEPLOY | 0 |
| NEW_PERMANENT_5_1_1_FAILED_JOBS_SINCE_DEPLOY | 0 |
| NEW_AGENCY_SUMMARY_5_1_1_FAILED_JOBS | 0 |
| FAILED_JOBS_RETRIED | 0 |
| FAILED_JOBS_DELETED | 0 |
| Manual summary trigger | NO |

Cycle shape (2026-10-22): 5 communication_logs — 1 permanent-suppression skip for the known bad AGENCY_ADMIN mailbox; 4 `No recipients resolved` skips matching the dominant pre-deploy pattern. Queue routing remains `notifications-transactional`.

### Closure mutation assertions

| Assertion | Value |
|-----------|-------|
| CUSTOMER_EMAIL_TEST_SEND | 0 |
| HISTORICAL_NOTIFICATION_REPLAY | 0 |
| SUPPLIER_CALLS | 0 |
| PAYMENT_CALLS | 0 |
| BOOKING_MUTATIONS | 0 |
| FINANCIAL_MUTATIONS | 0 |
| AIRBLUE_EXTERNAL_NETWORK_CALLS | 0 |
| SABRE_PNR_CREATE_CALLS | 0 |
| TICKETING_CALLS | 0 |

### Separate operator data action (not runtime closure)

AGENCY_ADMIN recipient mailbox correction remains an authorized operator/data action. Runtime defect closure does **not** mean the incorrect mailbox itself was corrected.

### Closure decision

| Gate | Result |
|------|--------|
| CLOSURE_DECISION | PASS |
| OPS_DEF_0029_STATUS | **CLOSED_PROD** |

END
