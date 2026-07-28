# SABRE-GDS-LIVE-BFM-OPERATING-CARRIER-AND-FARE-BASIS-APPLICABILITY-CORRECTION-4

## Objective
Fix BFM response-side operating-carrier canonical equivalence and fare-component fare-basis applicability without weakening linkage.

## Root causes
1. **Operating carrier:** `normalizeScheduleSegment()` could emit `operating_carrier=QR` while shop/draft omitted operating when it matched marketing, so the canonical signature slot differed (`''` vs `QR`).
2. **Fare basis:** `fareSegmentsFromItinerary()` only read inline `fareBasisCode` on segment wraps. Live GIR stores basis on `fareComponentDescs` and segment applicability via `id` / refs, so overlay stayed empty (`per_segment_present=[false,false]`).

## Equivalence rule (Part A)
Centralized in `SabreGdsRevalidationCanonicalSegmentSignature::canonicalOperatingCarrierForSignature()`:
- Absent operating → signature slot empty.
- Operating equals marketing → signature slot empty (equivalent to absent).
- Operating differs from marketing → codeshare slot retained (fail-closed).
- BFM schedules use `segmentRowFromScheduleDesc()` with marketing fallback when only operating is present.

Diagnostics: `operating_carrier_shape_categories` (`absent`, `same_as_marketing`, `different_from_marketing`).

## Fare applicability path (Part B)
`SabreGdsRevalidationResponseCandidateLinker::resolveFareOverlayByScheduleIndex()`:
- Index schedule segments from leg/scheduleDesc refs.
- Resolve `fareBasisCode` from fare component inline or `fareComponentDescs` ref.
- Map segment applicability via schedule ref, segment `id`/`segmentNumber`, or route keys.
- Positional mapping only when **no** applicability hints and wrap count equals schedule count (31-candidate inline shape).
- Ambiguous conflicting basis on one index clears that index (fail-closed).

## Files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `tests/Unit/SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest.php`
- `tests/Feature/SabreGdsLiveRevalidationOnlyProbeTest.php` (GIR success fixture)

## Tests
```bash
php artisan test --filter=SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest
php artisan test --filter=SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest
php artisan test --filter=SabreRevalidationOnlyProbeCanonicalDiagnosticsPersistenceCorrectionPhaseTest
php artisan test --filter=SabreGdsLiveRevalidationOnlyProbeTest::test_success_with_valid_linkage
php artisan sabre:gds-scenario-revalidation-diagnostic --linkage-fixture=tests/Fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json
```

## SFTP upload
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`

## After deploy
One fresh QR revalidation-only probe is **recommended** to confirm live `3a1d9200-…` class outcomes (`exact_segment_signature_match_count=1`, fare basis complete).

## Final status
Implementation complete; targeted and regression tests **PASS** (phase 4: 6/6 after cabin fixture tweak).
