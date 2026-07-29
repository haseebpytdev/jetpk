# Booking Progress Architecture (JP-FE-07)

## Component

`frontend/features/booking-progress/components/BookingProgress.tsx`

## Group Ticketing steps

1. Package Selected
2. Passenger Details
3. Review
4. Manual Payment
5. Confirmation

## Standard supplier steps (JP-FE-08)

1. Flight Selected
2. Passenger Details
3. Seat & Extras
4. Review
5. Payment
6. Confirmation

Implemented for standard flight at `/booking/passengers` via `StandardBookingJsonPresenter::progressState()`.

## Authority

Step state comes from Laravel `progress` arrays in booking JSON payloads. Completed steps require Laravel booking status confirmation—not URL visits alone.

## Usage

Pass `steps` from Laravel response. Optional `href` on completed steps when safe return is supported.
