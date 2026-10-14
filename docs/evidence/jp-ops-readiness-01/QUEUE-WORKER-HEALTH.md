# JP-OPS-READINESS-01 — Queue Worker Health

**Updated:** Wave 1C (routing fix in progress; default backlog disposition pending)

## Findings

```
WORKER_RUNNING=PARTIAL
WORKER_MANAGER=CRON (not supervisor/systemd queue daemon)
WORKER_COUNT=0 persistent processes (cron spawns stop-when-empty)
QUEUES_CONSUMED=notifications-critical,notifications-transactional,notifications-ops,notifications-bulk
UNCONSUMED_QUEUES=default — intentional NOT to consume until backlog disposition; then keep default out of cron
WORKER_RESTART_POLICY=cron every minute + --stop-when-empty --tries=5 --timeout=90
WORKER_FAILURE_VISIBILITY=failed_jobs table + notification-worker-cron.log
INTENDED_MAIL_QUEUE=notifications-transactional (DeliverQueuedOperationalMail)
PERSISTENT_DEFAULT_WORKER_REQUIRED=NO
```

## Cron worker command

```
* * * * * php artisan queue:work database --stop-when-empty \
  --queue=notifications-critical,notifications-transactional,notifications-ops,notifications-bulk \
  --tries=5 --timeout=90 >> storage/logs/notification-worker-cron.log 2>&1
```

## Implications

1. Notification queues are polled every minute — OK for mail backlog when jobs exist.
2. Jobs on `default` (e.g. branding `RemoveBrandLogoBackground` if queued default) may sit until a worker covers them — **gap**.
3. No long-running `queue:work` process in `ps` at probe time (expected with stop-when-empty).
4. failed_jobs backlog is **historical mail rejects**, not active worker crash loop.
5. **Follow-up probe (Wave 1A):** `jobs` table has **178 pending** rows, all on queue **`default`** (oldest≈2026-09-11, newest≈2026-10-14 UTC). Notification cron does **not** consume `default` → backlog can grow indefinitely (OPS-DEF-0019).

## PM2

- Daemon running (pid 405709 since Aug 8)
- Serves Next apps only — **not** Laravel queue
