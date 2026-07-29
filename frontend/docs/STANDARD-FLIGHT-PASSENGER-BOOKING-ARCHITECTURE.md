# Standard Flight Passenger Booking Architecture (JP-FE-08)

## Route decision

| Route | Owner | Purpose |
|-------|-------|---------|
| `/booking/passengers` | Next.js | Passenger, contact, document entry |
| `/booking/review` | Laravel Blade | Review (JP-FE-09) |
| `/booking/confirmation` | Laravel Blade | Confirmation |

Handoff from flight results/revalidation uses `/booking/passengers` on the Next.js origin. Review handoff uses Laravel `absoluteLaravelHandoffUrl`.

## Laravel authority

- Session draft (`BookingDraftService` / `ota_booking_draft`)
- Offer validation (`OfferValidationService`, `FareHoldService`)
- Passenger validation (`StoreBookingPassengersRequest`)
- Document rules (`InternationalRouteDetector`)
- Draft booking creation (`BookingService`)
- Progress and next URL

## JSON contracts

### GET `/booking/passengers?format=json`

Query: same as Blade (`search_id`, `offer_id`, `from`, `to`, `depart`, traveller counts, fare keys).

Returns `StandardBookingJsonPresenter::presentPassengersContext()`.

### POST `/booking/passengers` (Accept: application/json)

Same payload as Blade form. Success: `{ ok, status: "accepted", next_url: "/booking/review", progress }`.

Errors: 404 missing session, 410 offer expired, 422 validation.

## Next.js feature module

```
frontend/features/standard-booking/
├── components/
├── services/standard-booking-api.ts
├── types/
├── utils/allowlist.ts, passenger-form.ts
└── index.ts
```

## Privacy

- Form state in memory only
- No PII in URL or browser storage
- Review navigation allowlisted

## Seat & Extras

`seat_extras_capability.seat_map_available` is always `false` in this phase. No operational seat map.
