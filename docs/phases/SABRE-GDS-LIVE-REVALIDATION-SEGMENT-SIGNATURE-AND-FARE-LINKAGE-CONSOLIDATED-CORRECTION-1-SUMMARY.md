# SABRE-GDS-LIVE-REVALIDATION-SEGMENT-SIGNATURE-AND-FARE-LINKAGE-CONSOLIDATED-CORRECTION-1

## Phase name
SABRE-GDS-LIVE-REVALIDATION-SEGMENT-SIGNATURE-AND-FARE-LINKAGE-CONSOLIDATED-CORRECTION-1

## Branch name
`claude/sabre-gds-live-revalidation-segment-signature-and-fare-linkage-consolidated-correction-1` (from `claude/ui-master`)

## Objective
Unify canonical segment-signature normalization across shop evidence, revalidation draft context, and BFM response candidate linkage without weakening exact linkage gates.

## Included scope
- Shared `SabreGdsRevalidationCanonicalSegmentSignature` for schedule identity hashing.
- Linker alignment: flight-number normalization, nested carrier shapes, route-keyed fare-component merge, structural flight-number enforcement.
- Safe linkage normalization diagnostics on failed linkage.
- Scenario runner artifact `chmod 0600` on write.
- Unit tests for QR/PK/EY-shaped fixtures and prior replay contract.

## Excluded scope
- Live Sabre HTTP calls, PNR, cancellation, ticketing.
- GF / multicity live revalidation or PNR.
- Gate loosening (price-only, index-0, cheapest candidate, partial itinerary).

## Investigation findings (production-shaped)
| Route | Normalized mismatch (shop/draft vs BFM candidate) |
|-------|-----------------------------------------------------|
| QR LHE-DOH-JED | `departure_wall_clock` / `arrival_wall_clock`: draft ISO `2026-09-01T02:15:00` hashed with calendar anchor while `scheduleDescs` used clock-only `02:15:00`; `flight_number`: unpadded `615` vs zero-padded `0615` in live-shaped responses. |
| PK LHE-KHI | Same datetime asymmetry; `flight_number` unpadded `301` vs `0301`. |
| EY LHE-AUH-JED | Datetime asymmetry (mixed `dateTime` vs `time`); integer vs string `marketingFlightNumber`; scalar vs nested `carrier.marketing` object. |

Prior linker vs evidence asymmetry: different signature field sets (fare basis/cabin vs operating carrier; time-only vs full ISO), so plan continuity digest could disagree with draft linkage even before response compare.

## Root cause
Three divergent segment-signature builders and non-canonical schedule field extraction caused `exact_segment_signature_match_count=0` while route/carrier/booking-class gates still passed (`structurally_eligible_candidate_count=1`).

## Files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php` (new)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationLinkageNormalizationDiagnostics.php` (new)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioExactOfferEvidence.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php`
- `tests/Unit/SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest.php` (new)
- `tests/Unit/SabreRevalidationBfmApplicationWarningAndResponseLinkageCorrectionPhaseTest.php`
- `docs/phases/SABRE-GDS-LIVE-REVALIDATION-SEGMENT-SIGNATURE-AND-FARE-LINKAGE-CONSOLIDATED-CORRECTION-1-SUMMARY.md`

## Tests executed
- `php artisan test --filter=SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest` (10 passed)
- `php artisan test --filter=SabreRevalidationBfmApplicationWarningAndResponseLinkageCorrectionPhaseTest` (11 passed)
- `php artisan test --filter=SabreGdsLiveScenarioExactOfferEvidenceTest` (7 passed)

## Zero-call replay verification
```bash
php artisan sabre:gds-scenario-revalidation-diagnostic --linkage-fixture=tests/Fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json
```
Expect `usable_fare_linkage=true`, `unique_usable_linkage_match_count=1`, `selected_response_candidate_ordinal=2`, no supplier HTTP.

## SFTP upload paths
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationLinkageNormalizationDiagnostics.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationResponseCandidateLinker.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioExactOfferEvidence.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php`

## Fresh live QR revalidation-to-PNR readiness
After deploy and zero-call replay green: one controlled live QR revalidation-only probe is **ready to attempt** (linkage normalization aligned); full revalidation-to-PNR still requires production approval and remains out of scope for this phase.

## Final status
Implementation complete locally; no live supplier calls in this phase.
