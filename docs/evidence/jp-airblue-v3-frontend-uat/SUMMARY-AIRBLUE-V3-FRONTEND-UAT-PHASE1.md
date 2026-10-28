# AirBlue v3 Frontend UAT — Phase-1R (Search / Presentation / Prebook)

**Date:** 2026-10-28  
**Branch:** `feat/airblue-v3-frontend-fare-presentation`  
**Authoritative source main:** `b0a342b4f9783068f4b38daff6414f00c407d66e`  
**Public checkpoint:** `78dadc7b`  
**Release lock stamp (known):** `feef2e7f`  
**GLOBAL_SOURCE_RUNTIME_DRIFT:** YES (known; not an AirBlue UAT blocker by itself)

## Classification

- AirBlue v3 TEST technical backend lifecycle: **CERTIFIED_CLOSED** (not reopened)
- This phase: **AIRBLUE_FRONTEND_UAT** acceptance layer only
- No AirBook / ticket / seat / refund / cancel re-certification
- No global release-lock refresh
- No automatic merge/deploy

## Phase A — Source precheck

| Gate | Result |
|------|--------|
| SOURCE_MAIN_SHA | `b0a342b4f9783068f4b38daff6414f00c407d66e` |
| SOURCE_MAIN_MATCH | YES |
| MAIN_PROTECTED | YES |

## Phase B — Production component parity (pre-search)

Readonly production SHA256 vs main (`b0a342b4`) — evidence `_raw-component-parity.txt`.

| Component | Parity |
|-----------|--------|
| XML_PARSER_PARITY | PASS |
| OTA_NORMALIZER_PARITY | PASS (pre-change; production matched main) |
| SEARCH_SERVICE_PARITY | PASS |
| OFFER_PRICE_SERVICE_PARITY | PASS |
| NORMALIZED_OFFER_PARITY | PASS |
| FLIGHT_CONTROLLER_PARITY | PASS |
| RESULT_STORE_PARITY | PASS |
| PUBLIC_SECURITY_PARITY | PASS |
| CLASSIFIER_PARITY | PASS (`b04a56a2…`) |
| TICKETING_SERVICE_PARITY | PASS (`600169ac…`) |

**AIRBLUE_UAT_COMPONENT_PARITY=PASS** (at search time, before this PR)

Post-PR note: normalizer/presenter/consolidator/config intentionally diverge from current production until scoped deploy of this PR.

## Phase C — Frontend provenance

AirBlue-UAT-relevant frontend sources byte-equivalent between `78dadc7b` and `b0a342b4`:

- `FlightResultsPage.tsx`
- `BrandedFareCarousel.tsx`
- `flight-results-api.ts`
- `flight-results/types/index.ts`
- `PassengerDetailsPage.tsx`

**AIRBLUE_RELEVANT_FRONTEND_SOURCE_EQUIVALENT=YES**

Do **not** claim `PRODUCTION_RUNTIME_SHA=main`.

## Phase D — Public isolation

Evidence: `_raw-isolation.txt`

| Gate | Result |
|------|--------|
| AIRBLUE_IN_PUBLIC_ALLOWLIST | NO |
| V3_TEST_PUBLIC_SEARCH_ELIGIBLE | NO |
| NORMAL_CUSTOMER_AIRBLUE_TEST_EXPOSURE | NO |

No changes to `OTA_PUBLIC_FLIGHT_RESULTS_SUPPLIERS`, Conn22, or certification flags.

## Phase E — Presentation audit (pre-fix, pre-fix)

Observed mapping before change:

| Field | Mapping |
|-------|---------|
| CURRENT_AIRBLUE_FARETYPE_MAPPING | segment `fare_type` only; top-level unused |
| CURRENT_AIRBLUE_RBD_MAPPING | segment `rbd` only |
| CURRENT_AIRBLUE_CABIN_MAPPING | segment `cabin_class`; top-level hardcoded `ECONOMY` |
| CURRENT_AIRBLUE_FARE_FAMILY_MAPPING | always `null` |
| CURRENT_AIRBLUE_BRANDED_FARES_MAPPING | always `[]` |

Baggage null; refundable hardcoded false (not asserted as supplier truth).

## Phase F–H — Real v3 TEST search + variant decision

Evidence: `_raw-search-variant-analysis.txt`

| Item | Value |
|------|-------|
| AIRLOWFARESEARCH_PROVIDER_CALLS | 1 |
| Route / date | ISB–KHI / 2026-12-29 |
| REAL_AIRBLUE_OFFER_COUNT | 6 |
| DISTINCT_ITINERARY_COUNT | 2 (PA201 10:45, PA207 19:30) |
| SAME_ITINERARY_VARIANT_COUNT | 3 (EF / EX / EV) |
| SAME_ITINERARY_VARIANTS_IDENTIFIED | YES |
| DISTINCT_FLIGHTS_NOT_MERGED | PASS |
| AIRBLUE_BRANDED_FARE_MODEL_REQUIRED | YES |
| RUNTIME_CHANGE_REQUIRED | YES |

Sanitized sample (PA201): supplier totals 25000 / 26000 / 27100 PKR; RBD=U; CabinClass=Y; FareTypes EV/EF/EX; no baggage; no invented marketing labels.

Mutating provider calls: **0**. No AirBook / Read / SeatMap / Type5 / AirDemandTicket / Type1 / Cancel.

## Phase I–K — Implementation (STOP at PR)

### Changes

1. **`AirBlueOtaResponseNormalizer`**
   - `fare_family` = exact supplier `FareType`
   - cabin from segment `CabinClass` (`Y`→`economy`)
   - `provider_context.fare_type_code` / `rbd` / `fare_basis`
   - `customer_display_fields.booking_class` / `fare_type` / `fare_basis`
   - canonical segment keys (`origin`/`destination`/`airline_code`) for consolidator
   - `branded_fares` remains `[]` (consolidator builds selectable options)
2. **`config/suppliers.php`** — `airblue.branded_fares_display_enabled` / `selection_enabled` (default true)
3. **`FlightOfferDisplayPresenter`** — AirBlue display/selection gates (do not fall through to Sabre defaults)
4. **`ItineraryFareConsolidator`** — omit invented `refundable_display` for AirBlue; RBD fallback from segments
5. **Fixture** `ota_air_low_fare_search_v3_branded_variants.xml`
6. **Tests** — normalizer variants, consolidator AirBlue grouping, presenter AirBlue gates
7. **`BrandedFaresResultsUiTest`** — retargeted from stale Blade stubs to Next.js frontend contracts

### Intentionally untouched

Mutation/ticket/cancel/refund/seat services, Conn22, Booking F, public allow-list, credentials, release lock.

### Tests executed

| Suite | Result |
|-------|--------|
| AIRBLUE_FARE_PRESENTATION_TESTS (normalizer + consolidator AirBlue) | PASS |
| BRANDED_FARES_RESULTS_UI_TESTS | PASS (Next contracts) |
| BRANDED_FARE_CHECKOUT_CONTEXT_TESTS | PASS |
| SUPPLIER_ROUTING_TESTS | PASS |
| PUBLIC_SECURITY_TESTS | PASS |
| Parser + SearchRequestContract + Normalizer | PASS |
| Combined gate battery | **47 passed / 238 assertions** (`_raw-final-gate-phpunit.txt`) |

### Stop condition (Phase K)

| Gate | Value |
|------|-------|
| FRONTEND_UAT_IMPLEMENTATION_PR_READY | YES |
| Browser UAT (L–T) | **NOT_RUN** (blocked until review → merge → scoped deploy) |
| Merge | NO |
| Deploy | NO |

## Final decision (code-change path)

```
AIRBLUE_FRONTEND_SEARCH_PRESENTATION_CERTIFIED=NO
AIRBLUE_FRONTEND_CHECKOUT_PREBOOK_CERTIFIED=NO
FRONTEND_UAT_IMPLEMENTATION_PR_READY=YES
FINAL_STATUS=AIRBLUE_V3_FRONTEND_FARE_PRESENTATION_PR_READY
NEXT_PATH=REVIEW_MERGE_SCOPED_DEPLOY_THEN_RESUME_FRONTEND_UAT
```

## Call counts

```
AIRLOWFARESEARCH_PROVIDER_CALLS=1
REVALIDATION_AIRBLUE_PROVIDER_CALLS=0
AIRBOOK_PROVIDER_CALLS=0
READ_PROVIDER_CALLS=0
SEATMAP_PROVIDER_CALLS=0
TYPE5_PROVIDER_CALLS=0
AIRDEMANDTICKET_PROVIDER_CALLS=0
TYPE1_PROVIDER_CALLS=0
CANCEL_PROVIDER_CALLS=0
TOTAL_MUTATING_PROVIDER_CALLS=0
```

## Protection

```
BOOKING_F_CHANGED=NO
CONN22_CHANGED=NO
V3_CONNECTION_PERSISTED_CONFIG_CHANGED=NO
```

## Evidence path

`docs/evidence/jp-airblue-v3-frontend-uat/`
