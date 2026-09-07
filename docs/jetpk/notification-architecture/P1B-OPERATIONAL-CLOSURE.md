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

`NotificationEventIdentity`:

- explicit `notification_event_id` wins
- login/OTP/password-reset/new-device are occurrence-scoped UUIDs (not user_id)
- booking/user aggregates use UUID v5 from `event_type|aggregate_type:id`

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
