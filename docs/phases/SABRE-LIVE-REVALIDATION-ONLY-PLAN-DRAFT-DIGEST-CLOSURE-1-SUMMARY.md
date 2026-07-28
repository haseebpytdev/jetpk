# Phase SABRE-LIVE-REVALIDATION-ONLY-PLAN-DRAFT-DIGEST-CLOSURE-1 — Summary

## Phase name
SABRE-LIVE-REVALIDATION-ONLY-PLAN-DRAFT-DIGEST-CLOSURE-1

## Branch name
*(not committed in this pass)*

## Objective
Close the plan-mode artifact gap for `sabre:gds-live-revalidation-only-probe`: accept passenger JSON in plan mode, build the exact revalidation draft and sanitized structural digest without any supplier revalidation HTTP call, and persist explicit zero-call / no-mutation diagnostics.

## Included scope
- `--passenger-json` required and validated in plan mode (same private file as send)
- Shared `buildRevalidationDraftContext()` for plan and send draft/digest construction
- Plan artifact persistence: `supplier_revalidation_call_count=0`, `db_mutation_detected=false`, full `payload_structural_digest`
- `db_mutation_detected=false` (boolean) when DB snapshots match
- `api_draft` stripped before artifact persistence (no raw payload leak)
- Feature tests for plan digest, invalid/missing passenger, explicit zero call count, DB safety, PII exclusion
- Send path unchanged: exactly one revalidation HTTP call

## Excluded scope
- No live `--send` during implementation verification
- No migrations
- No booking/PNR/cancel/ticket/void/refund/communication paths
- No changes to revalidation outcome mapping or retry policy

## Investigation findings
- Production plan run `9715dbfd-b0c6-4b1c-a906-169bed1a54e1` proved search/linkage but omitted digest fields because passenger JSON was only wired for `--send`.
- `finalizeArtifact()` previously set `db_mutation_detected` to `null` when unchanged, and omitted `supplier_revalidation_call_count` on plan success paths.

## Root causes
- Passenger loading gated behind `$mode === 'send'`.
- Plan mode returned immediately after linkage without draft/payload/digest build.
- `db_mutation_detected` used nullable semantics instead of explicit `false`.

## Exact files changed
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php`
- `docs/phases/SABRE-LIVE-REVALIDATION-ONLY-PLAN-DRAFT-DIGEST-CLOSURE-1-SUMMARY.md`

## Routes changed
None.

## Database changes
None.

## Backend changes
- Passenger JSON validated for both plan and send before shop search.
- Plan mode runs full draft + structural digest pipeline via shared builder; zero revalidation HTTP.
- `finalizeArtifact()` always sets `supplier_revalidation_call_count` when absent; `db_mutation_detected=false` on unchanged DB.
- Command exit: plan fails on `passenger_json_required` / `passenger_json_invalid`; DB mutation when `db_mutation_detected !== false`.

## Frontend changes
None.

## Tests executed
```text
php artisan test --filter="SabreGdsLiveRevalidationOnlyProbeTest"
```
- 15 passed, 112 assertions

## Assertion counts
- Plan with passenger: digest keys present, `supplier_revalidation_call_count=0`, `db_mutation_detected=false`, no revalidation HTTP
- Plan invalid/missing passenger: exit failure, zero revalidation HTTP, call count 0 in artifact
- Send: exactly one revalidation call, digest present, `db_mutation_detected=false`
- PII/raw payload/api_draft excluded from artifacts
- No Booking/PNR/communication DB mutation

## Screenshots
N/A (CLI).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Plan mode now requires `--passenger-json` (aligned with send); linkage-only plan without passenger is no longer supported.
- Draft-invalid plan paths may include partial digest from invalid draft (same as send blocked path).

## Risks
Low for plan mode (no supplier revalidation HTTP). Send behavior unchanged.

## Rollback instructions
Revert the three code files above; plan mode reverts to linkage-only artifacts without digest.

## Commit SHA
*(pending user commit)*

## Final status
**PASS** — plan builds digest with passenger JSON, zero revalidation calls, explicit artifact fields; tests green; no live `--send` executed.

## Production operator commands

### Plan (search + linkage + draft digest; no revalidation HTTP)
```bash
php artisan sabre:gds-live-revalidation-only-probe \
  --connection=1 \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --preset=qr-connecting \
  --candidate-index=0 \
  --payload-style=bfm_revalidate_v1 \
  --endpoint-path=/v4/shop/flights/revalidate \
  --passenger-json=/path/to/private/passenger.json \
  --confirm-production=APPROVE-LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE
```

### Send (unchanged; exactly one revalidation HTTP call)
```bash
php artisan sabre:gds-live-revalidation-only-probe \
  --connection=1 \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --preset=qr-connecting \
  --candidate-index=0 \
  --payload-style=bfm_revalidate_v1 \
  --endpoint-path=/v4/shop/flights/revalidate \
  --passenger-json=/path/to/private/passenger.json \
  --send \
  --confirm-production=APPROVE-LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE \
  --confirm-revalidation=LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE
```
