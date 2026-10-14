# JP-OPS-READINESS-01 — Booking State Machine

**Source of truth:** `app/Enums/BookingStatus.php`, `app/Services/Booking/BookingService.php`  
**Derived:** 2026-10-14 (audit-only; no invention)

## Canonical booking statuses

| Status | Value |
|--------|-------|
| Draft | `draft` |
| Pending | `pending` |
| Fare Review | `fare_review` |
| Confirmed | `confirmed` |
| Payment Pending | `payment_pending` |
| Paid | `paid` |
| Ticketing Pending | `ticketing_pending` |
| Ticketed | `ticketed` |
| Cancelled | `cancelled` |
| Expired | `expired` |
| Failed | `failed` |
| Refunded | `refunded` |

## Allowed transitions (non–platform-admin)

From `BookingService::getAllowedStatusTransitions`:

```
draft            → pending
pending          → fare_review | confirmed | cancelled
fare_review      → confirmed | cancelled
confirmed        → payment_pending | cancelled
payment_pending  → paid | cancelled
paid             → ticketing_pending | refunded
ticketing_pending→ ticketed | failed
failed           → ticketing_pending | cancelled
ticketed         → refunded
cancelled|expired|refunded → (terminal)
```

Platform admin may transition to any other status.

## Side effects on `changeStatus`

| To | DB / side effects |
|----|-------------------|
| Any change | `BookingStatusLog`, audit `booking.status_changed` |
| `confirmed` | `confirmed_at`; `LedgerEventRecorder::recordMarkupRevenueForBooking`; confirmation email |
| Any | `sendBookingStatusChanged` communication |

## Parallel / satellite state fields (not enum-unified)

| Field | Meaning |
|-------|---------|
| `payment_status` | `unpaid` / `partial` / `paid` via `BookingPaymentService` |
| `supplier_booking_status` | free string; normalized by `SupplierOperationalStatus` |
| `ticketing_status` | free string on booking |
| Cancellation request | `BookingCancellationStatus` |
| Refund record | `BookingRefundStatus` |
| Group booking | `GroupBookingStatus` (separate lifecycle) |
| IATI | `IatiReservationLifecycleStatus` |
| Sabre auto-PNR | `SabreGdsAutoPnrLifecycleStatus` |

## Known operational risks (audit)

1. Payment success with booking still non-paid if settlement fails after gateway paid (see payment SM).
2. Admin unrestricted transitions can create impossible ops combinations with satellite fields.
3. `Paid → refunded` allowed without requiring `ticketed`/`cancelled` first — may desync ticket/PNR presentation.
4. Supplier/PNR creation is not a single transition in this enum; it is orchestrated via `SupplierBookingService` / provider adapters.
