# P1 migration result

Engineering SHA recorded at commit time.

## Implemented

- `notification_outbox`, `notification_deliveries` (UNIQUE `idempotency_key`), `notification_routes`
- Outbox + delivery services, allowlisted route strategies seeded from `POLICY_BUCKETS`
- `DispatchNotificationOutboxEvent`, `DeliverNotification`, `DeliverQueuedOperationalMail`
- `OtaNotificationService::send` publishes through the pipeline then delivers via existing renderer (P0 auth shell unchanged)
- Anonymous Mail queue closure replaced with `DeliverQueuedOperationalMail`
- `notifications:status` read-only command
- Default `NOTIFICATION_PIPELINE_ASYNC=false` so production does not need new queue workers

## Not migrated (retained)

| FILE | METHOD | WHY | NEXT |
|---|---|---|---|
| LoginOtpService | Mail::to send | OTP latency/UX | P2 critical queue after worker split |
| RegisteredUserController | welcome/signup Mail::send | sync registration UX | pipeline wrap |
| BestEffortEmailVerification | notify() | signed URL security | keep Laravel |
| Password reset | Laravel notification | tokens | keep Laravel |
| BookingCommunicationService | Mail send/queue | customer modern renderer | later |
| NotificationRecipientResolver::POLICY_BUCKETS | fallback | used when no DB route | keep until fallback_count=0 in prod logs |

## Tests

`NotificationPipelineArchitectureTest` + P0 `AuthLoginSecurityEmailCanonicalTest` + `NotificationRecipientRoutingTest` + fail-soft auth.

CommunicationLog remains reporting mirror.
