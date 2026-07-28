# SABRE-REVALIDATION-BFM-PSEUDO-CITY-CODE-CONTEXT-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-PSEUDO-CITY-CODE-CONTEXT-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-pseudo-city-code-context-correction-1` (from `claude/ui-master`)

## Objective
Emit `OTA_AirLowFareSearchRQ.POS.Source.PseudoCityCode` on `bfm_revalidate_v1` from authoritative local Sabre configuration per live rejection (`run_id=cd9d75a9-1b9d-4fff-bad2-041630f614f3`), block before HTTP when PCC is unavailable, and preserve all prior exact-offer linkage digests.

## Included scope
- Emit `PseudoCityCode` on POS.Source when authoritative PCC resolves
- Deterministic PCC precedence via `resolveBfmRevalidatePseudoCityCodeContext()`
- Pre-HTTP rejection when `PseudoCityCode` is missing, null, empty, object, array, or boolean
- Safe PCC source-location digest fields on plan/send artifacts
- Payload freeze fingerprint extended with pseudo-city-code digest
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `cd9d75a9-1b9d-4fff-bad2-041630f614f3`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No automatic style fallback
- No migration
- No raw PCC/IPCC/credential values in artifacts

## Investigation findings
Production run `cd9d75a9-1b9d-4fff-bad2-041630f614f3` rejected:
`Unable to determine PseudoCityCode`

`bfm_revalidate_v1` emitted `POS.Source.RequestorID` but omitted `PseudoCityCode`. Production BFM shop (`SabreFlightSearchRequestBuilder::buildMinimalShopPayload()`) and `iati_like_bfm_revalidate_v1` both include `PseudoCityCode` when PCC resolves.

## PCC vs IPCC
This implementation uses **PCC only** (`PseudoCityCode` / `pcc` / `pseudo_city_code` keys). No separate IPCC field exists in local Sabre builders or connection configuration.

## Authoritative PCC source and precedence
`resolveBfmRevalidatePseudoCityCodeContext()` (same order as existing `resolvePseudoCityCodeFromDraft()`):

1. `draft._sabre_pseudo_city_code` → `draft_sabre_pseudo_city_code`
2. `_sabre_shop_context` / `_sabre_shop_identifiers` keys: `pcc`, `PCC`, `pseudo_city_code`, `pseudoCityCode` → `shop_context_<key>`
3. `supplier_connection_id` credentials then settings (same keys) → `supplier_connection_credentials_<key>` / `supplier_connection_settings_<key>`

No invented fallback. If no authoritative PCC resolves, payload omits `PseudoCityCode` and pre-HTTP validator blocks.

## Old vs corrected POS.Source structure

**Old source child keys:** `RequestorID`  
**Corrected source child keys:** `PseudoCityCode`, `RequestorID`

| Field | Old | Corrected |
|-------|-----|-----------|
| `PseudoCityCode` | absent | present when PCC resolves |
| `RequestorID.ID` | present | preserved |
| `RequestorID.Type` | present | preserved |
| `RequestorID.CompanyName` | present | preserved |

## Validator behavior
Rejects invalid/missing `PseudoCityCode`:
- `payload_schema_valid=false`
- `payload_schema_reason_code=revalidation_payload_missing_or_invalid_pseudo_city_code`
- `pseudo_city_code_present` / `pseudo_city_code_type_valid` / `pseudo_city_code_non_empty`
- `pseudo_city_code_source_present` / `pseudo_city_code_source_location`
- `supplier_revalidation_call_count=0`

Raw PCC value never emitted in safe output.

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmPseudoCityCodeContextCorrectionPhaseTest.php` (new)
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfmPseudoCityCodeContextCorrectionPhaseTest|SabreRevalidationBfmRequestorIdSchemaCorrectionPhaseTest|SabreRevalidationBfmRootVersionSchemaCorrectionPhaseTest|SabreRevalidationBfmBrandedFareIndicatorSchemaCorrectionPhaseTest|SabreRevalidationBfmCabinCodeFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFareBasisFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest|SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest|SabreGdsLiveRevalidationOnlyProbeTest::test_plan_mode|SabreGdsRevalidationHttpSupplierErrorSanitizerTest"
```
**108 passed** (12 new + 96 regression).

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
- `pseudo_city_code_present=true`
- `pseudo_city_code_type_valid=true`
- `pseudo_city_code_non_empty=true`
- `pseudo_city_code_source_present=true`
- `source_child_keys` includes `PseudoCityCode`
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
**Yes** — after deploy and read-only plan verification, one authorized `--send` revalidation-only probe is required to confirm Sabre accepts the corrected wire shape.

## Final status
Implementation complete; tests green; awaiting deploy authorization and read-only production plan verification.
