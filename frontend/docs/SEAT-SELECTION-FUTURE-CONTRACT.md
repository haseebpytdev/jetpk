# Seat Selection Future Contract (JP-FE-07 readiness)

## Audit result

No complete authoritative seat-map contract exists for Group Ticketing or standard supplier booking in Laravel at JP-FE-07 closure.

Group inventory is seat-count-based (`total_seats`, `held_seats`, `sold_seats`), not per-seat maps.

## Future boundary

`frontend/features/seat-selection/types/index.ts` defines:

- `SeatMapResponse`, `SeatMapSegment`, `Seat`, `PassengerSeatSelection`
- Integration must use Laravel-only endpoints when available
- Never call suppliers directly from Next.js
- Never fabricate seat numbers, availability, or prices

## Optional copy (when appropriate)

"Seat selection is not currently available for this booking. Seat assignment is subject to airline or ticketing confirmation."

## Future backend fields (documentation only)

`search_id`, `offer_id`, `booking_id`, `group_booking_id`, `segment_id`, `passenger_id`, `seat_map_version`, `seat_number`, `seat_status`, `seat_price`, `currency`, `selection_token`, `expires_at`
