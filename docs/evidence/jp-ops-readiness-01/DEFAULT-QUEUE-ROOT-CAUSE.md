# Wave 1B — Default Queue Root Cause

## Answers

```
WHY_DEFAULT_QUEUE_UNCONSUMED=
Cron queue:work only consumes notifications-critical,notifications-transactional,notifications-ops,notifications-bulk.
OtaNotificationService::dispatchMail() uses bare dispatch(Closure) which lands on the default queue when QUEUE_CONNECTION=database and not sync.
No supervisor/systemd persistent worker covers default.

EXPECTED_DEFAULT_WORKER=
Either: (a) cron/supervisor worker including default, OR (b) code change so queued mail uses notifications-* queues (out of Wave 1B scope — no code change).

PERSISTENT_WORKER_REQUIRED=YES (or expand cron queue list) once pending jobs are safely dispositioned.

CURRENT_NOTIFICATION_WORKER_SCOPE=notifications-* only via minute cron --stop-when-empty

DEPLOYMENT_CONFIG_MISSING=YES — production crontab omits default

APPLICATION_CONFIG_MISSING=PARTIAL — Closures intentionally use default connection/queue; named Mail::queue paths may use other queues

OPS_DEF_0019_CLASS=MISSING_CONFIGURATION + OPERATIONAL_PROCESS_MISSING
(also APPLICATION_DEFECT-adjacent: Closure dispatch without ->onQueue('notifications-transactional'))
```

## Evidence

- crontab: `queue:work database --queue=notifications-critical,notifications-transactional,notifications-ops,notifications-bulk`
- `OtaNotificationService.php:291` `dispatch(function () { ... sendMailNow ... })` — no `onQueue`
- `shouldQueueMail()` true when queue.default !== sync
- Pending: 178 on `default` only; `PENDING_ALL=178`

## Wave 1B gate

```
DEFAULT_WORKER_START_ALLOWED=NO
DEFAULT_QUEUE_AUTO_DRAIN_ALLOWED=NO
HIGH_RISK_PENDING_JOBS=178
```
