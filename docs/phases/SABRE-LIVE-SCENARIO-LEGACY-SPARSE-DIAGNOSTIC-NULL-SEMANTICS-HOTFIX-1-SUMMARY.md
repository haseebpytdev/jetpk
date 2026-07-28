# Phase SABRE-LIVE-SCENARIO-LEGACY-SPARSE-DIAGNOSTIC-NULL-SEMANTICS-HOTFIX-1 — Summary

## Phase name
SABRE-LIVE-SCENARIO-LEGACY-SPARSE-DIAGNOSTIC-NULL-SEMANTICS-HOTFIX-1

## Branch name
*(not committed in this pass — working tree on `phase/BOOKING-CANCELLATION-SYNC-MAIL-LOG-FINALIZATION-1`)*

## Objective
Preserve three-state null semantics when replaying legacy sparse scenario run artifacts so absent supplier/response evidence is not synthesized as `false` or `candidate_count=0`.

## Included scope
- Tri-state boolean mapping for `supplier_call_attempted` and `supplier_response_received` in `SabreGdsLiveScenarioRevalidationOutcomeMapper`
- Guarded `response_structure_summary` resolution (only from explicit outcome fields or present `response_structure`)
- Unit tests for sparse vs explicit-false vs rich outcomes
- Feature test for legacy run `952d8cfe-793f-48d2-a535-ca923a67311e` replay output and no DB mutation

## Excluded scope
- No migration
- No live Sabre probe
- No change to rich outcome contract (`SabreGdsRevalidationSanitizedOutcomeContract`)
- No change to runner persistence for new runs beyond mapper tri-state passthrough in `extractScenarioResultFields`

## Investigation findings
- Legacy stored runs (e.g. `952d8cfe-793f-48d2-a535-ca923a67311e`) only persisted sparse `scenario_results` without supplier transport fields.
- `mapToScenarioEvidence()` coerced absent booleans via `($outcome['supplier_call_attempted'] ?? false) === true`, emitting `supplier_call_attempted=false` after `array_filter`.
- Absent `response_structure` triggered `buildResponseStructureSummary([])`, synthesizing `candidate_count=0`.

## Root causes
1. Default-false coercion on optional supplier boolean evidence.
2. Unconditional fallback build of `response_structure_summary` from empty `response_structure`.
3. `extractScenarioResultFields()` re-coerced absent evidence keys to `false` on round-trip.

## Exact files changed
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `tests/Unit/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapperTest.php`
- `tests/Feature/SabreGdsScenarioRevalidationDiagnosticPhaseTest.php`
- `docs/phases/SABRE-LIVE-SCENARIO-LEGACY-SPARSE-DIAGNOSTIC-NULL-SEMANTICS-HOTFIX-1-SUMMARY.md`

## Routes changed
None.

## Database changes
None.

## Backend changes
- Added `triStateBool()` and `resolveResponseStructureSummary()` helpers.
- `mapToScenarioEvidence()` and `extractScenarioResultFields()` now omit unrecorded supplier booleans and response summaries.

## Frontend changes
None.

## Tests executed
```text
php artisan test --filter="SabreGdsLiveScenarioRevalidationOutcomeMapperTest|SabreGdsScenarioRevalidationDiagnosticPhaseTest"
```
- 23 passed, 74 assertions

## Assertion counts
- Unit: sparse omission, explicit false preservation, no synthetic `candidate_count`, rich outcome unchanged
- Feature: legacy replay note + expected fields, no `supplier_call_attempted`/`supplier_response_received`/`response_structure_summary` lines, no Booking/SupplierBookingAttempt mutation

## Screenshots
N/A (CLI diagnostic only).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Pre-call blocked evidence via `mapBlockedEvidence()` still records `supplier_call_attempted=false` explicitly (intentional positive false for gate blocks).
- Legacy replay still synthesizes a minimal outcome from sparse `scenario_results`; only unrecorded transport/response fields are now omitted.

## Risks
Low — change is confined to mapper null semantics; rich wrapped outcomes unchanged.

## Rollback instructions
Revert `SabreGdsLiveScenarioRevalidationOutcomeMapper.php` and associated tests.

## Commit SHA
*(pending user commit)*

## Final status
**PASS** — legacy sparse replay omits unrecorded supplier/response fields; rich diagnostics preserved; tests green.
