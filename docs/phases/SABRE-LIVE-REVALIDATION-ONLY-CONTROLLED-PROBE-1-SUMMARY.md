# Phase SABRE-LIVE-REVALIDATION-ONLY-CONTROLLED-PROBE-1 — Summary

## Phase name
SABRE-LIVE-REVALIDATION-ONLY-CONTROLLED-PROBE-1

## Branch name
*(not committed in this pass)*

## Objective
Add a dedicated controlled Sabre GDS revalidation-only probe command that searches, proves exact-offer linkage, builds the production revalidation draft, performs exactly one revalidation HTTP call, persists rich sanitized diagnostics, and stops — with zero booking/PNR/ticket/cancel/communication mutation.

## Included scope
- `sabre:gds-live-revalidation-only-probe` Artisan command (plan default, send gated)
- `SabreGdsLiveRevalidationOnlyProbe` orchestrator
- Hard in-memory `SabreGdsRevalidationProbeCallCounter` (max 1 revalidation call)
- `SabreGdsRevalidationProbeDbSnapshot` before/after verification
- Private artifact persistence under `storage/app/private/sabre-gds-revalidation-probes/{run_id}.json` (mode 0600)
- Sanitized payload structural digest before supplier call
- `pickCandidateByIndex()` on offer catalog
- Optional `endpointPath` on `SabreGdsRevalidationService::revalidateDraft()`
- Feature tests for plan/send gates, single-call limit, DB safety, classifications, PII exclusion

## Excluded scope
- No live production `--send` during implementation verification
- No migrations
- No booking/PNR/cancel/ticket/void/refund paths
- No automatic retry on ambiguous/transport/HTTP failures

## Investigation findings
- Existing scenario runner couples revalidation to book/PNR modes; a dedicated orchestrator isolates revalidation-only blast radius.
- Production confirmation pattern in codebase uses explicit phrase gates (`--confirm-production`, `--confirm-revalidation`) rather than generic `--confirm`.

## Root causes addressed
- No operator-safe command existed for a single controlled revalidation probe after sparse legacy run `952d8cfe-793f-48d2-a535-ca923a67311e`.
- Revalidation service could not pass endpoint override through `revalidateDraft()` without reaching booking service directly.

## Exact files changed
- `app/Console/Commands/SabreGdsLiveRevalidationOnlyProbeCommand.php` *(new)*
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php` *(new)*
- `app/Support/Sabre/Scenario/SabreGdsRevalidationProbeCallCounter.php` *(new)*
- `app/Support/Sabre/Scenario/SabreGdsRevalidationProbeDbSnapshot.php` *(new)*
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioOfferCatalog.php`
- `app/Services/Suppliers/Sabre/Gds/SabreGdsRevalidationService.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php` *(new)*
- `docs/phases/SABRE-LIVE-REVALIDATION-ONLY-CONTROLLED-PROBE-1-SUMMARY.md`

## Routes changed
None.

## Database changes
None.

## Backend changes
- Plan mode: live shop search + exact-offer evidence, no revalidation HTTP.
- Send mode: draft build + structural digest + one `revalidateDraft()` call + rich outcome mapper persistence.
- Ticketing-enabled config blocks command entirely.

## Frontend changes
None.

## Tests executed
```text
php artisan test --filter="SabreGdsLiveRevalidationOnlyProbeTest|SabreGdsLiveScenarioRevalidationOutcomeMapperTest|SabreGdsScenarioRevalidationDiagnosticPhaseTest"
```
- 36 passed, 145 assertions

## Assertion counts
- Plan: no revalidation HTTP, linkage fields present, no DB mutation
- Send: exactly one revalidation call, rich artifact, DB unchanged
- Gates: production + revalidation phrases, ticketing block
- Failure classes: HTTP 500, timeout, GIR error (no retry)
- PII/token exclusion in artifact

## Screenshots
N/A (CLI).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Initial production route `LHE→JED` + `qr-connecting` must be validated in plan mode before send; test fixtures use unfiltered `LHE→DXB` shop fixture.
- `supplier_revalidation_call_count` is in-memory per command execution only (no second call path exists).

## Risks
Medium for production `--send` (live supplier HTTP). Mitigated by dual confirmation phrases, ticketing block, DB snapshot verification, call counter, and no booking/PNR code paths.

## Rollback instructions
Remove new command/orchestrator files and revert `SabreGdsRevalidationService::revalidateDraft()` signature + `pickCandidateByIndex()`.

## Commit SHA
*(pending user commit)*

## Final status
**PASS** — command registered; plan/send paths implemented; tests green; no `--send` executed during verification.

## Production operator commands

### Plan (read-only search + linkage; no revalidation HTTP)
```bash
php artisan sabre:gds-live-revalidation-only-probe \
  --connection=1 \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --preset=qr-connecting \
  --candidate-index=0 \
  --confirm-production=APPROVE-LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE
```

### Send (separately authorized; exactly one revalidation HTTP call)
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

### Confirmation phrases
| Gate | Phrase |
|------|--------|
| Production | `APPROVE-LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE` |
| Revalidation send | `LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE` |

### Expected artifact path
`storage/app/private/sabre-gds-revalidation-probes/{run_id}.json`
