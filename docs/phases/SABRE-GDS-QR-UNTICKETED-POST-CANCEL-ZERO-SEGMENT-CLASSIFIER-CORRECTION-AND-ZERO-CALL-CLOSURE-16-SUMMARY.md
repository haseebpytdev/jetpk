# SABRE-GDS-QR-UNTICKETED-POST-CANCEL-ZERO-SEGMENT-CLASSIFIER-CORRECTION-AND-ZERO-CALL-CLOSURE-16

## Objective
Correct zero-segment post-cancel classification after Phase 15 production retrieve; zero-call replay + local-only closure for booking id=3. **No supplier calls in this phase.**

## Classifier defect (audit)
1. `assessFromPreview()` treated `synced=false` / `reason_code=unmappable` as transport failure even when `http_status=200` and `segment_count=0`.
2. `prior_cancellation_confirmed` was not passed into assessment; only `synced===true` could confirm empty itineraries.
3. Missing locators in emptied PNR responses are orthogonal to segment counts; unmappable preview does not invalidate zero-segment evidence.
4. `safe_to_map_preview=false` reflects mapper eligibility, not HTTP failure.
5. Conflation: zero segments + confirmed prior cancel was lumped with “empty without proof” via predicate at `assessFromPreview` lines 92–100 (`retrieve_failed_or_empty_without_cancel_proof`).
6. Exact predicate: `rows===[] && candidateCount===0` AND (`isset(error)` OR `synced===false`) without prior-cancel closure context.

## Corrected rule
`SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier` confirms closure when prior Phase 14 evidence + identity gates pass, retrieve evidence shows HTTP 200, `segment_count=0`, `mappable_segment_count=0`, `resource_unavailable_present=false`, no active segments, and ticketing/unticketed gates hold. Locator fields absent in response do not block.

## Commands

**Dry-run replay (zero supplier calls, zero DB mutation):**
```bash
php artisan sabre:gds-qr-unticketed-post-cancel-replay \
  --booking-id=3 \
  --supplier-booking-id=2 \
  --prior-cancellation-lifecycle-run-id=5f265d7f-834f-4f4b-8376-4df358a4e9d7 \
  --post-cancel-retrieve-lifecycle-run-id=019da711-5074-4bb6-8558-43485975be89 \
  --retrieve-attempt-id=9 \
  --dry-run
```

**Local-only closure (production, confirmation tokens, no supplier HTTP):**
```bash
php artisan sabre:gds-qr-unticketed-post-cancel-replay \
  --booking-id=3 \
  --supplier-booking-id=2 \
  --prior-cancellation-lifecycle-run-id=5f265d7f-834f-4f4b-8376-4df358a4e9d7 \
  --post-cancel-retrieve-lifecycle-run-id=019da711-5074-4bb6-8558-43485975be89 \
  --retrieve-attempt-id=9 \
  --apply-local-closure \
  --confirm-local-closure=APPROVE-LOCAL-SABRE-GDS-POST-CANCEL-ZERO-SEGMENT-CLOSURE \
  --confirm-replay-booking=CONFIRM-SABRE-GDS-REPLAY-CLOSURE-BOOKING-3
```

## Files changed
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelZeroSegmentClosureClassifier.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelReplayEvidenceLoader.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelReplayLifecycle.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelRetrieveAttemptCorrectionService.php` (new)
- `app/Console/Commands/SabreGdsQrUnticketedPostCancelReplayCommand.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment.php`
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelRetrieveLifecycle.php`
- `tests/Unit/SabreGdsQrUnticketedPostCancelZeroSegmentPhase16Test.php` (new)
- `tests/Unit/SabreGdsQrUnticketedPostCancelRetrievePhase15Test.php`

## Tests
```bash
php -d memory_limit=1G artisan test tests/Unit/SabreGdsQrUnticketedPostCancelZeroSegmentPhase16Test.php tests/Unit/SabreGdsQrUnticketedPostCancelRetrievePhase15Test.php tests/Unit/SabreGdsQrUnticketedCancelProductionReadinessPhase14Test.php tests/Unit/SabreGdsQrUnticketedPostRevalidationHandoffPhase13Test.php
```
39 passed.

## Attempt id=9
On apply: same row updated `needs_review`/`unmappable` → `success`; safe_summary preserved with Phase 16 correction keys.

## Manual duplicate recommendations (unchanged)
- Attempt 4 → superseded_duplicate of 5
- Attempt 8 → superseded_duplicate of 7

## Final status
Ready for dry-run on production; apply only after dry-run shows `retrieve_confirmed`. **No further supplier retrieve or cancellation authorized.**
