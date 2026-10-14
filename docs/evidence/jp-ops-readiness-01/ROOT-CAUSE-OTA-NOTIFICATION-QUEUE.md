# Phase 3 — OtaNotificationService → sendMailNow root cause

```
WHY_CLOSURE_DISPATCHED=
Historical dispatchMail() used bare dispatch(function () { sendMailNow(...); })
when shouldQueueMail() was true (mail driver not log/array/local AND queue.default ≠ sync).
Laravel serializes that as CallQueuedClosure onto the connection default queue name "default".

WHY_QUEUE_DEFAULT_SELECTED=
No ->onQueue(...) on the Closure dispatch. QUEUE_CONNECTION=database uses queue name "default".

EXPECTED_NOTIFICATION_QUEUE=notifications-transactional
(via config('mail.notification_queue'); consumed by production cron)

EXISTING_DELIVERY_JOB_CLASS=App\Jobs\Notifications\DeliverQueuedOperationalMail
(restored/reintroduced in Wave 1C — named ShouldQueue job)

EXISTING_DELIVERY_QUEUE=notifications-transactional
```

## Fix

`OtaNotificationService::dispatchMail` now dispatches `DeliverQueuedOperationalMail`
(constructor sets `onQueue(config('mail.notification_queue'))`).

Parallel Mail::queue producers also set `onQueue` the same way.
