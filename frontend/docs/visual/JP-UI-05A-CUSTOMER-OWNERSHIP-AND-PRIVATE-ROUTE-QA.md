# JP-UI-05A — Customer Ownership and Private Route QA

## Test file

`frontend/tests/jp-ui-05a-customer-ownership.spec.ts`

## Command

```bash
cd frontend
npm run build
npx playwright test tests/jp-ui-05a-customer-ownership.spec.ts -c playwright.config.ts
```

## Results (JP-UI-05A)

| Test | Result |
|------|--------|
| Customer can access owned booking detail (`BKG-1001`) | Pass |
| Forbidden booking (`BKG-FORBIDDEN`) safe denial, no PNR flash | Pass |
| Navigation excludes Agent/Admin links | Pass |
| Private route `robots` noindex | Pass |

## Evidence

- Session fixture `ota_session_fixture=customer` for SSR portal guard
- Route mock `**/laravel/customer/bookings/BKG-1001?format=json` returns full booking confirmation payload
- Forbidden route returns HTTP 403; `customer-permission-denied` or safe message; zero PNR text
- `frontend/app/customer/layout.tsx` exports `metadata.robots = { index: false, follow: false }`

## Laravel

JP-UI-05B adds Laravel-authoritative evidence in `tests/Feature/Jetpk/CustomerBookingOwnershipTest.php` (3 methods) covering `customer.bookings.show`, `customer.bookings.index`, and `BookingPolicy::view`.

## Invoice eligibility

JP-UI-05B adds `tests/Feature/Jetpk/CustomerInvoiceOwnershipTest.php` (3 methods) covering `customer.invoices.show`, `customer.invoices.index`, and `BookingPolicy::view`. Visual matrix (`customer-booking-detail` scenario) and ownership payload `view_invoice` action remain unchanged.
