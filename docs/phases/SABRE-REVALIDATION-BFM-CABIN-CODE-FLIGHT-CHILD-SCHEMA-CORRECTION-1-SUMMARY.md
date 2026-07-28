# SABRE-REVALIDATION-BFM-CABIN-CODE-FLIGHT-CHILD-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-CABIN-CODE-FLIGHT-CHILD-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-cabin-code-flight-child-schema-correction-1` (from `claude/ui-master`)

## Objective
Remove unsupported `CabinCode` from `bfm_revalidate_v1` `TPA_Extensions.Flight[]` nodes per live Sabre JSON_ADAPTER rejection, extend the pre-HTTP schema guard, and preserve cabin continuity through accepted TravelPreferences, itinerary, and shop-context structures.

## Included scope
- Remove `CabinCode` emission from `buildBfmRevalidateFlightNodeFromSegment()`
- Move `CabinCode` to unsupported Flight child-key list
- Pre-HTTP rejection of direct `CabinCode` under Flight (`revalidation_payload_unsupported_cabin_code`)
- `evaluateBfmRevalidateBookingClassFareContextDigest()` extended with `cabin_context_present` and `cabin_context_location`
- Plan/send artifact schema field persistence
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `7127a3b6-2cab-4fb8-bc9e-9e25acd1416e`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No removal of `ClassOfService` or other Flight siblings without live rejection evidence
- No automatic style fallback
- No migration

## Investigation findings
Production run `7127a3b6-2cab-4fb8-bc9e-9e25acd1416e` rejected:
`JSON_ADAPTER: ...TPA_Extensions.Flight[0]: property 'CabinCode' is not defined in the schema and the schema does not allow additional properties.`

## Root causes
`buildBfmRevalidateFlightNodeFromSegment()` set `$node['CabinCode'] = $cabin` when `segment_cabin_code` was present on the internal segment. BFM revalidate schema does not allow `CabinCode` as a direct Flight child.

## Exact method that emitted CabinCode
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildBfmRevalidateFlightNodeFromSegment()` — `$node['CabinCode'] = $cabin` (removed).

## Old vs corrected Flight key sets (names only)

**Old:** included `CabinCode`  
**Corrected:** `Airline`, `ArrivalDateTime`, `ClassOfService`, `DepartureDateTime`, `DestinationLocation`, `Number`, `OriginLocation`, `Type`

**Unsupported (blocked pre-HTTP):** `CabinCode`, `FareBasisCode`, `ResBookDesigCode`, `SegmentNumber`

## Cabin context after removal
Preserved through safe linkage locations (no raw values):
- `ota_travel_preferences_cabin_pref` (`OTA_AirLowFareSearchRQ.TravelPreferences.CabinPref` via `collectCabinPrefs()`)
- `itinerary_segments_cabin` (`itinerary.segments[].cabin` via `buildClientItinerarySegments()`)
- `shop_context_segment_cabin_codes` / `shop_context_cabin_codes` / `shop_context_cabin_codes_by_segment` when present

## Validator behavior
Rejects `CabinCode` under Flight:
- `payload_schema_valid=false`
- `payload_schema_reason_code=revalidation_payload_unsupported_cabin_code`
- `contains_unsupported_cabin_code=true`
- `supplier_revalidation_call_count=0`

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmCabinCodeFlightChildSchemaCorrectionPhaseTest.php` (new)
- `tests/Unit/SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Routes changed
None

## Database changes
None

## Backend changes
BFM revalidate payload builder omits direct Flight `CabinCode`; schema validator and digests extended; booking service schema-block summary includes cabin fields.

## Frontend changes
None

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfmCabinCodeFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFareBasisFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest|SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest|SabreGdsLiveRevalidationOnlyProbeTest|SabreBookingRevalidatePhaseB13Test|SabreGdsRevalidationHttpSupplierErrorSanitizerTest"
```

## Production plan command (read-only after deploy)
```bash
php artisan sabre:gds-live-revalidation-only-probe \
  --plan \
  --connection=<ID> \
  --departure-date=<YYYY-MM-DD> \
  --origin=LHE \
  --destination=JED \
  --passenger-json=/path/to/safe-passenger-fixture.json
```

Expected plan artifact:
- `payload_schema_valid=true`
- `contains_unsupported_cabin_code=false`
- `unsupported_flight_child_keys=[]`
- `flight_child_keys` excludes `CabinCode`
- `booking_class_context_present=true`
- `cabin_context_present=true` when source cabin exists
- `fare_basis_context_present=true`
- `pricing_context_present=true`
- `fare_component_references_present=true`
- `supplier_revalidation_call_count=0`
- `db_mutation_detected=false`
- `revalidation_linkage_ready=true`

## SFTP upload paths
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`

Post-upload: `php artisan optimize:clear`

## One further live revalidation-only call required?
**Yes** — after deploy and read-only plan verification, one authorized `--send` revalidation-only probe is required to confirm Sabre accepts the corrected wire shape (same pattern as prior BFM Flight-child schema phases).

## Rollback instructions
Revert the five `app/` files above to the prior commit; run `php artisan optimize:clear`.

## Final status
Implementation complete pending test run and deploy authorization.
