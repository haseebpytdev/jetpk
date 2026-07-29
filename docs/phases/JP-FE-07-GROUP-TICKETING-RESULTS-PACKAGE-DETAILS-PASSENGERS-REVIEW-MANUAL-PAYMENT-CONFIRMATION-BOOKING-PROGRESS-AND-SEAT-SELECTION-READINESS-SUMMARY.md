# JP-FE-07 — Group Ticketing Results, Package Details, Passengers, Review, Manual Payment, Confirmation, Booking Progress, and Seat Selection Readiness

## Phase name

JP-FE-07-GROUP-TICKETING-RESULTS-PACKAGE-DETAILS-PASSENGERS-REVIEW-MANUAL-PAYMENT-CONFIRMATION-BOOKING-PROGRESS-AND-SEAT-SELECTION-READINESS

## Branch

`phase/jetpk-fe-07-group-ticketing-flow`

## Objective

Operational Next.js Group Ticketing flow from search through manual-payment confirmation using existing Laravel engine.

## Included scope

- Laravel additive JSON on group search/booking routes
- Next.js `/groups/*` operational routes
- Booking progress component
- Seat-selection future contract (no operational seat map)
- Targeted Laravel + Playwright tests
- Architecture documentation

## Excluded scope

- Card/AbhiPay/wallet payment
- Supplier seat maps
- Blade route removal
- Production deployment

## Hold behavior

- Draft at passenger submit (`pending_passenger_details`, no `expires_at`)
- Hold at review confirm (`reserved_awaiting_payment`, `expires_at` +25 min default)
- Release via scheduled command + payment page expiry check

## Repeat-offender

Three unpaid timeout releases → lock (`GroupBookingRestrictionService::BLOCK_THRESHOLD = 3`)

## Tests executed

| Suite | Result |
|-------|--------|
| `php artisan test tests/Feature/GroupTicketing/GroupTicketingNextJsonContractTest.php` | 5 passed |
| `npm run typecheck` | pass |
| `npm run lint` | pass |
| `npm run build` | pass |
| Playwright `group-ticketing.spec.ts` + `search-laravel-group-handoff.spec.ts` | 7 passed |

## Commit SHAs

- Feature: `728d0a0`
- Docs: `b4f7b7a`
- Merge: `db6dad1`
- Final SHA doc: `8f258a1`

## Final status

FINAL_PASS — all targeted tests green; main merged with no-ff.

## Next phase

JP-FE-08-STANDARD-FLIGHT-PASSENGER-CONTACT-DOCUMENTS-AND-BOOKING-SESSION-FLOW

## No-deployment confirmation

Production untouched.
