# SABRE-GDS-REVALIDATION-ONLY-PROBE-CANONICAL-DIAGNOSTICS-PERSISTENCE-CORRECTION-3

## Phase name
SABRE-GDS-REVALIDATION-ONLY-PROBE-CANONICAL-DIAGNOSTICS-PERSISTENCE-CORRECTION-3

## Branch name
*(not committed in this pass — create from `claude/ui-master` per workflow)*

## Objective
Persist canonical linkage normalization diagnostics on revalidation-only probe artifacts for both successful and failed linkage, without live supplier calls in this phase.

## Included scope
- Probe artifact persistence via `SabreGdsLiveScenarioRevalidationOutcomeMapper::extractScenarioResultFields()`
- Unified persisted key `canonical_linkage_normalization_diagnostics`
- Nested copy under `revalidation_diagnostics.canonical_linkage_normalization_diagnostics`
- Stored inspection path in `SabreGdsRevalidationCanonicalSignatureRuntimePropagation::extractStoredArtifactSignatureDiagnostics()`
- Unit tests for failure/success persistence and stored CLI read

## Excluded scope
- Live Sabre HTTP, PNR, cancellation, ticketing
- Linkage algorithm or retry semantics changes
- Production deploy

## Investigation findings
- Post–Phase 2 probe run `7fe12461-f6b3-4e24-b15d-84f82cb46996` had HTTP 200 and linkage failure but artifact only contained legacy counters (`selected_segment_signature_hash`, `revalidation_diagnostics.mismatches`, etc.).
- `mapToScenarioEvidence()` already produced canonical flat fields and `canonical_linkage_normalization` on in-memory evidence.
- **Drop point:** `SabreGdsLiveRevalidationOnlyProbe::finalizeArtifact()` merge uses `extractScenarioResultFields($evidence)`, whose whitelist omitted all canonical normalization fields. `richOutcomeSlice()` only copied legacy `response_linkage_diagnostics` counters, not the canonical block.

## Root causes
1. Probe artifact assembly bypassed enriched mapper output by whitelisting scenario result fields without canonical persistence slice.
2. Stored signature CLI read `canonical_linkage_normalization` at top level only, not the new persisted key used by probes.

## Failure path vs success path
- **Same drop on both paths:** any run that reached `mapToScenarioEvidence()` lost canonical fields at `extractScenarioResultFields()` regardless of `revalidation_success`.
- Runtime/booking service paths were not the blocker for probe `7fe12461-…` once Phase 2 was deployed; persistence was.

## Exact persisted JSON path
- **Primary:** top-level `canonical_linkage_normalization_diagnostics` (object)
- **Flat mirrors (top-level):** `canonical_signature_version`, `selected_segment_signature_digest`, `draft_segment_signature_digest`, `selected_draft_signature_equal`, segment counts/digests, `structurally_eligible_candidate_signature_digests`, `candidate_mismatch_categories`, fare-basis and booking-class diagnostic fields
- **Nested mirror:** `revalidation_diagnostics.canonical_linkage_normalization_diagnostics` and `revalidation_diagnostics.canonical_linkage_normalization`
- **Legacy alias (still written):** top-level `canonical_linkage_normalization` (same object as persisted key)

## Files changed
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `tests/Unit/SabreRevalidationOnlyProbeCanonicalDiagnosticsPersistenceCorrectionPhaseTest.php`
- `docs/phases/SABRE-GDS-REVALIDATION-ONLY-PROBE-CANONICAL-DIAGNOSTICS-PERSISTENCE-CORRECTION-3-SUMMARY.md`

## Routes changed
None.

## Database changes
None.

## Backend changes
- Added `CANONICAL_LINKAGE_NORMALIZATION_DIAGNOSTICS_KEY`, resolver and artifact field extractor on outcome mapper.
- `extractScenarioResultFields()` merges canonical persistence slice (success and failure).
- `buildDiagnostics()` embeds canonical block for nested `revalidation_diagnostics`.
- `extractStoredArtifactSignatureDiagnostics()` resolves via mapper from probe/scenario artifact paths.

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter=SabreRevalidationOnlyProbeCanonicalDiagnosticsPersistenceCorrectionPhaseTest
php artisan test --filter=SabreRevalidationCanonicalSignatureRuntimePropagationCorrectionPhaseTest
php artisan test --filter=SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest
```

## Assertion counts
- Phase 3 unit: 5 tests, 24 assertions (pass)
- Phase 2 unit: 4 tests (pass)
- Phase 1 consolidated: 10 tests (pass)

## Screenshots
N/A

## Responsive verification
N/A

## Accessibility verification
N/A

## Known limitations
- `SabreGdsLiveRevalidationOnlyProbeTest::test_success_with_valid_linkage` still fails locally (linkage fixture vs shop snap); pre-existing, not introduced by this phase.
- Canonical fields appear on probe artifacts only when the booking/revalidation outcome includes canonical blocks (expected after Phase 2 runtime propagation on send path).

## Risks
- Low: additive JSON fields only; no linkage logic change.

## Rollback instructions
Revert the three application/test files above; redeploy prior build. Artifacts from runs before deploy remain legacy-shaped.

## Commit SHA
*(pending user commit)*

## Final status
Implementation and targeted tests **PASS**. A **new live revalidation-only probe** after deploy is **warranted** to confirm production artifact `7fe12461-…` pattern is fixed (one supplier call, then inspect with zero-call CLI).

## Zero-call production verification (after deploy)
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic --stored-signature-diagnostics=<run_id>
```
Expect `canonical_signature_diagnostics={...}` with digests and mismatch categories, not the legacy warning.
