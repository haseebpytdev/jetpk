# JP-FE-09 — Standard Flight Review, Payment, Manual/Card Checkout, State, Invoice and Confirmation Handoff

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-09-STANDARD-FLIGHT-REVIEW-PAYMENT-MANUAL-CARD-CHECKOUT-STATE-INVOICE-AND-CONFIRMATION-HANDOFF |
| Branch | `phase/jetpk-fe-09-standard-flight-review-payment` |
| Feature commit | `ebb8066` |
| Docs commit | `ebb8066` (included in feature commit) |
| Merge commit | `a99a2d5` |
| Final SHA doc commit | _(pending)_ |
| Status | Complete |

## Objective

Operational standard-flight review and payment in Next.js backed by existing Laravel booking and AbhiPay engines—no duplicate payment/checkout engine.

## Audited Laravel contracts

| Endpoint | Auth | Session | Notes |
|----------|------|---------|-------|
| GET/POST `/booking/review` | Guest | `ota_public_booking_id` | Blade preserved; JSON additive |
| GET `/booking/checkout-state` | Guest | Session booking | Post-submit state |
| GET `/booking/payment/status` | Guest | Session booking | Poll payment status |
| GET `/booking/invoice` | Guest | Session booking | Invoice JSON |
| POST `/payments/abhipay/start/{booking}` | Auth/guest token | Booking policy | Hosted checkout URL |
| GET `/payment/success` etc. | Guest | Query ref | Browser return; not proof |

**Payment methods (JetPakistan):** Manual (`pay_later`) and Pay by Card (`online_card`) only.

**Booking creation:** Unchanged—review POST submits booking via existing `processReviewSubmit`; card payment after submit.

## Next.js routes

- `/booking/review`
- `/booking/payment/manual`
- `/booking/payment/card`
- `/booking/payment/status`
- `/booking/payment/return` → status
- `/booking/invoice`

## Tests executed

| Suite | Result |
|-------|--------|
| `php artisan test tests/Feature/StandardBookingReviewJsonTest.php` | 6 passed, 37 assertions |
| `npm run typecheck` | Pass |
| `npm run lint` | Pass |
| `npm run build` | Pass (31 routes) |
| `npx playwright test tests/standard-booking-review-payment.spec.ts` | 4 passed |

## Known limitations

- Full JP-FE-10 booking-success page not duplicated; handoff to `/booking/confirmation` when terminal
- Manual payment has no proof-upload on standard flow (matches Blade)
- Invoice PDF only when Laravel document generated
- Playwright smoke tests run without live Laravel (missing-session states)

## No deployment

Production untouched.

## Next phase

JP-FE-10-STANDARD-FLIGHT-BOOKING-SUCCESS-ITINERARY-INVOICE-PAYMENT-STATUS-AND-POST-BOOKING-ACTIONS
