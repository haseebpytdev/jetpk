# JP-EMAIL-RECOVERY-01 — Queue / retry / suppression

```
QUEUE_RENDER_PARITY=PASS
IMMEDIATE_RENDER_PARITY=PASS
RETRY_BACKOFF_PRESERVED=PASS
DEDUP_PRESERVED=PASS
PERMANENT_FAILURE_CLASSIFICATION_PRESERVED=PASS
PERMANENT_RECIPIENT_SUPPRESSION_PRESERVED=PASS
COMMUNICATION_LOGGING_PRESERVED=PASS
```

## Proof notes

- `DeliverQueuedOperationalMail` retains `tries=5`, `backoff=[15,45,90]`, notification queue binding.
- Job mailable selection now prefers non-empty pre-rendered HTML (JetPK shell) over legacy `BookingUniversalNotification`.
- `DeliverQueuedOperationalMailTest` still covers send, already-sent skip, permanent 550, transient rethrow.
- `RecipientDeliverySafetyService` + permanent-recipient skip logging remain on `OtaNotificationService::send`.
- No real SMTP provider contacted in tests (`Mail::fake` / array mailer).
