# WAVE-1C PRE-MUTATION SNAPSHOT

Captured before any Wave 1C production mutation. No queue drain. No worker start on `default`.

```
PROTECTED_MAIN_SHA=daa5a1a2465cc1504d9b448ed94db307be976cf5
PRODUCTION_RUNTIME_SHA=12045dda48c98fefc24db5b1c2e071e7498be87a
LIVE_BUILD_ID=7-iZf4RbaJmGpuDjO1tsh

PENDING_DEFAULT_JOBS_PRE=178
FAILED_JOBS_PRE=0

DISK_USED_PERCENT=73
HOMEPAGE_HTTP=200
UP_HTTP=200

QUEUE_CONNECTION=database
QUEUE_DEFAULT=default

CRON_QUEUE_WORKER=
* * * * * php artisan queue:work database --stop-when-empty
  --queue=notifications-critical,notifications-transactional,notifications-ops,notifications-bulk
  --tries=5 --timeout=90

PERSISTENT_QUEUE_WORKER_PROCESSES=0
DEFAULT_WORKER_STARTED=NO
```

## Pending default composition (pre)

| Group | Count | Type | Notes |
|------|------:|------|-------|
| 1 | 169 | Closure → OtaNotificationService::sendMailNow | Includes jobs from today |
| 2 | 9 | BookingUniversalNotification | STALE_BOOKING_PAYMENT_MAIL; SAFE_TO_DRAIN=NO |

## Safety freeze

- Do not start `php artisan queue:work default`
- Do not execute serialized Closures
- Do not deliver stale booking/payment mail
- OPS-DEF-0020 (public/storage) out of scope
