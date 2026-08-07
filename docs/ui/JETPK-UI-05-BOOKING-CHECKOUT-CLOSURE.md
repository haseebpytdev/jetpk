# JETPK-UI-05 — Booking Checkout Progress Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JETPK-UI-05 |
| Branch | `phase/jetpk-ui-05-booking-checkout-closure` |
| Baseline | `5fd1b0732cb57e47551489bb113770c3cdbb73e0` |
| Gap | JETPK-UI-006 |
| Commit | `feat: close JetPakistan booking flow gap` |
| Deployment | NOT PERFORMED |

## Root cause

Standard booking pages forced `compact` on `BookingProgress`, hiding visible step labels at 1280×800 despite the connected stepper already existing from JP-UI-04. Laravel progress labels (`Flight Selected`, `Passenger Details`) diverged from mockup vocabulary.

## Changes

- Removed `compact` from passengers, review, payment, and confirmation pages.
- `BookingProgress` maps `BOOKING_JOURNEY_STEP_LABELS` by step key.
- Fixed duplicate label rendering; labels visible from 480px+.
- Added Playwright assertion for connected progress labels.

## Gap closure

| Gap | Status |
|-----|--------|
| JETPK-UI-006 | **CLOSED** |

**Remaining open gaps:** 14

## Tests

- `standard-booking-passengers.spec.ts` (including new progress label test)

## Final status

Pending merge after acceptance PASS.
