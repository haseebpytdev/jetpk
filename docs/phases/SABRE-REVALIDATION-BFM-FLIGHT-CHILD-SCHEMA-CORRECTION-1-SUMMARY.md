# SABRE-REVALIDATION-BFM-FLIGHT-CHILD-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-FLIGHT-CHILD-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-flight-child-schema-correction-1` (from `claude/ui-master`)

## Objective
Remove unsupported `SegmentNumber` from `bfm_revalidate_v1` `TPA_Extensions.Flight[]` nodes, add a BFM Flight child-key compatibility review, extend the pre-HTTP schema guard, and persist unsupported-key evidence on plan/send artifacts.

## Included scope
- Remove `SegmentNumber` emission from `buildBfmRevalidateFlightNodeFromSegment()`
- Restore `Number` as marketing flight number (not segment index)
- BFM Flight child-key allowlist/unsupported review (`evaluateBfmRevalidateFlightChildCompatibility()`)
- Pre-HTTP rejection of `SegmentNumber` under Flight
- Structural digest fields: `contains_unsupported_segment_number`, `unsupported_flight_child_keys`
- Freeze fingerprint flight-key digest
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `d71a5dd3-ad26-4020-a025-822d53acaf6c`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No automatic style fallback
- No migration
- No removal of other Flight keys not yet proven invalid (e.g. `CabinCode` retained)
- `shop_replay_selected_itinerary_v1` and IATI-like styles unchanged

## Investigation findings
Production run `d71a5dd3-ad26-4020-a025-822d53acaf6c` passed prior schema guards but Sabre rejected:
`JSON_ADAPTER: ...TPA_Extensions.Flight[0]: property 'SegmentNumber' is not defined in the schema and the schema does not allow additional properties.`

Plan digest had reported `SegmentNumber` among Flight child keys alongside accepted keys.

## Root causes
`buildBfmRevalidateFlightNodeFromSegment()` explicitly set both `Number` and `SegmentNumber` to `$segmentIndex + 1`, inherited from the prior BFM flight-node enrichment pattern (IATI segment indexing). The BFM revalidate endpoint schema does not allow `SegmentNumber` on `TPA_Extensions.Flight[]`.

## Exact method that emitted SegmentNumber
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildBfmRevalidateFlightNodeFromSegment()` — line assigning `$node['SegmentNumber'] = $segmentIndex + 1` (removed in this phase).

## Old vs corrected Flight key sets (names only)

**Old:**
```
Airline, ArrivalDateTime, CabinCode, ClassOfService, DepartureDateTime,
DestinationLocation, FareBasisCode, Number, OriginLocation, ResBookDesigCode,
SegmentNumber, Type
```

**Corrected:**
```
Airline, ArrivalDateTime, CabinCode, ClassOfService, DepartureDateTime,
DestinationLocation, FareBasisCode, Number, OriginLocation, ResBookDesigCode, Type
```

`Number` now carries marketing flight number from segment data (not segment index).

## Segment ordering preservation
Deterministic ordering via `TPA_Extensions.Flight[]` array order within the grouped ODI leg. ODI `RPH` and chronological segment sequence in the builder loop are unchanged.

## Validator behavior
For `bfm_revalidate_v1` + `/v4/shop/flights/revalidate`:
- Rejects `SegmentNumber` under any Flight node
- `payload_schema_reason_code=revalidation_payload_unsupported_flight_segment_number`
- `contains_unsupported_segment_number=true`
- `unsupported_flight_child_keys` lists disallowed/unknown keys (key names only)
- Blocks supplier HTTP (`supplier_revalidation_call_count=0`)

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest.php` (new)
- `tests/Unit/SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Routes changed
None.

## Database changes
None.

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest|SabreGdsLiveRevalidationOnlyProbeTest|SabreGdsRevalidationHttpSupplierErrorSanitizerTest"
```
**Result:** 55 tests, 299 assertions, all passed.

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

Expected:
- `payload_schema_valid=true`
- `contains_unsupported_segment_number=false`
- `unsupported_flight_child_keys=[]`
- `supplier_revalidation_call_count=0`
- `db_mutation_detected=false`
- `revalidation_linkage_ready=true`
- `flight_child_keys` includes `Number`, excludes `SegmentNumber`

## SFTP upload paths
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`

## Further live revalidation-only call required?
**Yes — one authorized `--send` revalidation-only probe** after deploy + successful plan verification.

## Commit SHA
(pending commit on phase branch)

## Final status
Implementation complete locally; tests green; awaiting deploy, plan verification, and authorization for one live revalidation-only send.
