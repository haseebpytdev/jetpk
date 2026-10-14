# QUEUE WORKER PRODUCTION DESIGN — Wave 1C

Decision: keep the existing cron-driven notification consumers; do not start a persistent `default` worker for OPS-DEF-0019.

```
EXPECTED_QUEUES=notifications-critical,notifications-transactional,notifications-ops,notifications-bulk
WORKERS_PER_QUEUE=1 shared cron process (priority order left-to-right)
PROCESS_MANAGER=CRON (minute) + --stop-when-empty
AUTOSTART=YES (crontab)
AUTORESTART=YES (next minute)
TRIES=5
TIMEOUT=90
BACKOFF=job-level (DeliverQueuedOperationalMail: 15,45,90)
LOGGING=storage/logs/notification-worker-cron.log
FAILURE_VISIBILITY=failed_jobs table + communication_logs.status=failed
```

## Comparison: cron --stop-when-empty vs Supervisor persistent

| Factor | Cron (current) | Supervisor persistent |
|--------|----------------|------------------------|
| Host fit | Matches shared/cron ops model already live | Better latency / always-on |
| Risk | Minute lag OK for mail | Needs process supervision + deploy hooks |
| Wave 1C | **Retain** | Deferred (not required to close 0019) |

## Activation policy

```
WORKER_ACTIVATED_DEFAULT=NO
WORKER_ACTIVATED_NOTIFICATIONS=YES (already via cron; unchanged)
ACTIVATE_AFTER=
1) routing code deployed
2) 178 default backlog archived + deleted (no Closure execute)
3) QA canary on notifications-transactional succeeds
```

## Named job contract (DeliverQueuedOperationalMail)

```
QUEUE_NAME=notifications-transactional (config mail.notification_queue)
TRIES=5
TIMEOUT=90
BACKOFF=[15,45,90]
FAILED_JOB_BEHAVIOR=rethrow → failed_jobs; communication_logs.status=failed
IDEMPOTENCY_BEHAVIOR=skip send if communication_logs.status in (sent,delivered)
LOGGING=Laravel exception report + log status updates
CORRELATION_REFERENCE=communication_log_id
```
