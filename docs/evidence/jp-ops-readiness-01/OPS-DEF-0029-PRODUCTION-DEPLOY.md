# OPS-DEF-0029 — Production deployment + natural-cycle closure evidence

**PR (runtime):** #109  
**Approved head:** `1f1f49363df60f9a3b142e6b00e40915a9c3625a`  
**Merge method:** squash (merge commits forbidden by branch protection)  
**Merged main SHA:** `273c59c84dcc68a615a9445ad5b66816e503dbdb`  
**Release guard (runtime):** `39438694764` PASS  
**Deploy time (UTC):** 2026-10-20T14:45:34Z  
**Observation start (UTC):** 2026-10-20T14:46:30Z  
**Evidence PR (deploy docs):** #110 → `bce79f4a0b81ca38bede1fe8202179c32344293e`  
**Status:** **CLOSED_PROD** (natural-cycle observation proven 2026-10-22)

## Runtime files deployed

| Path | Action |
|------|--------|
| `app/Jobs/Notifications/DeliverQueuedOperationalMail.php` | replaced |
| `app/Services/Communication/OtaNotificationService.php` | replaced |
| `app/Services/Communication/RecipientDeliverySafetyService.php` | new |
| `app/Support/Communication/NotificationErrorSanitizer.php` | new |
| `app/Support/Communication/SmtpDeliveryFailureClassifier.php` | new |

Blob parity: merged-main tree blobs for all five paths match approved head `1f1f493…`.  
Closure re-check (2026-10-22): `PROD_RUNTIME_HASH_PARITY=PASS`.

## Hashes (SHA-256 content) — still certified

```
4b931dbdf2babe7578702eaea481f12875592053517ab42ec4466e5f83878ebe  app/Jobs/Notifications/DeliverQueuedOperationalMail.php
2befb7011301d6b717ad379e70c005a79c09515dad4bdd32e32d574fda7e1b80  app/Services/Communication/OtaNotificationService.php
75873ed55617b257b1a0143cbd7b82b38ba1cf3830ac850ed687177c43bef5ff  app/Services/Communication/RecipientDeliverySafetyService.php
09a20070f7436c7d6ea8e625c2a035a06af299b711a95b41c28e15977e40a45d  app/Support/Communication/NotificationErrorSanitizer.php
1ced7b776ce93a576c3973c7190fab5de4b71e56ee9d84414f6a992dcc9d3220  app/Support/Communication/SmtpDeliveryFailureClassifier.php
```

Backups: `/home/pkjetp/backups/ops-def-0029-pre-deploy-20261020T144514Z` (true pre) and `…144534Z` (install meta).

## Historical failed-jobs baseline (final reconciled)

| Aggregate | Value |
|-----------|-------|
| FAILED_JOBS_HISTORICAL_BASELINE | 9 |
| PERMANENT_550_5_1_1_HISTORICAL | 6 |
| TRANSIENT_450_4_7_1_HISTORICAL | 3 |

Original investigation recorded 8 SMTP-ish failures (5 permanent + 3 transient). A ninth row was created at **2026-10-20 07:17:05 UTC** (before deployment), also `agency_booking_activity_summary` / `550` / `5.1.1`, same OPS-DEF-0029 family. It is historical baseline, **not** post-deploy regression.

Deploy/closure counts:

| Item | Value |
|------|-------|
| FAILED_JOBS_TOTAL_AT_DEPLOY_BASELINE | 9 |
| FAILED_JOBS_TOTAL_NOW (2026-10-22 closure) | 9 |
| NEW_FAILED_JOBS_SINCE_DEPLOY | 0 |
| NEW_PERMANENT_5_1_1_FAILED_JOBS_SINCE_DEPLOY | 0 |
| NEW_AGENCY_SUMMARY_5_1_1_FAILED_JOBS | 0 |
| FAILED_JOBS_RETRIED | 0 |
| FAILED_JOBS_DELETED | 0 |

## Natural-cycle observation (closure)

| Item | Value |
|------|-------|
| Schedule | `ota:send-agency-booking-activity-summary --all-active-agencies` dailyAt `07:10` (app TZ = UTC) |
| NATURAL_POSTDEPLOY_CYCLE_OCCURRED | YES |
| NATURAL_CYCLE_TIMESTAMP | 2026-10-22 07:10:04 |
| Prior post-deploy cycle | 2026-10-21 also showed `skipped_permanent=1` + `skipped_no_recipients=4` |
| KNOWN_PERMANENT_RECIPIENT_ATTEMPTED | NO |
| PERMANENT_RECIPIENT_SKIPPED_AUDIT_CREATED | YES |
| SKIPPED_AUDIT_EVENT | agency_booking_activity_summary |
| SKIPPED_AUDIT_STATUS | skipped |
| SKIPPED_AUDIT_PERMANENT_FLAG | true |
| Known recipient SHA256 prefix | `9755902a7672` (no raw email) |
| VALID_RECIPIENT_DELIVERY_PATH_UNCHANGED | YES |

Cycle shape (2026-10-22): 5 communication_logs — 1 permanent-suppression skip for the known bad AGENCY_ADMIN mailbox; 4 `No recipients resolved` skips matching the dominant pre-deploy pattern (32/40 historical sample). Only agency with historical sent/queued success for this event is the known permanent mailbox agency — now suppressed instead of creating `failed_jobs`. Queue routing remains `notifications-transactional`.

## Absolute mutation assertions (closure loop)

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
| Manual summary trigger | NO |

## Separate operator data action (not runtime closure)

AGENCY_ADMIN recipient mailbox correction remains an authorized operator/data action. Runtime defect closure does **not** mean the incorrect mailbox itself was corrected.

## Closure gates

| Gate | Result |
|------|--------|
| PROD_RUNTIME_HASH_PARITY | PASS |
| PHP_SYNTAX | PASS |
| LARAVEL_BOOTSTRAP | PASS |
| NATURAL_POSTDEPLOY_CYCLE_OCCURRED | YES |
| KNOWN_PERMANENT_RECIPIENT_ATTEMPTED | NO |
| PERMANENT_RECIPIENT_SKIPPED_AUDIT_CREATED | YES |
| NEW_AGENCY_SUMMARY_5_1_1_FAILED_JOBS | 0 |
| NEW_PERMANENT_5_1_1_FAILED_JOBS_SINCE_DEPLOY | 0 |
| VALID_RECIPIENT_DELIVERY_PATH_UNCHANGED | YES |
| CLOSURE_DECISION | PASS |
| OPS_DEF_0029_STATUS | **CLOSED_PROD** |

END
