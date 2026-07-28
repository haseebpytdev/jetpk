# SABRE-GDS-LIVE-REVALIDATION-CANONICAL-SIGNATURE-RUNTIME-PROPAGATION-CORRECTION-2

## Objective
Propagate canonical segment signatures through the live runtime path and persist safe normalization diagnostics on scenario artifacts.

## Root cause (run 3834f1e0-357b-47e5-b0df-1ffb836d6eb5)
Phase-1 canonical hashing existed in linker/evidence classes and passed fixture replay, but the **live path** still built linkage context from **raw `prepareBookingPayload` draft segments** (B65 segment prep / flight-number shaping) while shop evidence used **raw snap segments**. Signatures diverged at HTTP linkage time. Additionally, `safeResponseLinkageDiagnosticsSlice()` whitelisted only counters and **dropped** `linkage_normalization_diagnostics` / canonical fields before mapper artifacts — so production showed null diagnostics.

Fare basis: draft carried per-segment fare basis from handoff, but response candidate fare basis merge could remain incomplete on structurally eligible rows; post-response diagnostics now expose `fare_basis_presence_by_candidate` and `fare_basis_applicability_match_count` separately from schedule signature matching.

## Files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php` (new)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php` (VERSION constant)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioExactOfferEvidence.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationGate.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveRevalidationOnlyProbe.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Console/Commands/SabreGdsScenarioRevalidationDiagnosticCommand.php`
- `tests/Unit/SabreRevalidationCanonicalSignatureRuntimePropagationCorrectionPhaseTest.php` (new)

## Zero-call verification
```bash
php artisan test --filter=SabreRevalidationCanonicalSignatureRuntimePropagationCorrectionPhaseTest
php artisan test --filter=SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest
php artisan sabre:gds-scenario-revalidation-diagnostic --linkage-fixture=tests/Fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json
php artisan sabre:gds-scenario-revalidation-diagnostic --stored-signature-diagnostics=3834f1e0-357b-47e5-b0df-1ffb836d6eb5
```
(Last command summarizes persisted diagnostics only; legacy artifacts may warn that fields were not persisted.)

## SFTP upload paths
Upload all changed `app/` files listed above.

## Fresh live QR attempt
After deploy: one controlled QR revalidation-only or scenario revalidation run is appropriate to confirm non-null canonical diagnostics and `selected_draft_signature_equal=true` before any PNR attempt.

## Final status
Implementation complete locally; no live supplier calls in this phase.
