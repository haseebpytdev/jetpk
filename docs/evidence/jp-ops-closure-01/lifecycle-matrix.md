# Booking / payment / ticketing / cancellation state machines

Authority from code inspection (JP-OPS-CLOSURE-01). Do not invent names.

## BOOKING_STATE_MACHINE

`App\Enums\BookingStatus` — `app/Enums/BookingStatus.php`

| Value | Writer notes |
|---|---|
| draft | `BookingService::createDraftBooking` |
| pending | Public Review confirm via `submitBookingRequest` |
| fare_review | Staff/admin `changeStatus` |
| confirmed | Staff/admin `changeStatus` |
| payment_pending | Staff/admin from `confirmed` only (not public checkout) |
| paid | `BookingPaymentService::recalculateBookingPaymentStatus` when fully verified |
| ticketing_pending | Auto after paid if `supplier_booking_status=pending_ticketing` |
| ticketed | `TicketingService::issueTickets` (bypasses graph) |
| cancelled | Cancellation process / staff |
| expired | **Enum only before this phase — never assigned in app/** |
| failed | Staff/admin |
| refunded | `BookingRefundService` when cancelled + refunds complete |

Public Guest/Customer path: **draft → pending** (payment_status unpaid). Not payment_pending.

Allowed graph: `BookingService::getAllowedStatusTransitions`.

## PAYMENT_STATE_MACHINE

Per-row `BookingPaymentStatus`: pending | submitted | verified | rejected | refunded | partial

Aggregate `bookings.payment_status` string: unpaid | partial | paid

Gateway `PaymentTransactionStatus`: initiated → created/pending → paid/failed/…

## TICKETING_STATE_MACHINE

No enum. `bookings.ticketing_status` strings include: not_started, pending, processing, ticketed, failed, not_supported, ticketing_requires_review, voided, …

`AUTO_TICKET_AFTER_PAYMENT=NO` — verify payment never calls `TicketingService::issueTickets`.

## CANCELLATION_STATE_MACHINE

`BookingCancellationStatus`: requested | approved | rejected | processed | cancelled

Request does not cancel supplier; process may.

## PAYMENT DEADLINE (pre-fix)

```
BUSINESS_PAYMENT_WINDOW=NOT_CONFIGURED (gap)
SUPPLIER_DEADLINE_SOURCE=payment_required_by / price_guarantee / hold_expires / PNR meta
SAFETY_BUFFER=NONE
EFFECTIVE_DEADLINE_RULE=checkout lock only (OTA_CHECKOUT_LOCK_MINUTES default 7); payment_due_at unused
EXPIRY_JOB=NONE for standard bookings (group-ticketing:release-expired only)
REMINDER_JOB=NONE (manual payment_reminder_manual only)
AUTO_CANCEL_JOB=NONE for App\Models\Booking
```

## Critical race requirement

Expiry worker must lock + re-read payment/booking state before expiring. Paid must never auto-expire.
