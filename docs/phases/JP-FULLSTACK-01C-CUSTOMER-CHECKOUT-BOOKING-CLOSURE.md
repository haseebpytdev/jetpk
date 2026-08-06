# JP-FULLSTACK-01C — Customer Checkout, Passengers and Booking Submission Closure

**Phase:** JP-FULLSTACK-01C
**Branch:** `phase/jetpk-fullstack-01c-customer-checkout-passengers-booking`
**Baseline:** `295828d7d3442c33c017f5323acee4a604197ca3`
**Status:** Implementation complete — **not committed** (stop for review)

## Objective

Close **JP-FS01-GAP-020** — verify the manual `pay_later` checkout path end-to-end using Laravel fixture suppliers and frontend Playwright mocks (no live supplier/booking/payment calls).

## 01C gap scope (register-locked)

| Gap ID | Severity | Status |
|--------|----------|--------|
| JP-FS01-GAP-020 | MEDIUM | **CLOSED** |

No other gaps are assigned to JP-FULLSTACK-01C in the gap register.

## Checkout flow trace (authoritative)

| Step | Next route | Frontend | Laravel | Session | CSRF |
|------|------------|----------|---------|---------|------|
| Fare selection | `/flights/fare-selection` | `FareSelectionPage` | `flights.results.offer` | optional | GET |
| Auth gate | login/register | auth layouts | `login`, `register` | — | — |
| Passengers GET | `/booking/passengers` | `StandardPassengersPage` | `GET booking.passengers?format=json` | booking draft | GET |
| Passengers POST | — | `submitStandardPassengers` | `POST booking.passengers` | sets `PublicBooking::SESSION_BOOKING_ID` | required |
| Review GET | `/booking/review` | `BookingReviewPage` | `GET booking.review?format=json` | booking id in session | GET |
| Review POST (manual) | — | `submitBookingReview('pay_later')` | `POST booking.review?format=json` | required | required |
| Manual payment | `/booking/payment/manual` | `ManualPaymentPage` | `GET booking/checkout-state?format=json` | required | GET |
| Confirmation | `/booking/confirmation` | confirmation page | `GET booking.confirmation?format=json` | required | GET |
| Card handoff | `/booking/payment/card` | `CardPaymentPage` | AbhiPay start (01D scope) | — | — |

**Manual payment (`pay_later`):** Review POST returns `next_url: /booking/payment/manual`; checkout-state exposes unpaid/pending status and manual instructions only — no fabricated paid/ticketed state.

**Blade fallbacks:** HTML requests to the same Laravel routes remain on Blade views; not removed in 01C.

## Implementation (GAP-020)

No application code changes required. Existing `BookingReviewPage`, `ManualPaymentPage`, and `booking-checkout-api.ts` already implement the authoritative path.

### Tests added / extended

| File | Change |
|------|--------|
| `frontend/tests/standard-booking-review-payment.spec.ts` | +4 Playwright tests for pay_later submit, manual payment state, duplicate-submit guard |

### Laravel tests (existing, re-run)

| Command | Result |
|---------|--------|
| `php artisan test tests/Feature/StandardBookingReviewJsonTest.php` | **8 passed**, exit 0 |

Covers: review JSON context, `pay_later` POST → `/booking/payment/manual`, checkout-state, payment status, invoice, confirmation JSON contracts.

### Frontend tests

| Command | Result |
|---------|--------|
| `npx playwright test tests/standard-booking-review-payment.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0` | **8 passed**, exit 0 |
| `npm run typecheck` | exit 0 |
| `npm run lint` | exit 0 |
| `npm run build` | exit 0 (during Playwright gate) |

### Note on PublicBookingFlowTest

Full `PublicBookingFlowTest` run on baseline reported **2 failures** unrelated to pay_later JSON verification (stale `OTA-` reference prefix assertion; admin `/admin/bookings` 403). Not modified in 01C (admin scope). GAP-020 verification uses `StandardBookingReviewJsonTest` + Playwright.

## Security / booking safeguards (observed)

- Client `submitBookingReview` sends only `booking_method`; price from Laravel `pricing` on review/checkout-state
- `resolveBookingNextUrl` allowlist blocks external handoffs
- `submitLock` prevents duplicate review POST from UI
- No passport data in localStorage (covered by `standard-booking-passengers.spec.ts`)
- OTP demo patch unchanged

## Remaining limitations

- Card payment completion and AbhiPay return → **01D** (GAP-004)
- Customer booking history / guest detail → **01E**
- Full `PublicBookingFlowTest` suite cleanup deferred (stale assertions on baseline)

## Rollback

Revert `frontend/tests/standard-booking-review-payment.spec.ts` and documentation updates; no migrations or application code changes.
