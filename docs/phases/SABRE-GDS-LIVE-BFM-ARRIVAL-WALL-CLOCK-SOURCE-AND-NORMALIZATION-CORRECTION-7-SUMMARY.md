# SABRE-GDS-LIVE-BFM-ARRIVAL-WALL-CLOCK-SOURCE-AND-NORMALIZATION-CORRECTION-7

## Objective
Fix segment-1 `arrival_wall_clock` tuple mismatch on live QR LHE-DOH (QR 629) by using authoritative BFM `scheduleDesc` local `time` for wall-clock identity.

## Root cause
`segmentRowFromScheduleDesc()` preferred `arrival.dateTime` over `arrival.time`. Live BFM can supply **both**: local airport `time` (`15:05:00`) and a **UTC-shifted** `dateTime` (e.g. `12:05:00Z`). Phase 6 tuple hashing used the wrong field → candidate arrival wall clock `12:05` vs selected/draft `15:05`. Segment 2 matched because its schedule row did not expose the same conflicting pair.

## Correction
- `scheduleEndpointClockRaw()` — prefer `endpoint.time`, fallback `dateTime`/`date`.
- `segmentRowFromScheduleDesc()` uses endpoint readers; stores `*_clock_source_shape`, `*_date_adjustment_days`, `schedule_desc_ref` (diagnostics only, not in hash tuple).
- `comparableWallClock()` hardened for `HH:MM:SS`, compact `HHMM`, and offset-stripped ISO literals (literal clock preserved, no timezone conversion).
- Persist `selected_canonical_hash_tuple_values`, `draft_canonical_hash_tuple_values`, `candidate_canonical_hash_tuple_values`, `candidate_tuple_field_comparisons`.

## Files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `tests/Unit/SabreGdsLiveBfmArrivalWallClockSourceAndNormalizationCorrectionPhaseTest.php`

## Tests (zero-call)
```bash
php artisan test --filter=SabreGdsLiveBfmArrivalWallClockSourceAndNormalizationCorrectionPhaseTest
php artisan test --filter=SabreGdsCanonicalSegmentRowSchemaAndHashTupleCorrectionPhaseTest
php artisan test --filter=SabreGdsLiveBfmOperatingCarrierCanonicalSignatureActivePathCorrectionPhaseTest
php artisan test --filter=SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest
php artisan test --filter=SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest
```

## SFTP upload
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`

## Post-deploy
One fresh QR revalidation-only probe recommended (`run_id` successor to `fde4be31-…`) to confirm segment-1 tuple digest alignment and `exact_segment_signature_match_count=1`.

## Final status
PASS (local).
