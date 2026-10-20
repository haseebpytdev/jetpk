# OPS-DEF-0029 — Production deployment evidence

**PR:** #109  
**Approved head:** `1f1f49363df60f9a3b142e6b00e40915a9c3625a`  
**Merge method:** squash (merge commits forbidden by branch protection)  
**Merged main SHA:** `273c59c84dcc68a615a9445ad5b66816e503dbdb`  
**Release guard:** `39438694764` PASS  
**Deploy time (UTC):** 2026-10-20T14:45:34Z  
**Observation start (UTC):** 2026-10-20T14:46:30Z  
**Status:** **DEPLOYED_OBSERVATION_PENDING** (not CLOSED_PROD)

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

### True pre-deploy production (existing files only)

Backup: `/home/pkjetp/backups/ops-def-0029-pre-deploy-20261020T144514Z`

```
aeaede7856715f5a3a7965fa3f3b878a05f2c8cb82a1c59c8ae0aa2b175de584  DeliverQueuedOperationalMail.php
e48e4664abeb2a34a3e0a5f1b409c87cbab43f855f766eb04d126fd9aa00d394  OtaNotificationService.php
```

Successful install backup/meta: `/home/pkjetp/backups/ops-def-0029-pre-deploy-20261020T144534Z`

## Gates

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

## Workers / queues

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

## Failed jobs (read-only)

| Item | Value |
|------|-------|
| FAILED_JOBS_TOTAL_BEFORE | 9 |
| FAILED_JOBS_TOTAL_AFTER | 9 |
| FAILED_JOBS_RETRIED | 0 |
| FAILED_JOBS_DELETED | 0 |
| HISTORICAL_SMTPISH_IDS_STILL_PRESENT | 9 |
| NEW_PERMANENT_5_1_1_FAILED_JOBS | 0 |

## Historical permanent recipient recognition (read-only)

| Item | Value |
|------|-------|
| HISTORICAL_PERMANENT_RECIPIENT_RECOGNIZED | YES |
| LEGACY_5_1_1_FALLBACK_ACTIVE | YES |
| RECIPIENT_SHA256_PREFIX | 9755902a7672 |
| CONTROL_ADDRESS_SUPPRESSED | NO |
| Email send for proof | NO |

## Absolute mutation assertions

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

## Next closure gate (do not claim yet)

Natural scheduled agency booking activity summary (~07:10) after OBSERVATION_START must show:

- NEW_AGENCY_SUMMARY_5_1_1_FAILED_JOBS=0
- KNOWN_PERMANENT_RECIPIENT_ATTEMPTED=NO
- PERMANENT_RECIPIENT_SKIPPED_AUDIT_CREATED=YES
- VALID_RECIPIENT_DELIVERY_PATH_UNCHANGED=YES

Only then evaluate **CLOSED_PROD**.

END
