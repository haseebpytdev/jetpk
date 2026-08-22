# Wave-7 Phase Summary — Owner Retest V3

## Phase name
Owner Retest V3 Wave-7 — selected fare persistence, authoritative multipax Fare Details, passport OCR, checkout experience

## Branch name
`feat/jetpk-flight-results-booking-flow-20260819`

## Objective
Close Wave-7 engineering remediation for selected branded-fare persistence, Adult/Child/Infant Fare Details, local passport OCR reliability, Flight Summary / Change flight / Terms consent, then stop before production deploy for owner/ChatGPT review.

## Included scope
- Cluster A: selected fare option key persistence Continue → Travelers
- Cluster B: FX-normalized passenger_pricing + branded PTC rows + Fare Details table
- Cluster C: bounded local OCR, self-hosted Tesseract assets, title rules
- Cluster D: Flight Summary baggage/meal, Change flight pre-hold abandon, mandatory terms
- Cluster E: focused tests, typecheck, Playwright mock regression, gate flags, runtime manifest

## Excluded scope
- Production deploy
- Live supplier search / PNR / hold / ticket / payment / wallet mutations
- Marking `OWNER_RETEST_V3=PASS`

## Investigation findings / root causes
1. Revalidation handoff omitted `fare_option_key`; Travelers fell back to base ECONOMY BASIC / 0 kg.
2. Supplier PTC rows stayed in USD after FX; Fare Details hid them instead of converting with the priced FX rate.
3. Sabre branded option selection did not always refresh `fare_breakdown.passenger_pricing`.
4. Passport OCR could hang without timeout / worker termination / self-hosted assets.
5. Travelers lacked mandatory versioned terms acceptance and a safe pre-hold Change flight path.

## Exact files changed (Wave-7 commits)
See git history from `7050d2e6` → final SHA below. Key modules:
- `FlightController`, `FlightOfferDisplayPresenter`, `StandardBookingJsonPresenter`
- `PassengerPricingCustomerCurrencyNormalizer`, `FlightSearchService`, `SabreFlightSearchNormalizer`
- `BookingController`, `StoreBookingPassengersRequest`, `config/ota_checkout_consent.php`
- Frontend: `use-revalidation`, `PriceBreakdown`, `FareFamilyDetails`, `OrderSummary`, `PassengerDetailsPage`, document-reader OCR
- Tests: Wave7 feature/unit + `owner-v3-flight-wave-7-selected-fare.spec.ts`

## Routes changed
- `POST /booking/abandon-selected-offer` (`booking.abandon-selected-offer`)

## Database changes
None.

## Backend / frontend changes
As above.

## Tests executed
- `php artisan test --filter=Wave7` — pass
- `PassengerPricingCustomerCurrencyNormalizerTest` / `ResultsPassengerPricingTrustTest` / related checkout presenter — pass
- `npm run typecheck` — pass
- `npm run test:document-reader` — 13/13 pass
- `npx playwright test tests/owner-v3-flight-wave-7-selected-fare.spec.ts` — mock flow (selected fare, terms/Change flight, multipax Fare Details)

## Screenshots
Under `tmp/owner-v3-flight-wave-7/` (mock Playwright / local navigation captures). Synthetic passport fixtures only.

## Known limitations / risks
- Live production UAT still required after protected deploy; engineering matrix does not itself prove live Sabre/IATI payloads.
- Branded meal/seat amenity richness still depends on supplier-provided fields; Wave-7 does not fabricate missing benefits.
- Deployed runtime remains `9653d5ab…` until owner-authorized protected deploy.

## Rollback
Revert Wave-7 commits on the feature branch or redeploy prior runtime SHA `9653d5ab488ec6ba971ff76324894057ca8c3ffb`.

## Final status
`OWNER_RETEST_V3=FAILED_REMEDIATION_REQUIRED` (do not set PASS). Engineering Wave-7 source/test gate prepared for review; **STOP BEFORE PRODUCTION DEPLOYMENT**.
