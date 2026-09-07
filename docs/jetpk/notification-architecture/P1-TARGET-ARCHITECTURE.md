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
Production dispatch uses `notifications.pipeline.compat_queue` (`default`) until dedicated workers exist. `pipeline.async=false` processes inline so jobs cannot accumulate.

Idempotency: `sha256(event_id|channel|audience|normalized_email|variant)`.

OTP / password-reset / verification stay on existing Laravel/Mail paths (security latency).
