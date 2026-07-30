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

No Laravel changes. Laravel remains authoritative for ownership; frontend tests validate presentation and client-side guard behavior with fixtures.

## Invoice eligibility

Covered in visual matrix (`customer-booking-detail` scenario) and ownership payload includes `view_invoice` action only when `available: true`. Dedicated invoice route tests remain in existing customer portal specs.
