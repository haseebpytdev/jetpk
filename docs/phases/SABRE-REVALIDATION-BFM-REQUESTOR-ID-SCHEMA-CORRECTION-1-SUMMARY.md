# SABRE-REVALIDATION-BFM-REQUESTOR-ID-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-REQUESTOR-ID-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-requestor-id-schema-correction-1` (from `claude/ui-master`)

## Objective
Add the schema-required `OTA_AirLowFareSearchRQ.POS.Source.RequestorID.ID` attribute to `bfm_revalidate_v1` per live Sabre rejection (`run_id=6ad1d0f8-881d-4a6f-a24a-3c75488bf4fe`), extend the pre-HTTP schema guard, and preserve all prior exact-offer linkage digests.

## Included scope
- Emit `RequestorID.ID` on BFM revalidate POS.Source block
- Pre-HTTP rejection when `RequestorID.ID` is missing, null, empty, object, array, or boolean
- POS/Source/RequestorID child-key and type audit fields on plan/send artifacts
- `requestor_identity_source_present` / `requestor_identity_source_location` digest fields
- Payload freeze fingerprint extended with requestor-id digest
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `6ad1d0f8-881d-4a6f-a24a-3c75488bf4fe`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No automatic style fallback
- No migration
- No raw RequestorID, PCC, credential or passenger identity in artifacts

## Investigation findings
Production run `6ad1d0f8-881d-4a6f-a24a-3c75488bf4fe` rejected:
`Invalid request. It is not compliant with schema. Element: RequestorID. Error: The attribute 'ID' is required but missing.`

## Root causes
`SabreRevalidationPayloadBuilder::buildPayload()` default BFM path emitted `RequestorID` with only `Type` and `CompanyName` — omitting the required `ID` attribute.

## Exact method that omitted RequestorID.ID
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildPayload()` — default `bfm_revalidate_v1` POS.Source.RequestorID inline construction (lines ~232–235 pre-fix).

## Authoritative local source for ID value
| Source | ID | Type | CompanyName |
|--------|----|------|-------------|
| `SabreFlightSearchRequestBuilder::buildMinimalShopPayload()` | `'1'` | `'1'` | `['Code' => 'TN']` |
| `buildIatiLikeBfmRevalidateV1Envelope()` | `'1'` | `'1'` | `['Code' => 'TN']` |
| `SabreBookingPayloadBuilder` Trip Orders POS wire | `'1'` | `'1'` | `['Code' => 'TN']` |

Constants: `BFM_REVALIDATE_REQUESTOR_ID`, `BFM_REVALIDATE_REQUESTOR_TYPE`, `BFM_REVALIDATE_REQUESTOR_COMPANY_CODE` — OTA schema identity parity, not passenger/customer identity.

## Old vs corrected RequestorID structure

**Old child keys:** `Type` (string), `CompanyName` (array)  
**Corrected child keys:** `ID` (string), `Type` (string), `CompanyName` (array with `Code` string)

| Field | Old | Corrected |
|-------|-----|-----------|
| `ID` | absent | present, scalar string |
| `Type` | present | preserved |
| `ID_Context` | absent | absent (optional, no evidence) |
| `CompanyName` | present | preserved |

## POS/Source audit

| Field | Status |
|-------|--------|
| `POS.Source` | present, locally supported |
| `POS.Source.RequestorID` | present, locally supported |
| `POS.Source.PseudoCityCode` | absent in bfm_revalidate_v1 (optional; added by iati path when PCC available) |
| `POS.Source.ISOCountry` | absent (optional) |
| `POS.Source.AgentSine` | absent (optional) |

## Validator behavior
Rejects invalid/missing `RequestorID.ID`:
- `payload_schema_valid=false`
- `payload_schema_reason_code=revalidation_payload_missing_or_invalid_requestor_id`
- `requestor_id_present` / `requestor_id_type_valid` / `requestor_id_non_empty`
- `pos_child_keys`, `source_child_keys`, `requestor_id_child_keys`, `requestor_id_child_types`
- `supplier_revalidation_call_count=0`

Raw RequestorID value never emitted in safe output.

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmRequestorIdSchemaCorrectionPhaseTest.php` (new)
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Routes changed
None

## Database changes
None

## Backend changes
BFM revalidate payload emits `RequestorID.ID` via shop-parity block builder; schema validator and digests extended; booking service schema-block summary includes requestor-id fields.

## Frontend changes
None

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfmRequestorIdSchemaCorrectionPhaseTest|SabreRevalidationBfmRootVersionSchemaCorrectionPhaseTest|SabreRevalidationBfmBrandedFareIndicatorSchemaCorrectionPhaseTest|SabreRevalidationBfmCabinCodeFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFareBasisFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest|SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest|SabreGdsLiveRevalidationOnlyProbeTest::test_plan_mode|SabreGdsRevalidationHttpSupplierErrorSanitizerTest"
```
**96 passed** (10 new + 86 regression).

## Assertion counts (new phase tests)
10 tests, 62 assertions.

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
- `root_version_present=true`
- `requestor_id_present=true`
- `requestor_id_type_valid=true`
- `requestor_id_non_empty=true`
- `requestor_id_child_keys` includes `ID`
- `requestor_identity_source_present=true`
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

## Rollback instructions
Revert the five `app/` files above; run `php artisan optimize:clear`.

## Commit SHA
(pending commit)

## Final status
Implementation complete; tests green; awaiting deploy authorization and read-only production plan verification.
