# Seat Selection Capability and Conditional Step Contract (JP-UI-04)

## Classification

**Future capability — conditional.** Seat map UI must remain hidden until Laravel confirms `seat_map_available: true`.

## Current operational state

| Item | Value |
|------|-------|
| `seat_map_available` | `false` (Laravel contract) |
| Seat selection route | **Does not exist** |
| UI scaffold | `features/seat-selection/types` only |
| Visual stepper | `seat_extras` step **omitted** when Laravel marks `state: "skipped"` |

## Progress step contract

Laravel `booking_session.progress[]` remains authoritative:

```json
{
  "key": "seat_extras",
  "label": "Seats",
  "state": "skipped",
  "href": null
}
```

Frontend behavior:

1. `visibleProgressSteps()` filters `state === "skipped"` from `BookingProgress`
2. `progressDisplayIndex()` renumbers remaining visible steps
3. No orphan connector line or gap in stepper
4. No navigation link to non-existent seat route

## Implementation

| File | Responsibility |
|------|----------------|
| `features/booking-layout/constants/journey-steps.ts` | `visibleProgressSteps`, `progressDisplayIndex`, label map |
| `features/booking-progress/components/BookingProgress.tsx` | Renders only visible steps |

## When JP-OPS enables seats

Future phase requirements (not JP-UI-04):

1. Laravel sets `seat_map_available: true` and `seat_extras` state to `upcoming`/`current`/`completed`
2. New route (e.g. `/booking/seats`) with `SeatMap` component
3. Seat numbers and prices from supplier only — no hardcoded aircraft layout
4. Visual audit scenarios for seat map light/dark/mobile
5. Update this contract and `MOCKUP-VS-ACTUAL-MISMATCH-REGISTER.md`

## Mockup reference

Mockup **#12** — documented only; not implemented.

## Visual audit

No seat-map captures in JP-UI-04 matrix. Progress scenario `prog-01` verifies stepper renders without seat step when skipped.

## Rules

- **Never** show seat selection UI with `seat_map_available: false`
- **Never** hardcode seat numbers or prices
- **Never** link to a seat route that Laravel has not authorized
