# SABRE-REVALIDATION-BFM-FLIGHTSEGMENT-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-FLIGHTSEGMENT-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-flightsegment-schema-correction-1` (from `claude/ui-master`)

## Objective
Correct `bfm_revalidate_v1` so `/v4/shop/flights/revalidate` no longer emits invalid `OriginDestinationInformation[].FlightSegment`, add a local schema guard before HTTP, and surface safe structural/schema fields on the revalidation-only plan artifact.

## Included scope
- `bfm_revalidate_v1` ODI construction fix (`TPA_Extensions.Flight[]` with IATI-style leg grouping)
- Local `evaluateRevalidationPayloadSchema()` guard in `SabreRevalidationPayloadBuilder`
- Pre-HTTP block in `SabreBookingService::runRevalidationBeforeBooking()`
- Structural style comparator (read-only, no HTTP)
- Plan artifact schema fields on `SabreGdsLiveRevalidationOnlyProbe`
- Unit/feature tests for schema guard and corrected shape

## Excluded scope
- No live Sabre HTTP during implementation
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No automatic style fallback
- `shop_replay_selected_itinerary_v1` left unchanged (still uses legacy direct `FlightSegment`; not production default)
- No migration

## Investigation findings
Production run `e0f82761-5e9d-4c1f-86c7-a87bbd7ea134` received HTTP 400 with `JSON_ADAPTER` rejecting `OriginDestinationInformation[0].FlightSegment` on `/v4/shop/flights/revalidate`.

## Root causes
`SabreRevalidationPayloadBuilder::buildPayload()` default BFM path (lines ~153–191 pre-fix) built one ODI row per segment with a direct `FlightSegment` child. That shape is not accepted by the revalidate endpoint schema. Valid OTA styles (`iati_like_bfm_revalidate_v1`, corrected BFM) place flights under `TPA_Extensions.Flight[]` with ODI-level origin/destination/date grouping.

## Exact method that emitted invalid `FlightSegment`
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildPayload()` — default BFM branch loop assigning `$odis[] = ['FlightSegment' => $flightSegment]`.

## Corrected schema structure (key names only)
```
OTA_AirLowFareSearchRQ
  OriginDestinationInformation[]
    RPH
    DepartureDateTime
    OriginLocation
    DestinationLocation
    TPA_Extensions
      SegmentType.Code
      Flight[]            # per-segment nodes with ClassOfService, ResBookDesigCode, FareBasisCode, Airline, etc.
  (sibling blocks preserved: itinerary, shop_context, pricingInformation, fare_context, passenger_counts)
```

## Style decision
**`bfm_revalidate_v1` corrected in place** (not switched to another style). Exact-offer linkage blocks (`shop_context`, `fare_context`, `pricingInformation`, `itinerary`) preserved.

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest.php` (new)
- `tests/Feature/SabreBookingRevalidatePhaseB13Test.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Routes changed
None.

## Database changes
None.

## Backend changes
- BFM flight nodes via `buildBfmRevalidateFlightNodeFromSegment()` + `groupIatiLikeOriginDestinationInformation()`
- `evaluateRevalidationPayloadSchema()` + freeze fingerprint includes ODI child-key pattern
- `runRevalidationBeforeBooking()` fails closed with `revalidation_payload_invalid_flightsegment_location` before supplier HTTP

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter=SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest
php artisan test --filter="SabreBookingRevalidatePhaseB13Test::test_bfm_revalidate_v1|SabreBookingRevalidatePhaseB13Test::test_revalidation_payload_includes"
php artisan test --filter=SabreGdsLiveRevalidationOnlyProbeTest::test_plan_mode
```

## Assertion counts
- New phase unit tests: 6 tests / 30+ assertions
- Related regression tests: green

## Screenshots
N/A (backend/schema phase)

## Responsive verification
N/A

## Accessibility verification
N/A

## Known limitations
- `shop_replay_selected_itinerary_v1` still emits direct `FlightSegment` (diagnostic-only style)
- Schema guard is keyed to `bfm_revalidate_v1` + `/v4/shop/flights/revalidate` only

## Risks
- Connecting itineraries now group into fewer ODIs when gap ≤24h and route continuity holds (matches IATI grouping rules); verify on next authorized live send

## Rollback instructions
Revert the files listed above; redeploy previous `SabreRevalidationPayloadBuilder` ODI loop.

## Production verification (after deploy)
```bash
php -l app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php
php artisan config:clear && php artisan cache:clear

# Plan only — no live revalidation send
php artisan sabre:gds-live-revalidation-only-probe \
  --connection=1 \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --preset=qr-connecting \
  --candidate-index=0 \
  --plan \
  --passenger-json=/path/to/private-passenger.json \
  --confirm-production=APPROVE-LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE
```

Confirm artifact:
- `payload_schema_valid=true`
- `contains_invalid_direct_flight_segment=false`
- `supplier_revalidation_call_count=0`
- `db_mutation_detected=false`

Stop for authorization before `--send`.

## Commit SHA
(pending commit)

## Final status
Implementation complete; awaiting commit, deploy, plan verification, and authorized live send review.
