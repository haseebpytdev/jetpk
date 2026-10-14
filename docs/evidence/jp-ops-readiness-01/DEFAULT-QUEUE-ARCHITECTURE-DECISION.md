# DEFAULT QUEUE ARCHITECTURE DECISION — Wave 1C

```
IS_DEFAULT_QUEUE_INTENTIONAL=PARTIAL
```

## Verdict

| Question | Answer |
|----------|--------|
| Are the 178 pending `default` jobs intentional? | **NO** — routing defect (Closures / Mail::queue without `onQueue`) |
| Does any legitimate non-notification work target `default`? | **YES (class only)** — `RemoveBrandLogoBackground` has no `onQueue`, so async dispatch would use `default` |
| Is that branding job async in production today? | **NO** — only `dispatchSync` from `BrandingLogoBackgroundController` |
| Persistent default worker required to close OPS-DEF-0019? | **NO** |

```
DEFAULT_QUEUE_EXPECTED_JOB_CLASSES=
(none required for mail) — branding may use default if/when async

DEFAULT_QUEUE_CURRENT_JOB_CLASSES=
CallQueuedClosure (169) — accidental
SendQueuedMailable/BookingUniversalNotification (9) — accidental/stale

PERSISTENT_DEFAULT_WORKER_REQUIRED=NO
CLASSIFICATION_OF_BACKLOG=ROUTING_DEFECT
```

## Intended topology (post-fix)

```
OtaNotificationService / Mail::queue producers
  → DeliverQueuedOperationalMail OR SendQueuedMailable
  → queue = config('mail.notification_queue') = notifications-transactional
  → cron queue:work --queue=notifications-critical,notifications-transactional,notifications-ops,notifications-bulk
```

Do **not** add `default` to the notification cron list merely to drain historical Closures.

## Booking / payment / supplier / wallet / ledger

No producer in those domains intentionally targets `default` for durable async work in the audited paths. Notification side effects only.
