# SABRE-BRANDED-FARES-BF1 — Fare Family / Branded Fares Discovery Audit

**Date:** 2026-06-14  
**Scope:** Discovery only — no runtime, payload, UI, migration, or flag changes.  
**BF1 confirmation:** No PHP runtime changes; no live Sabre calls; all Sabre safety flags unchanged.

---

## Executive summary

The OTA already has **substantial branded-fare scaffolding** for Sabre (B1/B2A): normalized `fare_family` + `branded_fares[]`, fare-family UI modal (display-only), checkout draft `selected_fare_family_option`, `applyBrandedFareOptionToOfferSnapshot()`, pricing-context digest readiness, and **inspect/compare-only** AirPrice `Brand` qualifier wiring on IATI-like CPNR v2.4.

**Gaps for production branded fares:**

1. Production shop uses a **minimal BFM v4 body** with **no branded-fare request qualifiers** (`BrandedFareIndicators`, enriched `TravelPreferences`, etc.).
2. Parser maps brands from **GIR `fareComponents` paths** (`brandCode`, `fareFamily*`) — not NDC-style `BrandID` / `BrandFeatures` nodes.
3. All `branded_fares[].selectable` remain **`false`** — UI modal shows options but blocks proceed except cheapest/default row behavior.
4. **Revalidation** and **default traditional CPNR AirPrice** do **not** preserve selected brand; only IATI-like v2.4 wire optionally applies `PricingQualifiers.Brand` (cert/compare path).
5. Host-rejection fingerprints and safe-refresh context **omit brand** — fare/RBD mismatch risk if brand tier changes fare basis without fingerprint update.

BF2 should enable gated search request enrichment, prove CERT response shapes per carrier, flip `selectable` only when linkage readiness passes, and thread brand through revalidation → booking meta → AirPrice behind separate flags.

---

## 1. Sabre search endpoint & payload builders

### Current endpoint / version

| Item | Value |
|------|--------|
| HTTP path | `config('suppliers.sabre.shop_path')` → default **`/v4/offers/shop`** |
| Request root | `OTA_AirLowFareSearchRQ` with **`Version: "4"`** |
| Transport | REST JSON POST via `SabreClient::searchFlights()` / `postShopPayload()` |
| Response root | `groupedItineraryResponse` (BFM v4 GIR) |
| Not used | SOAP envelope; production does not use `/v5/offers/shop` unless `SABRE_SHOP_PATH` overridden |

### Payload builder files / classes

| File | Class | Role |
|------|-------|------|
| `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchRequestBuilder.php` | `SabreFlightSearchRequestBuilder` | Production + inspect shop JSON |
| `app/Services/Suppliers/Sabre/Core/SabreClient.php` | `SabreClient` | Auth, POST shop, path resolution |
| `app/Services/Suppliers/Adapters/SabreFlightSupplierAdapter.php` | `SabreFlightSupplierAdapter` | Orchestrates search → normalize |
| `app/Services/Suppliers/Sabre/SabreFlightSearchRequestBuilder.php` | *(class_alias stub)* | Legacy import path |

### Production vs inspect shapes

**Production `build()` → `buildMinimalShopPayload()`:**

- `OriginDestinationInformation[]` (RPH, locations, `DepartureDateTime`)
- `TravelerInfoSummary.AirTravelerAvail[].PassengerTypeQuantity`
- `TPA_Extensions.IntelliSellTransaction.RequestType.Name = "50ITINS"`
- Optional `POS.Source[].PseudoCityCode` when PCC configured
- **No** root `TravelPreferences`, **no** `PriceRequestInformation`, **no** `Currency`, **no** branded-fare indicators

**Inspect-only `buildEnhancedInspectShopPayload()` (`sabre:inspect-shop-payload --variant=current`):**

- `TravelPreferences.TPA_Extensions.DataSources` (ATPCO Enable, LCC/NDC Disable)
- `TravelerInfoSummary.PriceRequestInformation.CurrencyCode` + `TPA_Extensions.PublicFare`
- Root `Currency`
- Still **no** `BrandedFareIndicators` / `MultipleBrandedFares` / similar

### Qualifier assembly locations

| Sabre concept | Where assembled today |
|---------------|----------------------|
| `TravelPreferences` | Inspect enhanced payload only |
| `TPA_Extensions` (IntelliSell 50ITINS) | Production + inspect |
| `PriceRequestInformation` | Inspect enhanced only; currency from `SABRE_SHOP_CURRENCY` (default USD) |
| `OptionalQualifiers` / `PricingQualifiers` | **Not in shop** — only in Passenger Records **AirPrice** (booking payload builder) |

---

## 2. Search response parser / normalizer

### Parser files / classes

| File | Class | Role |
|------|-------|------|
| `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php` | `SabreFlightSearchNormalizer` | GIR → `NormalizedFlightOfferData` |
| `app/Services/Suppliers/Sabre/Gds/SabreStoredPricingContextDigest.php` | `SabreStoredPricingContextDigest` | Pricing linkage readiness + branded option scoring |
| `app/Data/NormalizedFlightOfferData.php` | `NormalizedFlightOfferData` | DTO: `fare_family`, `branded_fares[]` |
| `app/Data/FareBreakdownData.php` | `FareBreakdownData` | Totals, `fare_basis_codes[]` |
| `app/Data/BaggageAllowanceData.php` | `BaggageAllowanceData` | Checked/cabin/summary |
| `app/Support/FlightSearch/FlightOfferDisplayPresenter.php` | `FlightOfferDisplayPresenter` | UI-facing `fare_family_options_display` |

### Parsing entry points

- `normalize()` → `collectItineraries()` → `normalizeOneItineraryWithDiagnostics()`
- Primary price: `firstFareNode()` → `pricingInformation[0].fare`
- Extra tiers: `buildBrandedFaresFromItinerary()` when **≥2** viable `pricingInformation` rows with brand name/code

### Fields extracted today

| Field | Source path (GIR) |
|-------|-------------------|
| `fare_basis` / codes | `passengerInfoList.*.passengerInfo.fareComponents.*` + segment booking metadata |
| `booking_class` (RBD) | Same fare component segment slices |
| Baggage | `baggageInformation` / `baggageAllowanceDescs` resolution |
| Validating carrier | Fare / itinerary validating carrier fields |
| Totals / taxes / currency | `fare.totalFare`, `passengerInfoList` aggregates |
| Segments | `scheduleDescs` + `legDescs` descriptor graph |
| `fare_family` (headline) | `extractFareFamily()` on primary fare node |
| `brand_code` | `extractPrimaryBrandCode()` → `fareComponents.*.brandCode` or `fareFamilyCode` |

### Brand / fare-family fields in current response handling

**Observed and parsed (safe path strings only):**

- `groupedItineraryResponse.itineraryGroups[].itineraries[].pricingInformation[].fare.passengerInfoList[].passengerInfo.fareComponents[].brandCode`
- `...fareFamilyCode`
- `...fareFamily` / `...fareFamilyName` / `...name`
- `...fareBasisCode` (used as fallback label when brand name absent)
- `pricingInformation[].fare` totals, refs: `pricingInformationRef`, offer refs (via `extractPricingInformationLinkageScalars()`)

**Not referenced in codebase (NDC/ATPCO XML-style names):**

- `BrandID`, `BrandName`, `ProgramName`, `BrandFeatures`, `AncillaryServices`
- `brandedFare`, `brandedFareIndicators` request/response nodes
- `PTC_FareBreakdown` (legacy OTA XML naming)

**Diagnostics (no raw payload):**

- `SabreFlightSearchNormalizer::brandedFaresProbeDiagnostics()` when `SABRE_BRANDED_FARES_PROBE_ENABLED=true`
- Log channel: `sabre.branded_fares_probe`, `sabre.branded_fares_mapped`

---

## 3. Offer cache / session structure

### Path

| Layer | Mechanism |
|-------|-----------|
| Search session | `FlightSearchResultStore` — Cache key `flight_search:{uuid}`, TTL **1800s**, max **150** offers |
| Select offer | Query params `search_id` + `offer_id` / `flight_id`; `findOffer()` |
| Checkout draft | `BookingDraft` session — `fare_option_key`, `selected_fare_family_option` |
| Revalidation patch | `patchOfferRevalidationMeta()`, `refreshOfferFromSearch()` |

### Files

- `app/Services/FlightSearch/FlightSearchResultStore.php`
- `app/Http/Controllers/Frontend/FlightController.php` (results, revalidate-offer POST)
- `app/Http/Requests/Frontend/StoreBookingPassengersRequest.php` (`findOffer` validation)

### Meta extensibility for brand

Cached offer arrays already support:

- `fare_family`, `brand_code`, `branded_fares[]`
- `raw_payload.sabre_shop_context`, `sabre_shop_identifiers`, `sabre_booking_context`
- `fare_option_key`, `sabre_booking_context` (top-level after `ensureSabreBookingContextOnCachedOffer()`)

`applyBrandedFareOptionToOfferSnapshot()` updates `pricing_information_index`, refs, fare totals, segment fare basis/RBD slices when user picks a tier.

### Fingerprints (brand not included today)

`SabreHostRejectionFingerprint::extractMatchFieldsFromSnapshot()` hashes:

- origin, destination, segment_count, marketing/operating carriers, validating_carrier
- `booking_classes_by_segment`, `fare_basis_codes_by_segment`, `segment_fingerprints`

**Does not include** `brand_code` / `fare_family` — BF2 should extend carefully to avoid false negatives/positives.

### Safe refresh context

`SabreSafeRefreshContext` (`meta.sabre_safe_refresh_context`) stores search criteria, segment summary, shop identifier scalars, totals — **no brand fields today**. BF2: add optional `fare_family.brand_id` / `brand_code` scalars when preserve flag on.

---

## 4. Checkout / booking persistence

### Conversion path

```
FlightController select → BookingController::passengers (GET/POST)
  → prepareSabreOfferForCheckoutHandoff()
  → applyBrandedFareOptionToOfferSnapshot() if draft selected
  → ensureSabreBookingContextOnCachedOffer()
  → bookings.meta: flight_offer_snapshot, validated_offer_snapshot,
     selected_fare_family_option, fare_option_key, sabre_booking_context,
     sabre_safe_refresh_context, checkout_search_id, checkout_offer_id
```

### Key files

- `app/Http/Controllers/Frontend/BookingController.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` (`resolveOfferSnapshotForBookingAttempt()`)
- `app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php` (`buildInternalDraft()`)

### Persisted today (no migration required)

| Key | Content |
|-----|---------|
| `meta.selected_fare_family_option` | Redacted option row (name, brand_code, price, readiness flags) |
| `meta.fare_option_key` | Stable UI key |
| `meta.sabre_booking_context` | Segment RBD/fare basis slices, brand_code, pricing linkage |
| `segments[].fare_basis_code` | Per segment in snapshots |
| `fare_breakdown.fare_basis_codes` | List on offer |

### Future safe persistence keys (BF2)

```json
"fare_family": {
  "brand_id": null,
  "brand_name": "Economy Flex",
  "brand_code": "EFLEX",
  "program_name": null,
  "amenities": [],
  "source": "sabre_gir_fare_component",
  "raw_field_paths_observed": [
    "pricingInformation[].fare.passengerInfoList[].passengerInfo.fareComponents[].brandCode"
  ]
}
```

No raw Sabre JSON or PII required — scalar codes + observed path strings only.

---

## 5. Revalidation / AirPrice / Passenger Records

### Revalidation

| Item | Detail |
|------|--------|
| Default path | `config('suppliers.sabre.revalidate_path')` → **`/v4/shop/flights/revalidate`** |
| Builder | `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php` |
| Orchestrator | `SabreBookingService::runRevalidationBeforeBooking()` |
| Default style | `bfm_revalidate_v1` (`SABRE_REVALIDATE_PAYLOAD_STYLE`) |
| Brand in revalidate payload | **Not present** — uses segments, fare basis, shop context from internal draft |

**BF2 hook:** `SabreRevalidationPayloadBuilder::buildPayload()` + internal draft enrichment after branded selection.

### AirPrice / CPNR

| Item | Detail |
|------|--------|
| Builder | `app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php` |
| Draft entry | `buildInternalDraft()` → `buildTraditionalPnrCreatePassengerNameRecordV1Wire()` |
| AirPrice shape | Root `CreatePassengerNameRecordRQ.AirPrice[]` with `PriceRequestInformation.Retain` |
| Brand qualifier | `traditionalPnrApplyRootAirPriceBrandQualifier()` → `OptionalQualifiers.PricingQualifiers.Brand[].Code` |
| Brand resolution | `traditionalPnrResolveBrandCodeFromDraft()` reads `_sabre_booking_context.brand_code`, `selected_fare_family_option.brand_code` |
| **Live default traditional v2.5 wire** | **Does not apply Brand** |
| **IATI-like v2.4 GDS wire** | Applies Brand when code resolvable (cert/compare lane) |

**BF2 hook:** Gate brand injection behind `SABRE_BRANDED_FARES_AIRPRICE_ENABLED`; keep traditional default unchanged until CERT proves host acceptance.

---

## 6. UI surfaces (future labels)

| Surface | File(s) |
|---------|---------|
| Desktop results + fare modal | `resources/views/frontend/flights/results.blade.php` |
| Mobile results | `resources/views/mobile/flights/results.blade.php`, `partials/filter-drawer.blade.php`, `partials/details-flight-card.blade.php` |
| Flight details | `resources/views/frontend/flights/details.blade.php` |
| Checkout passengers | `resources/views/frontend/booking/passenger-details.blade.php`, `mobile/bookings/partials/selected-flight-card.blade.php` |
| Checkout review | `resources/views/frontend/booking/review.blade.php`, `mobile/bookings/review.blade.php` |
| Confirmation | `resources/views/frontend/booking/confirmation.blade.php` |
| Presenter | `FlightOfferDisplayPresenter::buildFareSummaryDisplay()`, `buildFareFamilyOptionsDisplay()` |

**Current UX:** Modal renders `fare_family_options_display`; cards show `selectable=false` notice; proceed disabled for non-selectable tiers (Sabre). Duffel path may show selectable options via separate normalizer.

**Admin:** No dedicated brand panel; fare family visible only via booking snapshots / continuity diagnostics indirectly.

---

## 7. Config — existing vs proposed BF2 flags

### Existing (unchanged in BF1)

| Env / config key | Default | Purpose |
|------------------|---------|---------|
| `SABRE_BRANDED_FARES_PROBE_ENABLED` | `false` | Metadata-only shop probe logging |
| `SABRE_SHOP_PATH` | `/v4/offers/shop` | Shop endpoint |
| `SABRE_REVALIDATE_PATH` | `/v4/shop/flights/revalidate` | Revalidate endpoint |

### Proposed BF2 flags (do not add in BF1)

| Proposed flag | Default | Purpose |
|---------------|---------|---------|
| `SABRE_BRANDED_FARES_SEARCH_ENABLED` | `false` | Enrich shop request for multi-PI / branded indicators |
| `SABRE_BRANDED_FARES_UI_ENABLED` | `false` | Allow selectable Sabre fare-family modal proceed |
| `SABRE_BRANDED_FARES_CHECKOUT_PRESERVE_ENABLED` | `false` | Thread brand through snapshots, revalidation, refresh |
| `SABRE_BRANDED_FARES_AIRPRICE_ENABLED` | `false` | Apply AirPrice `Brand` on traditional CPNR live path |

---

## 8. Risk analysis & blockers

| Risk | Severity | Notes |
|------|----------|-------|
| **Version mismatch** | High | Shop v4 + revalidate `/v4/shop/flights/revalidate` vs optional `/v4/offers/shop/revalidate`; brand nodes may differ by path/version. |
| **PCC activation** | High | Minimal shop may return single PI without brand tiers until PCC/ATPCO branded fares enabled. |
| **Carrier support** | High | GF/EK/QR/etc. vary; cert matrix per carrier required before UI selectable. |
| **Response-shape variability** | Medium | GIR uses `brandCode` not `BrandName`; labels may be codes or fare basis — UI must not assume marketing names. |
| **Fare mismatch** | High | Selecting brand changes fare basis/RBD; E5 host rejection fingerprints ignore brand today. |
| **Brand vs baggage confusion** | Medium | Baggage from separate `baggageInformation`; brand tier ≠ baggage guarantee. |
| **Brand as sellability** | High | `selectable` must stay false until `assessBrandedFareOptionReadiness()` + live CERT PNR evidence. |
| **Early AirPrice brand** | High | Booking 46 pattern (`*NO FARES/RBD/CARRIER`) — brand qualifier before sellability proof is dangerous. |
| **IATI vs traditional split** | Medium | Brand wired only on IATI-like wire today; production certified path is traditional v2.5. |

### Blockers before production branded fares

1. CERT shop probe per target carrier with `SABRE_BRANDED_FARES_PROBE_ENABLED` + optional enriched search flag.
2. Prove `pricingInformation` multi-row mapping rates (`would_map_branded_fares`) on live PCC.
3. Revalidation + CPNR CERT with selected `pricing_information_index` / brand code.
4. E5 fingerprint / safe-refresh extension design review.
5. Keep `SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED=false`, `SABRE_TICKETING_ENABLED=false`.

---

## 9. BF2 implementation plan (recommended phases)

### BF2-A — Search request enrichment (flag-gated)

- Add optional `BrandedFareIndicators` / enriched `TravelPreferences` to `SabreFlightSearchRequestBuilder` when `SABRE_BRANDED_FARES_SEARCH_ENABLED`.
- CERT-only: `sabre:inspect-shop-payload` + `sabre:cert-gds-linkage-report` with probe enabled.
- Extend `brandedFaresProbeDiagnostics` skip_reason reporting.

### BF2-B — Parser hardening

- Map `program_name` if present in fare component descriptors.
- Optional amenities from fare component desc lists (display-only).
- Populate proposed `fare_family.*` meta keys on normalized offers.

### BF2-C — Selectable UI (flag-gated)

- Set `selectable=true` only when `ready_for_revalidation && ready_for_booking_payload` and `SABRE_BRANDED_FARES_UI_ENABLED`.
- Wire `fare_option_key` through select URL (already partially present).

### BF2-D — Checkout preserve (flag-gated)

- Extend `applyBrandedFareOptionToOfferSnapshot()`, `SabreSafeRefreshContext`, host fingerprint with optional brand_code.
- `SABRE_BRANDED_FARES_CHECKOUT_PRESERVE_ENABLED`.

### BF2-E — Revalidation + AirPrice (flag-gated)

- Thread brand + PI index into `SabreRevalidationPayloadBuilder`.
- Traditional CPNR AirPrice Brand behind `SABRE_BRANDED_FARES_AIRPRICE_ENABLED` after CERT.
- Admin continuity panel: brand scalar row.

---

## 10. Files likely touched in BF2

| Area | Files |
|------|-------|
| Shop request | `Gds/SabreFlightSearchRequestBuilder.php`, `config/suppliers.php`, `.env.example` |
| Parser | `Gds/SabreFlightSearchNormalizer.php`, `Gds/SabreStoredPricingContextDigest.php`, `NormalizedFlightOfferData.php` |
| Cache/checkout | `FlightSearchResultStore.php`, `BookingController.php`, `SabreSafeRefreshContext.php` |
| Revalidation | `Gds/SabreRevalidationPayloadBuilder.php`, `Booking/SabreBookingService.php` |
| CPNR | `Booking/SabreBookingPayloadBuilder.php` |
| UI | `FlightOfferDisplayPresenter.php`, `results.blade.php`, mobile partials, checkout blades |
| Tests | New `SabreBrandedFares*Test.php`, extend `SabreFlightSearchNormalizer*`, `SabreBookingWire*`, checkout tests |
| Docs | `summary.md` changelog |

---

## 11. Tests needed in BF2

1. **Unit:** `buildBrandedFaresFromItinerary()` fixture with 3 PI rows / distinct brand codes.
2. **Unit:** `applyBrandedFareOptionToOfferSnapshot()` updates fare basis, RBD, `pricing_information_index`.
3. **Unit:** `assessBrandedFareOptionReadiness()` ready vs index-only linkage.
4. **Unit:** `traditionalPnrResolveBrandCodeFromDraft()` resolution order.
5. **Feature:** Checkout with `fare_option_key` persists `selected_fare_family_option` (dry-run).
6. **CERT command:** Extend `sabre:cert-gds-linkage-report` / probe for multi-PI itineraries (no PNR).
7. **Regression:** E5 fingerprints still block without brand; no change to Booking 43/46 retry behavior.

---

## 12. Exact file map (BF1 reference)

```
app/Services/Suppliers/Sabre/Gds/SabreFlightSearchRequestBuilder.php   # Shop payload
app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php       # GIR parser + branded_fares
app/Services/Suppliers/Sabre/Gds/SabreStoredPricingContextDigest.php   # Linkage + branded readiness
app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php     # Revalidate JSON
app/Services/Suppliers/Sabre/Core/SabreClient.php                      # HTTP shop/revalidate
app/Services/Suppliers/Adapters/SabreFlightSupplierAdapter.php         # Search adapter
app/Services/Suppliers/Sabre/Booking/SabreBookingService.php           # Revalidate + booking orchestration
app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php    # CPNR / AirPrice wire
app/Services/FlightSearch/FlightSearchResultStore.php                  # Offer cache
app/Http/Controllers/Frontend/FlightController.php                     # Results + revalidate offer
app/Http/Controllers/Frontend/BookingController.php                    # Checkout meta
app/Data/NormalizedFlightOfferData.php                                 # Offer DTO
app/Support/FlightSearch/FlightOfferDisplayPresenter.php             # Fare family UI DTO
app/Support/Bookings/SabreSafeRefreshContext.php                       # Durable refresh context
app/Support/Bookings/SabreHostRejectionFingerprint.php               # Offer fingerprint
config/suppliers.php                                                   # Sabre config
resources/views/frontend/flights/results.blade.php                     # Fare family modal
resources/views/frontend/booking/*.blade.php                           # Checkout labels
```

---

## 13. Recommended BF2 prompt (copy-ready)

```
Proceed with SABRE-BRANDED-FARES-BF2 — gated Sabre branded fare search + selectable UI + checkout preserve (no live PNR by default).

Prerequisites: Read docs/sabre-branded-fares-bf1-audit.md. E5 chain remains stable; do not enable SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED, SABRE_TICKETING_ENABLED, or retry Booking 43/46.

BF2-A: Add config flags (default false): SABRE_BRANDED_FARES_SEARCH_ENABLED, SABRE_BRANDED_FARES_UI_ENABLED, SABRE_BRANDED_FARES_CHECKOUT_PRESERVE_ENABLED, SABRE_BRANDED_FARES_AIRPRICE_ENABLED.

BF2-A: When SEARCH_ENABLED, extend SabreFlightSearchRequestBuilder minimal shop with CERT-documented branded fare request qualifiers only; keep production default path when flag false.

BF2-B: Harden SabreFlightSearchNormalizer branded_fares mapping; populate fare_family.{brand_id,brand_name,program_name,amenities,source,raw_field_paths_observed} on offers; keep selectable false unless UI_ENABLED and assessBrandedFareOptionReadiness passes.

BF2-C: When UI_ENABLED, allow fare-family modal proceed for ready Sabre options; thread fare_option_key through existing BookingController handoff.

BF2-D: When CHECKOUT_PRESERVE_ENABLED, extend applyBrandedFareOptionToOfferSnapshot, SabreSafeRefreshContext, and optional fingerprint brand_code; no raw payload storage.

BF2-E: When AIRPRICE_ENABLED (CERT only first), apply traditionalPnrApplyRootAirPriceBrandQualifier on certified traditional v2.5 path after revalidation success — not before CERT matrix.

Tests: SabreBrandedFares* unit/feature; extend branded_fares_probe tests. No migrations. Update summary.md.

Verification: php artisan test --filter=SabreBranded; php -l on touched files; no live Sabre unless user approves CERT probe.
```

---

## BF1 runtime change confirmation

- **No PHP logic changes**
- **No Blade/CSS/JS changes**
- **No config/.env changes**
- **No migrations**
- **No live Sabre API calls**
- **No Passenger Records payload mutation**
- **No checkout behavior changes**
- This document + `summary.md` changelog only
