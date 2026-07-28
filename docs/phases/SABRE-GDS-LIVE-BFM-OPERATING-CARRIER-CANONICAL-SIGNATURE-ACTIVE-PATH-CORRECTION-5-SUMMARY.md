# SABRE-GDS-LIVE-BFM-OPERATING-CARRIER-CANONICAL-SIGNATURE-ACTIVE-PATH-CORRECTION-5

## Objective
Ensure active response-candidate segment signatures use the same canonical operating-carrier slot as selected/draft (absent ≡ same-as-marketing).

## Root cause
- `operating_carrier_shape_categories` on artifacts described **selected/draft only**; response candidates could still hash with a non-empty operating slot when raw BFM `carrier.operating` was present but semantically same-as-marketing.
- Signature parts were built directly from heterogeneous segment arrays (including nested `carrier` objects and explicit `operating_carrier` keys) instead of a single canonical identity row.
- Candidate segment rows were not re-normalized after fare overlay, so stale/raw operating fields could influence `rawOperatingCarrierCode()` while diagnostics showed selected-side `absent`.

## Correction
- `canonicalScheduleIdentityRow()` / `canonicalScheduleIdentityRows()` — sole source for hash parts, digests, and comparisons.
- `signaturePartsFromSegments()` reads `canonical_operating_carrier_slot` only from identity rows.
- `rawOperatingCarrierCode()` collapses same-as-marketing; shape diagnostics use pre-collapse probe on schedules.
- `segmentRowFromScheduleDesc()` clears equivalent operating, records shape category, strips raw operating keys when slot empty.
- `resolveCandidateSegments()` / `buildSelectedContextFromDraft()` finalize with `canonicalScheduleIdentityRows()` **after** fare overlay.
- Post-response diagnostics: `candidate_operating_carrier_shape_categories`, `candidate_canonical_operating_carrier_slots`.

## Files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioExactOfferEvidence.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `tests/Unit/SabreGdsLiveBfmOperatingCarrierCanonicalSignatureActivePathCorrectionPhaseTest.php`

## Verification (zero-call)
```bash
php artisan test --filter=SabreGdsLiveBfmOperatingCarrierCanonicalSignatureActivePathCorrectionPhaseTest
php artisan test --filter=SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest
php artisan test --filter=SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest
```

## After deploy
One fresh QR revalidation-only probe recommended to confirm `exact_segment_signature_match_count=1` on live-shaped runs (e.g. post `fbfa21e8-…`).

## SFTP upload
Same five `app/` paths as above (excluding tests).
