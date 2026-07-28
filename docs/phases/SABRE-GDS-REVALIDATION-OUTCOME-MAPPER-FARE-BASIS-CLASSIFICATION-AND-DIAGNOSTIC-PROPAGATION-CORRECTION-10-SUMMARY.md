# SABRE-GDS-REVALIDATION-OUTCOME-MAPPER-FARE-BASIS-CLASSIFICATION-AND-DIAGNOSTIC-PROPAGATION-CORRECTION-10

## Branch name
`phase/SABRE-GDS-REVALIDATION-OUTCOME-MAPPER-FARE-BASIS-CLASSIFICATION-AND-DIAGNOSTIC-PROPAGATION-CORRECTION-10` (to be created per workflow)

## Objective
Stop false `scenario_revalidation_fare_basis_incomplete` when authoritative `fare_basis_complete=true`, enforce HTTP 200 linkage precedence in the scenario outcome mapper, and propagate full safe linkage diagnostics on all failed HTTP 200 booking paths.

## Investigation findings
1. **Bad predicate:** `SabreGdsLiveScenarioRevalidationOutcomeMapper::classifyScenarioReasonCode()` returned `REASON_FARE_BASIS_INCOMPLETE` whenever `failure_category === 'fare_basis_incomplete'` without checking `fare_basis_complete`.
2. **Stale failure class:** `SabreGdsRevalidationSanitizedOutcomeContract::wrap()` could set `fare_basis_complete=true` from linker/digest while leaving `failure_category=fare_basis_incomplete`.
3. **Secondary gate:** `SabreBookingService` could fail on `assertPerSegmentFareBasisComplete()` after linker proved unique usable fare-basis-compatible linkage.
4. **Missing propagation:** `fare_basis_incomplete` and `pricing_tripwire` failure returns omitted `canonicalOutcomeExtras` and `response_linkage_diagnostics` (unlike `unusable_linkage`).

## Root causes
- Mapper trusted legacy `failure_category` over authoritative sanitized flags and linkage counts.
- Booking service raw JSON fare-basis scrape disagreed with linker; failure path dropped linkage diagnostics.

## Files changed
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `tests/Unit/SabreGdsRevalidationOutcomeMapperFareBasisClassificationAndDiagnosticPropagationCorrectionPhaseTest.php` (new)
- `tests/Unit/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapperTest.php`

## Tests executed
- `SabreGdsRevalidationOutcomeMapperFareBasisClassificationAndDiagnosticPropagationCorrectionPhaseTest` (7 tests)
- `SabreGdsLiveScenarioRevalidationOutcomeMapperTest`
- `SabreRevalidationLinkageReplayOutcomeContractCorrectionPhaseTest`
- Phase 1–9 regression suites (canonical segment, BFM clock, scheduleDesc, shopping parity, PNR readiness audit)

## Final status
Implementation complete locally; commit/push pending review gate.

## Rollback
Revert the five files above.

## Commit SHA
(pending user-requested commit)
