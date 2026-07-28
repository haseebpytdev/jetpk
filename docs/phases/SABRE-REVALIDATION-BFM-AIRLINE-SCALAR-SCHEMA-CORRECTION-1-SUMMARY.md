# SABRE-REVALIDATION-BFM-AIRLINE-SCALAR-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-AIRLINE-SCALAR-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-airline-scalar-schema-correction-1` (from `claude/ui-master`)

## Objective
Correct `bfm_revalidate_v1` so `/v4/shop/flights/revalidate` emits schema-compatible scalar `Airline.Marketing` / `Airline.Operating` strings under `TPA_Extensions.Flight[]`, extend the local pre-HTTP schema guard, and persist the same schema/type evidence on plan and send artifacts.

## Included scope
- Scalar `Airline` emission for `bfm_revalidate_v1` only (IATI-like styles unchanged)
- `evaluateRevalidationPayloadSchema()` airline scalar-type validation
- Pre-HTTP block in `SabreBookingService::runRevalidationBeforeBooking()` for invalid airline types
- Send artifact top-level schema field persistence (parity with plan)
- Structural digest airline type/key evidence
- Freeze fingerprint airline field-type digest
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `e7447d1d-2534-4016-a047-f0654557ae63`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No automatic style fallback
- No migration
- `iati_like_bfm_revalidate_v1` / `manager_like_bfm_revalidate_v1` object `Airline.{Marketing,Operating}.Code` shape preserved

## Investigation findings
Production run `e7447d1d-2534-4016-a047-f0654557ae63` reached `TPA_Extensions.Flight[]` but Sabre rejected:
`JSON_ADAPTER: $.OTA_AirLowFareSearchRQ.OriginDestinationInformation[0].TPA_Extensions.Flight[0].Airline.Marketing: object found, string expected`.

Send artifact for that run showed `payload_schema_valid=null` and `contains_invalid_direct_flight_segment=null` because send mode did not merge plan-level schema fields onto the artifact root.

## Root causes
1. `buildBfmRevalidateFlightNodeFromSegment()` delegated to `buildIatiLikeFlightNodeFromSegment()`, which emits object carriers (`Marketing: { Code: string }`) suited to IATI wire parity, not BFM revalidate scalar schema.
2. `evaluateRevalidationPayloadSchema()` only guarded direct `FlightSegment` on ODI, not airline scalar types.
3. `SabreGdsLiveRevalidationOnlyProbe` send path omitted `planSchemaFieldsFromDraftContext()` merge.

## Exact method that emitted `Marketing` as object
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildIatiLikeFlightNodeFromSegment()` (lines ~1274–1277), invoked by `buildBfmRevalidateFlightNodeFromSegment()` before this phase.

## Old vs corrected structure (key/type names only)

**Old (invalid for BFM revalidate):**
```
TPA_Extensions.Flight[]
  Airline
    Marketing: object { Code: string }
    Operating: object { Code: string }
```

**Corrected:**
```
TPA_Extensions.Flight[]
  Airline
    Marketing: string
    Operating: string   # when segment operating context supplied
```

## Sibling field correction
`Operating` required the same scalar correction when present. No `Equipment` or validating-carrier relocation changes were needed for the BFM flight node shape.

## Validator behavior
For `bfm_revalidate_v1` + `/v4/shop/flights/revalidate`:
- Rejects non-string `Airline.Marketing` → `payload_schema_reason_code=revalidation_payload_invalid_airline_marketing_type`
- Rejects non-string `Airline.Operating` → `revalidation_payload_invalid_airline_operating_type`
- Emits `invalid_schema_paths`, `invalid_schema_type_count`, `airline_marketing_type_valid`, `airline_operating_type_valid`, `flight_child_keys`, `airline_child_keys`
- Blocks supplier HTTP with `supplier_revalidation_call_count=0` / `supplier_call_attempted=false`

## Send-artifact persistence correction
Send mode now merges `planSchemaFieldsFromDraftContext()` so top-level `payload_schema_valid`, `contains_invalid_direct_flight_segment`, airline type flags, and nested `payload_structural_digest` reflect pre-HTTP evaluation.

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest.php` (new)
- `tests/Unit/SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest.php`
- `tests/Feature/SabreBookingRevalidatePhaseB13Test.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Routes changed
None.

## Database changes
None.

## Backend changes
- `buildBfmRevalidateScalarAirlineFromSegment()` + override in `buildBfmRevalidateFlightNodeFromSegment()`
- Airline scalar validation in `evaluateRevalidationPayloadSchema()`
- Gatekeeper normalizer accepts scalar or object airline shapes
- Freeze fingerprint includes `airline_types` type digest
- Send artifact schema field merge

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter=SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest
php artisan test --filter=SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest
php artisan test --filter="SabreGdsLiveRevalidationOnlyProbeTest|SabreGdsRevalidationHttpSupplierErrorSanitizerTest|SabreGdsScenarioRevalidationDiagnosticPhaseTest"
```
**Result:** 56 tests, 396 assertions, all passed.

## Assertion counts (phase tests)
- 9 unit tests (`SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest`)
- 6 unit tests (existing FlightSegment phase, updated)
- 4 feature probe/B13 tests touched

## Screenshots
N/A (backend/schema phase).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Schema guard is keyed to `bfm_revalidate_v1` + `/v4/shop/flights/revalidate` only
- IATI-like styles retain object `Airline` nodes by design
- Further live Sabre validation errors beyond the persisted Marketing scalar rejection are not claimed without new evidence

## Risks
- Low: scalar airline shape is endpoint-specific; IATI paths unchanged
- Freeze fingerprint changes for corrected payloads (intended)

## Rollback instructions
Revert the files listed above; redeploy prior `SabreRevalidationPayloadBuilder` BFM flight-node airline emission.

## Production plan command (read-only after deploy)
```bash
php artisan sabre:gds-live-revalidation-only-probe \
  --plan \
  --connection=<SABRE_CONNECTION_ID> \
  --departure-date=<YYYY-MM-DD> \
  --origin=LHE \
  --destination=JED \
  --passenger-json=/path/to/safe-passenger-fixture.json
```
**Do not** pass `--send` until authorized.

Expected plan evidence:
- `payload_schema_valid=true`
- `contains_invalid_direct_flight_segment=false`
- `airline_marketing_type_valid=true`
- `supplier_revalidation_call_count=0`
- `db_mutation_detected=false`
- `revalidation_linkage_ready=true`

## SFTP upload paths
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`

## Further live revalidation-only call required?
**Yes — one authorized `--send` revalidation-only probe** after deploy + successful plan verification, to confirm Sabre accepts the scalar airline shape. No booking/PNR/cancel/ticket paths.

## Commit SHA
(pending commit on phase branch)

## Final status
Implementation complete locally; tests green; awaiting deploy, plan verification, and authorization for one live revalidation-only send.
