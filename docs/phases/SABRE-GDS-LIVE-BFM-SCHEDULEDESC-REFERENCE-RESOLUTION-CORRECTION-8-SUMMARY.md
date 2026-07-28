# SABRE-GDS-LIVE-BFM-SCHEDULEDESC-REFERENCE-RESOLUTION-CORRECTION-8

## Root cause
`indexDescs()` indexed each scheduleDesc under a **single** key (`ref` OR `id`), while Sabre leg schedule slots may reference the **other** numeric key. With duplicate LHE–DOH QR 629 rows (13:05 vs 15:05), a reference by `id` could resolve to the wrong row or the decoy first-listed schedule when the map was incomplete.

## Correction
- `SabreGdsRevalidationGirDescriptorResolver` — dual `id`/`ref` lookup map, ambiguous-key fail-closed, **no** array-position / ref−1 fallback.
- `resolveCandidateSegments()` — authoritative leg/schedule ref extraction; fail-closed on unresolved leg/schedule.
- Fare overlay index map and `indexDescs()` aligned to the same resolver.
- Removed one-based segment index fallback in fare applicability; route fallback only when **unique**.
- `candidate_schedule_descriptor_resolution` diagnostics persisted.

## SFTP
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationGirDescriptorResolver.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`

## Verification
```bash
php artisan test --filter=SabreGdsLiveBfmScheduleDescReferenceResolutionCorrectionPhaseTest
```
Plus Phase 1–7 regression filters.

## Post-deploy
One fresh QR revalidation-only probe after upload.
