# SABRE-REVALIDATION-BFM-BOOKING-CLASS-FLIGHT-CHILD-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-BOOKING-CLASS-FLIGHT-CHILD-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-booking-class-flight-child-schema-correction-1` (from `claude/ui-master`)

## Objective
Remove unsupported `ResBookDesigCode` from `bfm_revalidate_v1` `TPA_Extensions.Flight[]` nodes, extend the BFM Flight child-key compatibility review and pre-HTTP schema guard, and persist booking-class/fare-basis context location evidence on plan/send artifacts.

## Included scope
- Remove `ResBookDesigCode` emission from `buildBfmRevalidateFlightNodeFromSegment()`
- Retain `ClassOfService`, `CabinCode`, `FareBasisCode` on Flight (no live evidence against them)
- BFM Flight child-key allowlist update (`ResBookDesigCode` → unsupported)
- Pre-HTTP rejection of `ResBookDesigCode` under Flight
- `evaluateBfmRevalidateBookingClassFareContextDigest()` safe location labels
- Structural digest fields for booking-class/fare-basis context
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `7239a0b4-43ea-44d2-addf-b3edb1fd1d0c`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No automatic style fallback
- No migration
- No removal of `ClassOfService`, `CabinCode`, or `FareBasisCode` without evidence
- IATI/manager/shop-replay styles unchanged

## Investigation findings
Production run `7239a0b4-43ea-44d2-addf-b3edb1fd1d0c` passed prior schema guards but Sabre rejected:
`JSON_ADAPTER: ...TPA_Extensions.Flight[0]: property 'ResBookDesigCode' is not defined in the schema and the schema does not allow additional properties.`

## Root causes
`buildBfmRevalidateFlightNodeFromSegment()` explicitly set `ResBookDesigCode` alongside `ClassOfService` when booking class was present. The BFM revalidate endpoint schema does not allow `ResBookDesigCode` as a direct Flight child.

## Exact method that emitted ResBookDesigCode
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildBfmRevalidateFlightNodeFromSegment()` — `$node['ResBookDesigCode'] = $bookingClass` (removed in this phase).

## Old vs corrected Flight key sets (names only)

**Old:**
```
Airline, ArrivalDateTime, CabinCode, ClassOfService, DepartureDateTime,
DestinationLocation, FareBasisCode, Number, OriginLocation, ResBookDesigCode, Type
```

**Corrected:**
```
Airline, ArrivalDateTime, CabinCode, ClassOfService, DepartureDateTime,
DestinationLocation, FareBasisCode, Number, OriginLocation, Type
```

## Booking-class context preservation
After removal, booking-class continuity is preserved through:
- `ota_flight_class_of_service` (`ClassOfService` on Flight)
- `shop_context_booking_classes` (and related shop_context keys)
- `pricing_information`
- `itinerary_segments`

Fare-basis continuity through:
- `ota_flight_fare_basis_code` (`FareBasisCode` on Flight)
- `shop_context_fare_basis_codes`
- `fare_context_fare_basis_codes`
- `pricing_information`

## ClassOfService / CabinCode / FareBasisCode
No relocation required — no persisted live rejection evidence. Retained as direct Flight children pending further supplier feedback.

## Validator behavior
For `bfm_revalidate_v1` + `/v4/shop/flights/revalidate`:
- Rejects `ResBookDesigCode` under any Flight node
- `payload_schema_reason_code=revalidation_payload_unsupported_resbookdesigcode`
- `contains_unsupported_resbookdesigcode=true`
- Blocks supplier HTTP (`supplier_revalidation_call_count=0`)

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest.php` (new)
- `tests/Unit/SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest.php`
- `tests/Unit/SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest.php`
- `tests/Feature/SabreBookingRevalidatePhaseB13Test.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest|SabreGdsLiveRevalidationOnlyProbeTest|SabreGdsRevalidationHttpSupplierErrorSanitizerTest"
```
**Result:** 63 tests, 354 assertions, all passed.

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
- `contains_unsupported_resbookdesigcode=false`
- `unsupported_flight_child_keys=[]`
- `booking_class_context_present=true`
- `fare_basis_context_present=true`
- `flight_child_keys` excludes `ResBookDesigCode`, includes `ClassOfService` and `Number`
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
Implementation complete locally; tests green; awaiting deploy, plan verification, and authorization for one live revalidation-only send.
