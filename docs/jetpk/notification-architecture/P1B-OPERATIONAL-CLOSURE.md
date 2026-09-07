# P1B operational closure

Date: 2026-09-07  
Branch: `phase/jp-master-unfinished-closure-10`

## Worker topology

| Field | Value |
|---|---|
| WORKER_MANAGER | Supervisor preferred; cron `--stop-when-empty` fallback |
| WORKER_COUNT | 1 |
| QUEUE_LIST | `notifications-critical,notifications-transactional,notifications-ops,notifications-bulk` |
| MEMORY_LIMIT | process default |
| TIMEOUT | 90s |
| TRIES | 5 |
| RESTART_POLICY | Supervisor `autorestart=true`; cron every minute |

Config files:

- `deploy/supervisor/jetpk-notifications.conf`
- `deploy/cron/jetpk-notifications.cron`

## Async config

Source default remains `NOTIFICATION_PIPELINE_ASYNC=false` (do not hardcode production true).  
Production enablement is an env/runtime change after workers exist: `NOTIFICATION_PIPELINE_ASYNC=true`.

Outbox lock: `NOTIFICATION_OUTBOX_LOCK_TIMEOUT` (default 300s)  
Max attempts: `NOTIFICATION_OUTBOX_MAX_ATTEMPTS` (default 8)

## Route precedence

1. Enabled `notification_routes` for exact `agency_id`
2. Else enabled global (`agency_id` null) routes
3. Else `POLICY_BUCKETS` legacy fallback, logged as `notification.route.legacy_fallback`

Agency and global sets are not merged.

Route rows drive audience, strategy, queue_name, priority, template_key, provider.

## Event identity

Current precedence in `NotificationEventIdentity::resolve`:

1. explicit `notification_event_id`
2. immutable `occurrence_id` / `notification_occurrence_id` → UUID v5
3. explicit `event_variant` → UUID v5
4. proven one-shot aggregate (`NotificationEventIdentityPolicy::ONE_SHOT_ALLOWLIST`) → UUID v5 `event_type|aggregate_type:id`
5. otherwise a fresh occurrence UUID

Superseded (pre-`1a2881cf`): default identity was aggregate-only `event_type|aggregate_type:id` for any booking/user aggregate. That default is no longer current.

`invoice_generated`, `payment_receipt_generated`, and `ticket_itinerary_generated` are **not** one-shot aggregates. Repeat generations of the same booking/document family get new event IDs unless the producer supplies an immutable generation `occurrence_id`.

## Worker probe

`php artisan notifications:probe-worker` dispatches `NotificationWorkerProbe` (no email, no business mutation) onto the four notification queues and waits for cache tokens. Use this to prove cron `queue:work --stop-when-empty` actually consumes jobs. Cron count and empty backlog are not sufficient.

## Outbox race

Insert first; unique `event_id` is authority; duplicate-key `QueryException` returns the winning row.

## After-commit

If `DB::transactionLevel() > 0`, outbox row is written in the transaction; dispatch is `DB::afterCommit`. Rollback drops the row and does not dispatch.

## Remaining specialized / legacy

| Path | Status |
|---|---|
| Login OTP | INTENTIONAL_SPECIALIZED (`Mail::send`) |
| Password reset | INTENTIONAL_SPECIALIZED (Laravel) |
| Email verification | INTENTIONAL_SPECIALIZED (Laravel signed URL) |
| Registration welcome/admin | queued mailables (same templates; not pipeline render) |
| BookingCommunicationService customer mail | LEGACY_PENDING_MIGRATION |
| POLICY_BUCKETS | retained fallback |

## Checkout verification-delivery state

Inline checkout account creation must record `verification_delivery` after the booking transaction commits (session regenerate, then `Registered`). SMTP failure must not 500 or roll back the customer/draft. This was already broken on production baseline `1a2881cf` (not introduced by P1B identity work).

## Next phase

`P2_EMAIL_TEMPLATE_CONSISTENCY_DATA_ACCURACY_UI_CLOSURE`

P2 is existing JetPakistan templates only: visual consistency, accurate dynamic information, role-appropriate content, subjects/headings/CTAs/URLs, mobile/desktop/Gmail/Outlook rendering, no overflow, eliminate unintended legacy callers, preserve OTP/reset/verification security. No new third-party template system. Do not use the superseded label `P2_NOTIFICATION_TEMPLATE_EXTERNALIZATION`.
