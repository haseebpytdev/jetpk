# SABRE-REVALIDATION-LINKAGE-REPLAY-RETRY-SEMANTICS-AND-CLI-DEDUPE-CORRECTION-1

## Phase name
SABRE-REVALIDATION-LINKAGE-REPLAY-RETRY-SEMANTICS-AND-CLI-DEDUPE-CORRECTION-1

## Branch name
`claude/sabre-revalidation-linkage-replay-retry-semantics-and-cli-dedupe-correction-1` (create from `claude/ui-master` before commit)

## Objective
Correct successful linkage-fixture replay retry semantics and remove duplicate CLI field output while preserving prior transport, reason-code, candidate-count, and stored-probe inspection behavior.

## Investigation findings

### Retry-field ownership
| Layer | Role |
|---|---|
| `SabreGdsRevalidationSanitizedOutcomeContract::computeRetrySafe()` | **Canonical source** for `retry_safe` and `retry_idempotency_safe` |
| `SabreGdsLiveScenarioRevalidationOutcomeMapper` | Passes through outcome retry flags into evidence/diagnostics |
| `SabreGdsScenarioRevalidationDiagnosticCommand::replayLinkageFixture()` | No longer prints retry/transport fields separately (single `printEvidence` block) |
| `SabreGdsRevalidationResponseCandidateLinker` | Does not set retry flags |

### Root cause
`computeRetrySafe()` returned `true` whenever `supplier_call_attempted=false`, treating local fixture replay idempotency as supplier-operation retry eligibility. Successful replay outcomes (`success=true`) therefore incorrectly emitted `retry_safe=true` and `retry_idempotency_safe=true`.

### CLI duplication root cause
`replayLinkageFixture()` printed transport/warning/count/replay fields manually, then `printEvidence()` printed the same mapped fields again.

## Included scope
- Successful outcomes (`success=true`) now emit `retry_safe=false` and `retry_idempotency_safe=false`
- Failed pre-call outcomes retain retry-eligible semantics where appropriate
- Failed non-retryable supplier outcomes remain fail-closed
- Single canonical CLI output block for `--linkage-fixture`
- Phase unit tests

## Excluded scope
- No live Sabre calls / no `--send`
- No Booking/PNR/cancel/ticket/void/refund/communication mutation
- No migration / historical artifact rewrite
- No change to summary-only probe inspection semantics

## Exact files changed
### App
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationSanitizedOutcomeContract.php`
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php`

### Tests / docs
- `tests/Unit/SabreRevalidationLinkageReplayRetrySemanticsAndCliDedupeCorrectionPhaseTest.php` (new)
- `tests/Unit/SabreRevalidationLinkageReplayOutcomeContractCorrectionPhaseTest.php` (retry assertion update)
- `docs/phases/SABRE-REVALIDATION-LINKAGE-REPLAY-RETRY-SEMANTICS-AND-CLI-DEDUPE-CORRECTION-1-SUMMARY.md` (this file)

## Routes changed
None.

## Database changes
None.

## Backend changes
- `computeRetrySafe()` checks `success=true` first and returns `false` (no retry after completed success).
- Linkage replay CLI emits one ordered evidence block via `printEvidence()` including `mode=linkage_fixture_replay`.

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter=SabreRevalidation
```
- **121 passed**, 653 assertions

## Known limitations
- Nested `revalidation_diagnostics` JSON may still include `retry_idempotency_safe`; top-level CLI fields are deduplicated only.

## Risks
- Low: retry semantics change affects all successful wrapped outcomes (live and replay), aligning with “completed success requires no retry.”

## Rollback instructions
Revert the two app files and new phase test; restore prior phase-2 test retry assertions.

## Production verification (no live call)
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic \
  --linkage-fixture=/home/pkjetp/jetpk_app/storage/app/private/sabre/revalidation-fixtures/http-200-informational-warning-31-candidates-linkage.json
```
Confirm single occurrence of each top-level field and `retry_safe=false`, `retry_idempotency_safe=false`.

Stored probe inspect:
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic --probe-run-id=25997adc-680d-430a-a047-d8fb64cf9dad
php artisan sabre:gds-scenario-revalidation-diagnostic --run-id=25997adc-680d-430a-a047-d8fb64cf9dad
```

## Commit SHA
Uncommitted at phase completion.

## Final status
**PASS** — retry semantics corrected, CLI deduplicated, 121 revalidation tests green; no further live supplier call required.
