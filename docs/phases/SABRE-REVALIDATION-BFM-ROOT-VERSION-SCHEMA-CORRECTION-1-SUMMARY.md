# SABRE-REVALIDATION-BFM-ROOT-VERSION-SCHEMA-CORRECTION-1

## Phase name
SABRE-REVALIDATION-BFM-ROOT-VERSION-SCHEMA-CORRECTION-1

## Branch name
`claude/sabre-revalidation-bfm-root-version-schema-correction-1` (from `claude/ui-master`)

## Objective
Add the schema-required `OTA_AirLowFareSearchRQ.Version` attribute to `bfm_revalidate_v1` per live Sabre rejection (`run_id=0fe1e542-feca-4317-9b1d-8c60054bc2ce`), extend the pre-HTTP schema guard, and preserve all prior exact-offer linkage digests.

## Included scope
- Emit `Version` at `OTA_AirLowFareSearchRQ` root for `bfm_revalidate_v1`
- Pre-HTTP rejection when `Version` is missing, null, empty, object, or array
- Root OTA child-key audit (`root_child_keys`, `root_version_present`, `root_version_type_valid`, `root_target_present`)
- Plan/send artifact schema field persistence
- Payload freeze fingerprint extended with root-version digest
- Unit/feature tests

## Excluded scope
- No live Sabre HTTP during implementation
- No rerun of `0fe1e542-feca-4317-9b1d-8c60054bc2ce`
- No booking/PNR/cancel/ticket/void/refund/communication changes
- No automatic style fallback
- No migration
- No fabrication of unsupported root attributes (`Target`, root `TPA_Extensions`, etc.)

## Investigation findings
Production run `0fe1e542-feca-4317-9b1d-8c60054bc2ce` rejected:
`Invalid request. It is not compliant with schema. Element: OTA_AirLowFareSearchRQ. Error: The attribute 'Version' is required but missing.`

## Root causes
`SabreRevalidationPayloadBuilder::buildPayload()` default BFM path constructed `OTA_AirLowFareSearchRQ` with `POS`, `OriginDestinationInformation`, `TravelPreferences`, and `TravelerInfoSummary` but omitted the required root `Version` attribute.

## Exact method that omitted Version
`App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder::buildPayload()` — default `bfm_revalidate_v1` envelope at `OTA_AirLowFareSearchRQ` root (lines ~220–243 pre-fix).

## Local evidence for Version value
| Source | Version value |
|--------|---------------|
| `SabreFlightSearchRequestBuilder::buildMinimalShopPayload()` | `'4'` |
| `SabreFlightSearchRequestBuilder::otaAirLowFareSearchVersion()` for `/v4/*` | `'4'` |
| `buildIatiLikeBfmRevalidateV1Envelope()` | `'4'` |
| Revalidate endpoint | `/v4/shop/flights/revalidate` |

Constant: `SabreRevalidationPayloadBuilder::BFM_REVALIDATE_OTA_VERSION = '4'`

## Old vs corrected root structure

**Old root child keys:** `POS`, `OriginDestinationInformation`, `TravelPreferences`, `TravelerInfoSummary`  
**Corrected root child keys:** `Version`, `POS`, `OriginDestinationInformation`, `TravelPreferences`, `TravelerInfoSummary`

| Field | Old | Corrected |
|-------|-----|-----------|
| `Version` | absent | present, scalar string |
| `Target` | absent | absent (not required by local BFM evidence) |
| `TPA_Extensions` (root) | absent | absent (not required for BFM revalidate v1) |

## Target correction required?
**No** — local BFM shop and revalidate builders do not emit `Target` on `OTA_AirLowFareSearchRQ`; no live rejection evidence for `Target`.

## Validator behavior
Rejects invalid/missing `OTA_AirLowFareSearchRQ.Version`:
- `payload_schema_valid=false`
- `payload_schema_reason_code=revalidation_payload_missing_or_invalid_root_version`
- `root_version_present` / `root_version_type_valid` booleans
- `root_child_keys`, `invalid_schema_paths`
- `supplier_revalidation_call_count=0`

Safe output reports presence and type only — not the Version scalar value.

## Files changed
- `app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Diagnostics/SabreRevalidationPayloadStructuralSchemaComparator.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Unit/SabreRevalidationBfmRootVersionSchemaCorrectionPhaseTest.php` (new)
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`

## Routes changed
None

## Database changes
None

## Backend changes
BFM revalidate payload emits root `Version`; schema validator and digests extended; booking service schema-block summary includes root-version fields.

## Frontend changes
None

## Tests executed
```bash
php artisan test --filter="SabreRevalidationBfmRootVersionSchemaCorrectionPhaseTest|SabreRevalidationBfmBrandedFareIndicatorSchemaCorrectionPhaseTest|SabreRevalidationBfmCabinCodeFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFareBasisFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest|SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest|SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest|SabreGdsLiveRevalidationOnlyProbeTest::test_plan_mode|SabreGdsRevalidationHttpSupplierErrorSanitizerTest"
```
**86 passed** (10 new + 76 regression).

## Assertion counts (new phase tests)
10 tests, 45 assertions.

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
- `root_version_type_valid=true`
- `root_child_keys` includes `Version`
- `supplier_revalidation_call_count=0`
- `db_mutation_detected=false`
- `revalidation_linkage_ready=true`
- all prior Flight/airline/cabin/fare/branded-fare schema evidence remains valid

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
