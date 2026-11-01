# Canonical persistence after DEFINITIVE_PROVIDER_REJECTION

| Field | Value |
|-------|-------|
| TicketingAttempt count | 1 |
| TicketingAttempt status | failed |
| BookingTicket count | 0 |
| booking.status | payment_pending |
| ticketing_status | pending_ticketing |
| SupplierBooking | pending_ticketing |
| payment | unpaid / 0.00 |
| CANONICAL_TICKETING_PERSISTENCE | N/A (not success) |
| CANONICAL_UNCERTAIN_PERSISTENCE | N/A |

Attempt safe fields: `error_code=provider_error`; safe_summary keys include `correlation_id`, `provider_errors` (not printed).
