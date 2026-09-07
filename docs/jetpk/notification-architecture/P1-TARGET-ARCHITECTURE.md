# P1 target architecture

```
Business action
  → NotificationOutbox (same DB transaction)
  → DispatchNotificationOutboxEvent
  → notification_routes (allowlisted strategies)
  → notification_deliveries (UNIQUE idempotency_key)
  → DeliverNotification
  → existing OtaNotificationService render/send
  → JetpkEmailEventRenderer / canonical shell
  → CommunicationLog mirror
```

Queues (logical): `notifications-critical|transactional|ops|bulk`.  
P1B: after-commit dispatch; DB routes (agency then global) drive queue metadata; `pipeline.async` remains env-driven. Dedicated workers consume `notifications-*` queues. `pipeline.async=false` still processes inline via `dispatch_sync` after commit.

Event identity precedence: explicit event id → immutable occurrence id → explicit variant → proven one-shot aggregate → otherwise fresh occurrence UUID.

Idempotency: `sha256(event_id|channel|audience|normalized_email|variant)`.

OTP / password-reset / verification stay on existing Laravel/Mail paths (security latency).
