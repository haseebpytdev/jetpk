# OWNER-RETEST-V3 WAVE-8 RUNTIME MANIFEST

## Pins
- Production runtime (current): `a9ec8f18745c8b9db3ce62504efd485a1bb8df3e`
- Production public build: `i4kZsZzH4c9IcSNyRyhRi`
- Wave-8 engineering tip (pre-docs or with docs): see `FINAL_WAVE8_ENGINEERING_SHA` below after Cluster E commit.

## Delta
`a9ec8f18745c8b9db3ce62504efd485a1bb8df3e` â†’ `FINAL_WAVE8_ENGINEERING_SHA`

### Runtime / product files (deploy candidates after owner authorization)

```
app/Http/Controllers/Frontend/BookingController.php
app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php
app/Support/FlightSearch/FlightOfferDisplayPresenter.php
app/Support/FlightSearch/FlightOfferFallbackDetailsPresenter.php
frontend/features/booking-layout/components/OrderSummary.tsx
frontend/features/flight-details/components/FareFamilyDetails.tsx
frontend/features/flight-details/components/PriceBreakdown.tsx
frontend/features/flight-results/components/FlightResultsPage.tsx
frontend/features/flight-results/components/NearbyDateStrip.tsx
frontend/features/flight-results/components/ResultsHeroBand.tsx
frontend/features/flight-results/components/ResultsToolbar.tsx
frontend/features/flight-results/components/SearchSummaryBar.tsx
frontend/features/flight-results/components/SortControl.tsx
frontend/features/standard-booking/components/PassengerCard.tsx
frontend/features/standard-booking/components/PassengerDetailsPage.tsx
frontend/features/standard-booking/document-reader/components/DocumentReader.tsx
frontend/features/standard-booking/document-reader/ocr/scanDocumentClientSide.ts
```

### Tests / docs (engineering evidence; not production runtime)

```
tests/Feature/Wave8ChangeFlightFreshSearchTest.php
tests/Feature/FlightSearch/Wave8SelectedBrandPassengerPricingTest.php
tests/Feature/Wave7CheckoutConsentAndChangeFlightTest.php
frontend/tests/owner-v3-flight-wave-7-selected-fare.spec.ts
frontend/tests/owner-v3-flight-wave-7-visual-matrix.spec.ts
frontend/tests/owner-v3-flight-wave-8-visual-matrix.spec.ts
frontend/tests/regression/document-reader-ocr-safety.test.ts
docs/phases/OWNER-RETEST-V3-WAVE-8-SUMMARY.md
docs/phases/OWNER-RETEST-V3-WAVE-8-GATE.md
docs/phases/OWNER-RETEST-V3-WAVE-8-RUNTIME-MANIFEST.md
tmp/owner-v3-flight-wave-8/
```

## Visual proof
`tmp/owner-v3-flight-wave-8/` states 01â€“20 present (Playwright Wave-8 matrix).

## Deploy
**STOP BEFORE PRODUCTION DEPLOYMENT.**
Do not activate this SHA on production in this engineering loop.

