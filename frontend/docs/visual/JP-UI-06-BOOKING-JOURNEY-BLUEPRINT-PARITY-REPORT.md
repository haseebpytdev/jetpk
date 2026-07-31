# JP-UI-06 Booking Journey Blueprint Parity Report (Wave 2)

Phase: **JP-UI-06** | Wave: **2**

## Families

| Family | Route | Mode | Key components |
|--------|-------|------|----------------|
| flight-results | `/flights/results` | exact | `FlightResultsPage`, 260px sidebar grid |
| fare-selection | `/flights/fare-selection` | exact_with_operational_substitution (A) | `FareSelectionPage`, pre-session progress |
| passenger-details | `/booking/passengers` | exact | `PassengerDetailsPage` |
| seat-selection-capability-unavailable | `/booking/passengers` | capability_exception (B) | `SeatExtrasReadinessPanel` |
| review | `/booking/review` | exact | `BookingReviewPage` |
| payment | `/booking/payment` | exact_with_operational_substitution (C) | `PaymentPage`, `AbhiPayHandoffPanel` |
| booking-success | `/booking/confirmation` | exact | `BookingConfirmationPage` |

## Fare selection (new route)

- URL carries only `search_id`, `offer_id`, `fare_option_key`.
- State reconstructed via `GET /flights/results/offer` on every load/refresh.
- Continue runs `POST /flights/results/revalidate-offer` then navigates to passengers.
- Expired/invalid → safe state with return-to-results CTA.
- Entry: `use-offer-selection.ts`, `use-revalidation.ts` → `/flights/fare-selection`.

## Payment (canonical shell)

- `/booking/payment` renders `PaymentPage` (no redirect).
- `/booking/payment/manual` → `?method=manual`; `/booking/payment/card` → `?method=card`.
- Card region: `AbhiPayHandoffPanel` only — no PAN/expiry/CVV fields.
- Method cards are route/handoff choices; authoritative list remains on review (`GET /booking/review`). **JP-OPS gap:** no dedicated payment-method persistence endpoint during JP-UI-06.

## Seat capability

No `/booking/seats` route. Capture uses `seat_map_available: false` fixture override. Reviewed against capability contract, not seat-map pixels.

## Evidence

`C:\Users\khadi\ota-jetpk\frontend\.visual-audit\jp-ui-06\wave-2-contact-sheet.png`

## Wave 2 gate

Manual visual approval required before Wave 3 sign-off.
