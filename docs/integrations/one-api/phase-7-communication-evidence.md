# Phase 7 — Communication evidence

## Policy

- **No dedicated hold/pending customer email** exists in `BookingCommunicationEvent`. Hold bookings **do not** call `sendSupplierBookingCreated`.
- Hold state is persisted (PNR, TID, deadline, `on_hold` status) and a **system** `booking_status_changed` log records the policy (`hold_persisted_no_customer_hold_email_template`).
- **Hold payment** uses `OneApiSupplierHoldPaymentOrchestrator` via `SupplierBookingService::payHeldOneApiReservation` after confirmed ticketed re-read; emits **`sendTicketIssued` once** (idempotent via existing communication logs).

## Orchestration paths

| Flow | Entry | Communication |
|------|--------|----------------|
| Paid / immediate book | `SupplierBookingService::createSupplierBooking` | `sendSupplierBookingCreated` when not on hold |
| On-hold book | Same | Suppressed; status log only |
| Hold payment | `SupplierBookingService::payHeldOneApiReservation` | `sendTicketIssued` after ticketed read |
| Ambiguous book | `OneApiBookingService` | No success communication |

## Tests

- `OneApiCommunicationIntegrationTest`
- `OneApiBookingAmbiguousTest` (no supplier_booking_created log)

## PHPUnit

`vendor/bin/phpunit --filter=OneApi` — communication cases included.
