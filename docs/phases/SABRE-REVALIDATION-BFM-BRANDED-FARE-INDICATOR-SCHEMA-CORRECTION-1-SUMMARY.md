# SABRE-REVALIDATION-BFM-BRANDED-FARE-INDICATOR-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-BRANDED-FARE-INDICATOR-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-branded-fare-indicator-schema-correction-1` (from `claude/ui-master`)

## Objective
Remove unsupported lowercase `singleBrandedFare` from `bfm_revalidate_v1` `TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators` per live Sabre JSON_ADAPTER rejection (`run_id=08962a3b-f581-4363-8a83-5006b8f1d32c`), extend the pre-HTTP schema guard, and preserve branded-fare intent through schema-supported shop/fare/pricing linkage digests.

## Included scope
- Remove `BrandedFareIndicators.singleBrandedFare` emission from default BFM revalidate `buildPayload()` path
- Pre-HTTP rejection of lowercase `singleBrandedFare` (`revalidation_payload_unsupported_single_branded_fare`)
- Branded-fare indicator child-key/type audit fields on plan/send artifacts
- `branded_fare_context_present` / `branded_fare_context_location` digest fields
- Payload freeze fingerprint extended with `branded_fare_keys` digest
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `08962a3b-f581-4363-8a83-5006b8f1d32c`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No automatic style fallback
- No migration
- No fabrication of brand-selection wire values

## Investigation findings
Production run `08962a3b-f581-4363-8a83-5006b8f1d32c` rejected:
`JSON_ADAPTER: ...TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators: property 'singleBrandedFare' is not defined in the schema and the schema does not allow additional properties.`

## Root causes
`SabreRevalidationPayloadBuilder::buildPayload()` default BFM path inlined:
```php
'BrandedFareIndicators' => ['singleBrandedFare' => true]
```
under `TravelerInfoSummary.PriceRequestInformation.TPA_Extensions`.

Certified BFM **shop** builders (`SabreFlightSearchRequestBuilder::brandedFareIndicatorsBlock()`) use PascalCase `SingleBrandedFare` / `MultipleBrandedFares` (and optionally `ReturnBrandAncillaries`) — not lowercase `singleBrandedFare`. Proven revalidation styles (`iati_like_bfm_revalidate_v1`, `manager_like_bfm_revalidate_v1`) omit `BrandedFareIndicators` entirely on `/v4/shop/flights/revalidate`.

## Exact method that emitted singleBrandedFare
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildPayload()` — default `bfm_revalidate_v1` envelope construction at `TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators`.

## Old vs corrected BrandedFareIndicators structure

| Aspect | Old (rejected) | Corrected |
|--------|----------------|-------------|
| Block present | yes | **removed** (optional for revalidate) |
| Child key | `singleBrandedFare` (camelCase) | absent |
| Child type | `boolean` (`true`) | n/a |
| Schema evidence | live JSON_ADAPTER rejection | shop builder uses `SingleBrandedFare`; revalidate IATI styles omit block |

**Decision:** **removed** (not renamed). No schema-supported revalidate replacement was evidenced locally; branded-fare intent remains in `shop_context` / `fare_context` / `pricingInformation` digests only.

## BrandedFareIndicators child audit (old emission)

| Child key | Type | Local schema evidence | Live rejection |
|-----------|------|----------------------|----------------|
| `singleBrandedFare` | boolean | **unsupported** on revalidate (rejected live) | **yes** — persisted production rejection |

No other children were emitted under `BrandedFareIndicators` in the rejected payload.

## Branded-fare context after removal
Preserved through safe linkage locations (no raw values):
- `shop_context_brand_code`
- `shop_context_selected_brand_code`
- `selected_offer_context`
- `pricing_information`

## Validator behavior
Rejects lowercase `singleBrandedFare` under `BrandedFareIndicators`:
- `payload_schema_valid=false`
- `payload_schema_reason_code=revalidation_payload_unsupported_single_branded_fare`
- `contains_unsupported_single_branded_fare=true`
- `unsupported_branded_fare_indicator_keys=["singleBrandedFare"]`
- `branded_fare_indicator_child_keys` / `branded_fare_indicator_child_types` (keys/types only)
- `supplier_revalidation_call_count=0`

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmBrandedFareIndicatorSchemaCorrectionPhaseTest.php` (new)
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Routes changed
None

## Database changes
None

## Backend changes
BFM revalidate payload omits unsupported `BrandedFareIndicators`; schema validator and plan/send digests extended; booking service schema-block summary includes branded-fare fields; freeze fingerprint includes branded-fare child-key digest.

## Frontend changes
None

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfmBrandedFareIndicatorSchemaCorrectionPhaseTest|SabreRevalidationBfmCabinCodeFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFareBasisFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest|SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest|SabreGdsLiveRevalidationOnlyProbeTest::test_plan_mode|SabreGdsRevalidationHttpSupplierErrorSanitizerTest"
```
**76 passed** (9 new + 67 regression).

## Assertion counts (new phase tests)
9 tests, 42 assertions.

## Responsive verification
N/A (backend-only).

## Accessibility verification
N/A (backend-only).

## Known limitations
- Only lowercase `singleBrandedFare` is pre-HTTP blocked; PascalCase `SingleBrandedFare` was not live-rejected and is not emitted on the corrected path.
- Branded-fare wire qualifiers remain available only on BFM **shop** probe paths (`SabreFlightSearchRequestBuilder`), not revalidate.

## Risks
Low — removes an already-rejected unsupported key; preserves linkage digests and all prior Flight-child corrections.

## Rollback instructions
Revert the seven changed files above; run `php artisan optimize:clear`.

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
- `contains_unsupported_single_branded_fare=false`
- `unsupported_branded_fare_indicator_keys=[]`
- `branded_fare_indicator_child_keys=[]`
- `supplier_revalidation_call_count=0`
- `db_mutation_detected=false`
- `revalidation_linkage_ready=true`
- prior Flight schema evidence remains valid

## SFTP upload paths
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`

Post-upload: `php artisan optimize:clear`

## One further live revalidation-only call required?
**Yes** — after deploy and read-only plan verification, one authorized `--send` revalidation-only probe is required to confirm Sabre accepts the corrected wire shape (same pattern as prior BFM schema phases).

## Commit SHA
(pending commit)

## Final status
Implementation complete; tests green; awaiting deploy authorization and read-only production plan verification.
