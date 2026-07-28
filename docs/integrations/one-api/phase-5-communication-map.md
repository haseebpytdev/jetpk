# Phase 5 — One API communication map

## Orchestration path (paid PNR create)

One API paid bookings flow through **`SupplierBookingService::executeSupplierBooking`** (existing platform orchestration), not direct calls from SOAP parsers.

On **successful** adapter result:

1. `SupplierBooking` row created  
2. Booking PNR / supplier reference updated  
3. **`BookingCommunicationService::sendSupplierBookingCreated($booking)`** — idempotent via `supplierBookingCreatedAlreadyLogged()`

On **failure** / **manual_review**:

- `notifySupplierFailure` or `notifyManualReviewRequired` — **no** success booking email

On **ambiguous** One API adapter result:

- Attempt marked ambiguous; **no** `sendSupplierBookingCreated`

## One API adapter scope

- `OneApiSupplierBookingAdapter` → `OneApiBookingService` — supplier SOAP + persistence of attempt only; **does not** send mail directly (by design).

## Hold / hold-payment

- Hold booking and hold-payment modify paths are **not** fully wired to `BookingCommunicationService` in this phase.
- Policy for hold/pending templates remains in existing communication service; dedicated One API hold comms tests **not yet added**.

## Idempotency

- Reuse platform `CommunicationLog` / `supplierBookingCreatedAlreadyLogged` for paid supplier booking created.
- Duplicate `SupplierBookingAttempt` success gate in `OneApiBookingService` prevents double supplier book; router prevents double success comms when attempt fails.

## Phase 5 gap

- Dedicated PHPUnit proofs for communication once per event (paid / hold / hold-pay / no comms on ambiguous) — **pending** follow-up tests mocking `BookingCommunicationService`.
