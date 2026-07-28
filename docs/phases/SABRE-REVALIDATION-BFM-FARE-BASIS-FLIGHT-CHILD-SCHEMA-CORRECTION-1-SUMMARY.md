# SABRE-REVALIDATION-BFM-FARE-BASIS-FLIGHT-CHILD-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-FARE-BASIS-FLIGHT-CHILD-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-fare-basis-flight-child-schema-correction-1` (from `claude/ui-master`)

## Objective
Remove unsupported `FareBasisCode` from `bfm_revalidate_v1` `TPA_Extensions.Flight[]` nodes, extend the pre-HTTP schema guard, and preserve fare-basis continuity through shop/fare/pricing linkage digests.

## Included scope
- Remove `FareBasisCode` emission from `buildBfmRevalidateFlightNodeFromSegment()`
- Move `FareBasisCode` to unsupported Flight child-key list
- Pre-HTTP rejection of direct `FareBasisCode` under Flight
- `evaluateBfmRevalidateBookingClassFareContextDigest()` extended with `pricing_context_present` and `fare_component_references_present`
- `safePayloadSummary()` fare-basis detection from shop/fare context (not only Flight node)
- Plan/send artifact schema field persistence
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `f69c4552-6ca8-41bc-8e08-3dcb09b693b6`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No removal of `ClassOfService`, `CabinCode` (no live rejection evidence)
- No automatic style fallback
- No migration

## Investigation findings
Production run `f69c4552-6ca8-41bc-8e08-3dcb09b693b6` rejected:
`JSON_ADAPTER: ...TPA_Extensions.Flight[0]: property 'FareBasisCode' is not defined in the schema and the schema does not allow additional properties.`

## Root causes
`buildBfmRevalidateFlightNodeFromSegment()` set `$node['FareBasisCode'] = $fareBasis` when segment fare basis was present. BFM revalidate schema does not allow `FareBasisCode` as a direct Flight child.

## Exact method that emitted FareBasisCode
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildBfmRevalidateFlightNodeFromSegment()` — `$node['FareBasisCode'] = $fareBasis` (removed).

## Old vs corrected Flight key sets (names only)

**Old:** included `FareBasisCode`  
**Corrected:** `Airline`, `ArrivalDateTime`, `CabinCode`, `ClassOfService`, `DepartureDateTime`, `DestinationLocation`, `Number`, `OriginLocation`, `Type`

## Fare-basis context after removal
Preserved through safe linkage locations (no raw values):
- `shop_context_fare_basis_codes`
- `fare_context_fare_basis_codes`
- `pricing_information`
- `shop_context_fare_component_refs` / `fare_context_fare_component_refs` when present

## ClassOfService / CabinCode
Retained on Flight nodes — no persisted live rejection evidence.

## Validator behavior
Rejects `FareBasisCode` under Flight:
- `payload_schema_reason_code=revalidation_payload_unsupported_fare_basis_code`
- `contains_unsupported_fare_basis_code=true`
- `supplier_revalidation_call_count=0`

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmFareBasisFlightChildSchemaCorrectionPhaseTest.php` (new)
- `tests/Unit/SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest.php`
- `tests/Unit/SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest.php`
- `tests/Unit/SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest.php`
- `tests/Feature/SabreBookingRevalidatePhaseB13Test.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfmFareBasisFlightChildSchemaCorrectionPhaseTest|...|SabreGdsRevalidationHttpSupplierErrorSanitizerTest"
```
**Result:** 70 tests, 392 assertions, all passed.

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
- `contains_unsupported_fare_basis_code=false`
- `unsupported_flight_child_keys=[]`
- `flight_child_keys` excludes `FareBasisCode`
- `booking_class_context_present=true`
- `fare_basis_context_present=true`
- `supplier_revalidation_call_count=0`

## SFTP upload paths
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`

## Further live revalidation-only call required?
**Yes — one authorized `--send` revalidation-only probe** after deploy + successful plan verification.

## Final status
Implementation complete locally; tests green; awaiting deploy, plan verification, and authorization.
