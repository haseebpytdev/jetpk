# JP-FE-08 — Standard Flight Passenger, Contact, Documents, Booking Session Progress and Seat Extras Readiness

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-08-STANDARD-FLIGHT-PASSENGER-CONTACT-DOCUMENTS-BOOKING-SESSION-PROGRESS-AND-SEAT-EXTRAS-READINESS |
| Branch | `phase/jetpk-fe-08-standard-flight-passenger-flow` |
| Feature commit | `2a22565` |
| Docs commit | _pending_ |
| Merge commit | _pending_ |
| Final SHA doc commit | _pending_ |
| Status | Complete (pending merge SHA update) |

## Objective

Implement operational standard-flight passenger, contact, and document entry in Next.js using the existing Laravel booking engine, with authoritative booking-session recovery and seat-extras readiness boundary.

## Included scope

- Laravel additive JSON on `GET|POST /booking/passengers` (`?format=json` / `Accept: application/json`)
- `StandardBookingJsonPresenter` for session, itinerary, requirements, progress
- Next.js `/booking/passengers` route and `features/standard-booking/` module
- Handoff from results/revalidation to Next.js passenger route
- Reused `BookingProgress` with standard six-step configuration
- Seat & Extras typed capability boundary (no fake seat map)
- Targeted Laravel and Playwright tests
- Architecture documentation

## Excluded scope

- Review, payment, confirmation in Next.js (JP-FE-09)
- Operational seat maps or ancillaries UI
- PNR, ticketing, payments, supplier search changes

## Audited Laravel contract

| Endpoint | Method | Auth | Notes |
|----------|--------|------|-------|
| `/booking/passengers` | GET | Guest OK | JSON context or Blade view |
| `/booking/passengers` | POST | Guest OK | Creates draft booking, sets `ota_public_booking_id` |
| `/flights/results/revalidate-offer` | POST | Guest | Pre-handoff revalidation (unchanged) |

**Session identity:** Laravel session draft (`ota_booking_draft`) + query `search_id`/`offer_id`; opaque `booking_session.id` is SHA-256 hash (non-PII).

**Passenger counts:** From search/offer draft; immutable in UI.

**Lead passenger:** Index 0 must be adult (`lead_passenger_index`).

**Document rules:** `InternationalRouteDetector` — passport required international; CNIC allowed PK domestic.

## Operational routes

- Next.js: `/booking/passengers?search_id=&offer_id=&from=&to=&depart=&adults=&children=&infants=`
- Laravel review handoff: `/booking/review` (Blade, JP-FE-09)

## Seat & Extras decision

No complete authoritative seat-map contract. `seat_map_available: false`; progress step remains upcoming; non-blocking readiness panel only.

## Tests executed

| Suite | Result |
|-------|--------|
| `php artisan test tests/Feature/StandardBookingPassengersJsonTest.php` | 3 passed, 24 assertions |
| `npm run typecheck` | Pass |
| `npm run lint` | Pass |
| `npm run build` | Pass (`/booking/passengers` route built) |
| `npx playwright test tests/standard-booking-passengers.spec.ts` | 5 passed |

## Files changed

- `app/Support/Booking/StandardBookingJsonPresenter.php`
- `app/Http/Controllers/Frontend/BookingController.php`
- `frontend/features/standard-booking/**`
- `frontend/app/(public)/booking/passengers/page.tsx`
- `frontend/features/flight-details/hooks/use-revalidation.ts`
- `frontend/features/flight-details/utils/handoff.ts`
- `frontend/features/flight-results/hooks/use-offer-selection.ts`
- `tests/Feature/StandardBookingPassengersJsonTest.php`
- `frontend/tests/standard-booking-passengers.spec.ts`
- `frontend/docs/*` (architecture updates)

## Known limitations

- Review/payment remain Laravel Blade
- One API ancillaries flagged but not integrated at passenger step
- Multi-city inquiry-only checkout still redirects via Laravel (unchanged)

## Rollback

Revert merge commit on `main`; Blade `/booking/passengers` remains functional.

## Next phase

JP-FE-09-STANDARD-FLIGHT-REVIEW-PAYMENT-MANUAL-AND-CARD-CHECKOUT-STATE-AND-INVOICE-FLOW

## No deployment

Production untouched.
