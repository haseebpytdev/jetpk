# Phase 8 — Notification idempotency (Wave 1C)

```
NOTIFICATION_DUPLICATE_PROTECTION=PARTIAL
NOTIFICATION_IDEMPOTENCY_GAPS=events without communication_log_id; Mail::queue SendQueuedMailable paths without shared log skip
```

## Protections present

| Path | Protection |
|------|------------|
| DeliverQueuedOperationalMail | Skip send if `communication_logs.status` in `sent`,`delivered` |
| BookingCommunicationService | Event-level "already sent" guards before queue (customerEmailAlreadySent / operationalNotificationAlreadyLogged) |
| JetpkOperationalEmailService | Dedup window via `isDuplicate` / registry minutes |

## Gaps (accepted for Wave 1C)

- Historical Closures must never be replayed (serialization/idempotency unsafe)
- Direct Mail::queue without communication_log_id cannot use job-level skip
- Do not auto-resend CURRENT_REPLAY_REQUIRED without explicit safety proof

## Wave 1C policy

`OLD_SERIALIZED_CLOSURES_EXECUTED=0` — recreate only via named job + fresh log when replay approved.
