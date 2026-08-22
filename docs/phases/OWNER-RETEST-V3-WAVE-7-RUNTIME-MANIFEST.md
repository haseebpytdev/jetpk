# Wave-7 Runtime Manifest (PRE-DEPLOY — DO NOT APPLY YET)

## Status
STOP BEFORE PRODUCTION DEPLOYMENT. Independent ChatGPT/owner review required.

`OWNER_RETEST_V3=FAILED_REMEDIATION_REQUIRED` (do not mark PASS)

## SHA pins (authoritative)

| Role | SHA |
|------|-----|
| Deployed production runtime (unchanged) | `9653d5ab488ec6ba971ff76324894057ca8c3ffb` |
| Public build (unchanged until deploy) | `JK8nDb8vrOeyjOA4Ue1Jg` |
| FINAL_WAVE7_ENGINEERING_SHA | `a9ec8f18745c8b9db3ce62504efd485a1bb8df3e` |
| FINAL_WAVE7_DOCS_SHA | *(docs commit after this file; see HEAD after docs push)* |

Do **not** treat earlier docs-only tip `f7796f89…` as runtime engineering.

## Branch / remote
- Branch: `feat/jetpk-flight-results-booking-flow-20260819`
- Remote: `jetpk`

## Exact runtime/build delta
Base: `9653d5ab488ec6ba971ff76324894057ca8c3ffb`  
Tip: `a9ec8f18745c8b9db3ce62504efd485a1bb8df3e`

Generated with:

`git diff --name-status 9653d5ab…a9ec8f18 -- . ':(exclude)tests' ':(exclude)docs' ':(exclude)tmp' ':(exclude)frontend/tests' ':(exclude)*.md'`

### Modifications (M)
- `app/Http/Controllers/Frontend/BookingController.php`
- `app/Http/Controllers/Frontend/FlightController.php`
- `app/Http/Requests/Frontend/StoreBookingPassengersRequest.php`
- `app/Services/FlightSearch/FlightSearchService.php`
- `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php`
- `app/Support/Booking/StandardBookingJsonPresenter.php`
- `app/Support/Bookings/CheckoutFareBreakdownPresenter.php`
- `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`
- `app/Support/FlightSearch/FlightOfferFallbackDetailsPresenter.php`
- `frontend/features/booking-layout/components/OrderSummary.tsx`
- `frontend/features/flight-details/components/FareFamilyDetails.tsx`
- `frontend/features/flight-details/components/PriceBreakdown.tsx`
- `frontend/features/flight-details/hooks/use-revalidation.ts`
- `frontend/features/flight-results/types/index.ts`
- `frontend/features/standard-booking/components/PassengerDetailsPage.tsx`
- `frontend/features/standard-booking/document-reader/components/DocumentReader.tsx`
- `frontend/features/standard-booking/document-reader/index.ts`
- `frontend/features/standard-booking/document-reader/ocr/scanDocumentClientSide.ts`
- `frontend/features/standard-booking/types/index.ts`
- `frontend/features/standard-booking/utils/passenger-form.ts`
- `frontend/package.json`
- `routes/web.php`

### Additions (A)
- `app/Support/Pricing/PassengerPricingCustomerCurrencyNormalizer.php`
- `config/ota_checkout_consent.php`
- `frontend/features/standard-booking/document-reader/titleFromPassport.ts`
- `frontend/public/tesseract/eng.traineddata.gz`
- `frontend/public/tesseract/tesseract-core-simd-lstm.wasm`
- `frontend/public/tesseract/tesseract-core-simd-lstm.wasm.js`
- `frontend/public/tesseract/worker.min.js`
- `frontend/scripts/bundle-tesseract-assets.mjs`

### Unexpected runtime subsystems
NONE — scope matches checkout / flight results / OCR areas only.

## Config / routes / database
- Config addition: `config/ota_checkout_consent.php` (`terms_version`, `privacy_version`)
- Route addition: `POST /booking/abandon-selected-offer` (`booking.abandon-selected-offer`)
- Database changes: **NONE**
- Migrations: **NONE**

## Self-hosted Tesseract assets (committed)
Verified locally; postinstall verifies presence/size and **never downloads**.

| Asset | Bytes | SHA256 |
|-------|------:|--------|
| `frontend/public/tesseract/eng.traineddata.gz` | 10,923,060 | `ED350F3752F81EE8F38769EDC14D92D997DABABE23B565C59879372CC46A2468` |
| `frontend/public/tesseract/worker.min.js` | 123,724 | `ACA1229639FC9907D86F96E825955A2B7C5716D17F3BC3ACD71F9C7AB66181FC` |
| `frontend/public/tesseract/tesseract-core-simd-lstm.wasm` | 2,859,709 | `66B601224A0C4A8977BC9D92DD39841189F9CA22CC4122FCD7208CDB0961EEEF` |
| `frontend/public/tesseract/tesseract-core-simd-lstm.wasm.js` | 3,938,657 | `CE20EDA9533CBED1E6C2B4276FBAE1E0ADC61B6754B5513084BE601787B457CF` |

Runtime OCR must load only same-origin `/tesseract/*` (production: `jetpakistan.pk`). No CDN / jsDelivr / raw.githubusercontent.

## Build-impact files
- `frontend/package.json` (postinstall → `bundle-tesseract-assets.mjs`)
- `frontend/scripts/bundle-tesseract-assets.mjs` (fail-closed local verify)
- `frontend/public/tesseract/*` (worker / wasm / eng data)

## Consent / OCR pre-deploy closures in engineering tip
- Server-authoritative `terms_version` / `privacy_version` persistence (`StoreBookingPassengersRequest` + `BookingController::checkoutTermsAcceptanceRecord`)
- Bounded OCR `terminateWorkerSafely` (`OCR_TERMINATE_TIMEOUT_MS=2000`) so hung terminate cannot trap UI Processing

## Explicit exclusions (not runtime deploy payload)
- `tests/**`, `frontend/tests/**`, `docs/**`, `tmp/**`, screenshots, `.next/**`, private tooling
- Untracked extra tesseract variants under `frontend/public/tesseract/` (lstm/simd non-committed extras) — **do not deploy**

## Rollback base
Redeploy / revert to `9653d5ab488ec6ba971ff76324894057ca8c3ffb` (build `JK8nDb8vrOeyjOA4Ue1Jg`).

## Deploy actor
Only established protected JetPakistan deployment scripts after explicit owner authorization.

## Forbidden during this gate
- Ad-hoc SSH/SFTP/SCP outside protected scripts
- Live supplier commercial mutations (PNR/hold/ticket/payment/wallet)
- Marking `OWNER_RETEST_V3=PASS`
