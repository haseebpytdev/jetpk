# JP-FRONTEND Validation Matrix

## Level A — Immediate client

Required fields, email/phone format, date relationships, traveler counts, password confirmation.

## Level B — Async server

OTP, account state, fare availability, passenger business rules, ownership, permissions, payment state.

## Level C — Final transaction

Booking creation, PNR, payment, ticketing, cancellation, refund.

## UX requirements

- Map 422 errors to fields via `mapFieldErrors`
- Form-level error summary
- Focus first invalid field
- Preserve valid input on failure
- `aria-invalid`, `aria-describedby`
- Remove stale field errors on correction

## PII policy

No passport, identity, DOB, contact, or passenger names in localStorage.
