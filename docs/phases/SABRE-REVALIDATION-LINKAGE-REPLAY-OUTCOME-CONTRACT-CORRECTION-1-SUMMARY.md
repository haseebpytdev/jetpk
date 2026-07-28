# SABRE-REVALIDATION-LINKAGE-REPLAY-OUTCOME-CONTRACT-CORRECTION-1

## Phase name
SABRE-REVALIDATION-LINKAGE-REPLAY-OUTCOME-CONTRACT-CORRECTION-1

## Branch name
`claude/sabre-revalidation-linkage-replay-outcome-contract-correction-1` (create from `claude/ui-master` before commit)

## Objective
Correct contradictory linkage-fixture replay and stored-run inspection semantics after production deployment proved linkage success but emitted misleading transport flags, failure reason codes, and candidate-count mappings.

## Production reference
- Plan run: `228d1e73-06ba-4e30-8567-bbeec7e40700` (`payload_schema_valid=true`, `supplier_revalidation_call_count=0`, `revalidation_linkage_ready=true`)
- Historical probe run: `25997adc-680d-430a-a047-d8fb64cf9dad` (artifact under `sabre-gds-revalidation-probes/`)

## Investigation findings

### Incorrect mappings (before correction)
| Field | Incorrect value | Correct value |
|---|---|---|
| `supplier_call_attempted` | `true` on fixture replay | `false` |
| `supplier_response_received` | `true` on fixture replay | `false` |
| `revalidation_attempted` | `true` on fixture replay | `false` |
| `revalidation_reason_code` | `scenario_revalidation_failed` with `revalidation_success=true` | `scenario_revalidation_success` |
| `response_candidate_count` (top-level) | `2` (structurally eligible subset) | `31` (declared supplier candidate count) |
| `--run-id` lookup | `sabre-gds-scenario-runs/` only | also searches `sabre-gds-revalidation-probes/` |

### Root causes
1. `SabreGdsRevalidationSanitizedOutcomeContract::wrap()` always forced live-call transport semantics when invoked without explicit replay flags.
2. `SabreGdsLiveScenarioRevalidationOutcomeMapper::classifyScenarioReasonCode()` returned `scenario_revalidation_failed` for all successful outcomes.
3. `response_candidate_count` preferred enumerated itinerary count (2) over declared structure count (31).
4. Diagnostic command had no probe artifact path or `--probe-run-id` option; summary-only probe artifacts could be misread as replayable.

## Included scope
- Linkage-fixture replay transport semantics (`mode=linkage_fixture_replay`, zero supplier activity)
- Success-compatible `revalidation_reason_code` (`scenario_revalidation_success`)
- Declared vs enumerated candidate count separation (`response_candidate_count=31`, `structurally_eligible_candidate_count=2`)
- Stored-run lookup across scenario runs and revalidation probes
- `--probe-run-id` for explicit probe artifact inspection
- Summary-only probe rejection with precise hint
- Phase unit tests

## Excluded scope
- No live Sabre calls / no `--send`
- No Booking/PNR/cancel/ticket/void/refund/communication mutation
- No migration / automatic retry / payload fallback
- No artifact move or duplication
- No raw payload/PCC/RequestorID/token/credential/PII exposure

## Exact files changed
### App
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`

### Tests / docs
- `tests/Unit/SabreRevalidationLinkageReplayOutcomeContractCorrectionPhaseTest.php` (new)
- `tests/Unit/SabreRevalidationBfmApplicationWarningAndResponseLinkageCorrectionPhaseTest.php` (success reason assertion update)
- `docs/phases/SABRE-REVALIDATION-LINKAGE-REPLAY-OUTCOME-CONTRACT-CORRECTION-1-SUMMARY.md` (this file)

## Routes changed
None.

## Database changes
None.

## Backend changes
- Fixture replay wraps outcomes with `supplier_call_attempted=false`, `supplier_response_received=false`, `revalidation_attempted=false`.
- Added replay fields: `fixture_response_present`, `fixture_response_analyzed`, `replay_performed`.
- Mapper success reason: `scenario_revalidation_success`.
- Candidate count resolver prefers `response_structure.candidate_count` over enumerated linkage count.
- Linker returns `enumerated_candidate_count` separately from declared `response_candidate_count`.
- `--run-id` searches `sabre-gds-scenario-runs` then `sabre-gds-revalidation-probes`; `--probe-run-id` targets probe directory explicitly.
- Summary-only probe artifacts emit `note=stored_probe_artifact_summary_only_not_replayable`.

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter=SabreRevalidation
```
- **112 passed**, 604 assertions (includes Phase 1 + Phase 2 + diagnostic feature tests)

### Phase 2 assertion coverage
- Zero supplier activity on linkage replay
- No `supplier_response_received=true` / `revalidation_attempted=true` on replay
- Success never emits `scenario_revalidation_failed`
- `response_candidate_count=31`, `structurally_eligible_candidate_count=2` consistent top-level and nested
- Scenario-run lookup still works
- Probe-run lookup supported; summary-only artifacts rejected
- No sensitive identifier leakage in CLI output
- Linkage evidence contract preserved (warnings, match counts, ordinal 2, pricing/linkage flags)

## Screenshots
N/A (CLI diagnostic phase).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Stored probe artifacts without sanitized grouped-itinerary structure remain summary-only; use `--linkage-fixture` for full linkage replay.
- `block_reason` is omitted on successful fixture replay (no live attempt).

## Risks
- Low: read-only diagnostic/mapping changes only; live revalidation path unchanged except shared mapper/count semantics.

## Rollback instructions
Revert the four app files and delete the new phase test + summary doc. No DB rollback required.

## Production verification (post-deploy, no live call)
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic \
  --linkage-fixture=/home/pkjetp/jetpk_app/storage/app/private/sabre/revalidation-fixtures/http-200-informational-warning-31-candidates-linkage.json
```
Confirm:
- `supplier_call_attempted=false`
- `supplier_response_received=false`
- `revalidation_attempted=false`
- `supplier_revalidation_call_count=0`
- `db_mutation_detected=false`
- `revalidation_success=true`
- `reason_code=sabre_revalidation_ok`
- `revalidation_reason_code=scenario_revalidation_success`
- `response_candidate_count=31`
- `structurally_eligible_candidate_count=2`
- `unique_usable_linkage_match_count=1`
- `selected_response_candidate_ordinal=2`

Stored probe inspect (summary-only, no supplier call):
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic --probe-run-id=25997adc-680d-430a-a047-d8fb64cf9dad
```

Stored run inspect (falls back to probe directory when scenario run missing):
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic --run-id=25997adc-680d-430a-a047-d8fb64cf9dad
```

## Commit SHA
Uncommitted at phase completion (`b155b10d0b9c5984c645d6aba473d746415cd2e9` workspace HEAD before phase commit).

## Final status
**PASS** — linkage fixture replay contract corrected; 112 revalidation tests green; no further live supplier call required for verification.
