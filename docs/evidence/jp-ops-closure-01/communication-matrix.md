# Communication matrix (JP-OPS-CLOSURE-01)

## Authority

```
RECIPIENT_RESOLUTION_AUTHORITY=NotificationRecipientResolver
ASSIGNED_USER_AUTHORITY=bookings.assigned_staff_id
FALLBACK_RECIPIENT_AUTHORITY=PlatformAdmin / agency support / config client.canonical_support_email
DUPLICATE_SUPPRESSION=CommunicationLog event (+ meta keys); payment reminders use meta.reminder_stage
```

## Events (actual keys)

| EVENT | CUSTOMER | ASSIGNED_STAFF | FINANCE | ADMIN | EMAIL | IN_APP | AUDIT | DEDUP |
|---|---|---|---|---|---|---|---|---|
| booking_request_received | Y | N (policy) | N | Y | Y | N | N | booking+event |
| payment_reminder | Y | N | N | N | Y | N | N | reminder_stage |
| payment_proof_submitted | N | Y | Y | Y | Y | N | Y | payment id (B2B) |
| payment_verified | Y | Y | N | Y | Y | N | Y | partial |
| payment_rejected | Y | Y | N | Y | Y | N | Y | partial |
| ticket_issued | Y | Y | N | Y | Y | N | Y | partial |
| cancellation_requested | Y | Y | N | Y | Y | N | Y | request id |
| booking_expired | Y | Y | N | Y | Y | N | via status log | booking_expired |
| payment_deadline_expired | NOT_SEPARATE | — | — | — | — | — | — | covered by booking_expired |
| supplier_cancel_failure | NOT_IMPLEMENTED as notify | — | — | — | — | — | cancel services | — |

```
IN_APP_NOTIFICATIONS=PARTIALLY_IMPLEMENTED (ops inbox for assign/notes/support; not full lifecycle)
MAIL_FAILURE_BOOKING_STATE_SAFE=YES
```

## New in this phase

- `booking_expired` customer + ops
- `payment_reminder` customer (first/final stages)
