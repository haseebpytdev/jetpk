# Standard flight checkout architecture (JP-FE-09)

## Laravel authoritative endpoints

| Route | Method | JSON | Purpose |
|-------|--------|------|---------|
| `/booking/review` | GET | `?format=json` | Review context |
| `/booking/review` | POST | `?format=json` | Submit review + create booking |
| `/booking/checkout-state` | GET | `?format=json` | Post-submit checkout state |
| `/booking/confirmation` | GET | `?format=json` | Post-booking confirmation (`presentConfirmation`) |
| `/booking/payment/status` | GET | `?format=json` | Payment status polling |
| `/booking/invoice` | GET | `?format=json` | Invoice presentation |
| `/payments/abhipay/start/{booking}` | POST | `?format=json` | Card initiation (auth) |
| `/guest/bookings/{booking}/access/{token}/abhipay/start` | POST | `?format=json` | Card initiation (guest) |

Blade routes remain unchanged as migration fallback.

## Next.js routes

| Route | Component |
|-------|-----------|
| `/booking/review` | `BookingReviewPage` |
| `/booking/payment/manual` | `ManualPaymentPage` |
| `/booking/payment/card` | `CardPaymentPage` |
| `/booking/payment/status` | `PaymentStatusPage` |
| `/booking/payment/return` | Redirect to status |
| `/booking/invoice` | `InvoicePage` |
| `/booking/confirmation` | `BookingConfirmationPage` |
| `/booking/status` | `BookingConfirmationPage` |
| `/lookup-booking` | `BookingLookupPage` |

JP-FE-10: Confirmation, status alias, lookup, and enhanced invoice/payment-status pages consume `presentConfirmation` and related JSON contracts. See `BOOKING-SUCCESS-AND-POST-BOOKING-ARCHITECTURE.md`.

## Payment methods (JetPakistan)

- **Manual Payment** → Laravel `pay_later` / `pay_later_booking_request`
- **Pay by Card** → Laravel `online_card` → AbhiPay after booking submit

## Booking creation timing

Unchanged from Blade: review POST creates supplier booking via existing `BookingController::processReviewSubmit`, then redirects to checkout state. Card payment starts only after booking exists.

## Module layout

`frontend/features/standard-booking/`

- `components/BookingReviewPage.tsx`
- `components/ManualPaymentPage.tsx`
- `components/CardPaymentPage.tsx`
- `components/InvoicePage.tsx`
- `services/booking-checkout-api.ts`
- `types/review-payment.ts`
- `utils/payment-url.ts` (hosted checkout allowlist)
