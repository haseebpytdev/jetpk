# SABRE-GDS-CANONICAL-SEGMENT-ROW-SCHEMA-AND-HASH-TUPLE-CORRECTION-6

## Phase name
SABRE-GDS-CANONICAL-SEGMENT-ROW-SCHEMA-AND-HASH-TUPLE-CORRECTION-6

## Branch name
*(create from `claude/ui-master` before commit — not merged in this pass)*

## Objective
Eliminate false `operating_carrier` linkage mismatches when canonical operating slots already match by defining one strict positional hash tuple shared by selected, draft, and response candidates.

## Included scope
- Fixed 7-field schedule hash tuple (no booking class / fare basis in schedule identity digest).
- `hashFromSegments()` digests JSON `{ canonical_signature_version, tuple_schema_version, segment_tuples }` only.
- Mismatch categories derived from tuple field-by-field comparison (not pipe-split legacy parts).
- Tuple diagnostics persistence: schema version, field count, per-segment tuple digests (selected/draft/candidate), `candidate_tuple_mismatch_field_names`.
- Phase 6 unit tests + zero-call regression suite.

## Excluded scope
- Live Sabre HTTP, PNR, cancellation, ticketing.
- Carrier equivalence rule changes (Phase 4–5 semantics preserved).
- Linkage gate weakening.

## Investigation findings
- Live probe `3cc23960-6e57-490e-8e95-5f345e548325`: shop/draft digest `7ae68e41…`, candidate `e3bb67c5…`, only category `operating_carrier` while slots/shapes all equivalent (`""` / absent / same_as_marketing).
- Fare basis and booking class gates already green (`per_segment_present=[true,true]`).

## Root causes
1. **Legacy pipe signature parts** embedded wall-clock as `|HH:MM` inside a pipe-delimited string; `mismatchCategoryForPart()` used `explode('|', …)`, splitting wall-clock segments and **mis-aligning field indices** → spurious `operating_carrier` category even when canonical slots matched.
2. **Hash payload** mixed ordinal/booking-class into pipe parts and used `ksort` on associative wrappers — optional keys and key order could diverge across sources despite identical schedule identity.
3. **Mismatch classifier** did not always compare the same fixed tuple values used for the digest.

## Before correction (conceptual)
- **Selected/draft hash input:** identity rows with empty `canonical_operating_carrier_slot`, ISO datetimes, no raw operating key.
- **Candidate hash input:** same identity slots after Phase 5 normalization, but digest built from **different serialization** (pipe string + extra fields) than mismatch logic assumed.
- **Structural delta:** pipe-delimited part string ≠ tuple comparison; wall-clock `|` tokens shifted mismatch index 3 (operating slot) vs actual tuple index 3.

## Fixed tuple schema
- **Version:** `sabre_gds_revalidation_canonical_segment_hash_tuple_v1`
- **Signature version:** `sabre_gds_revalidation_canonical_segment_signature_v3`
- **Field count:** 7 (fixed order, always present, `""` for missing text):
  1. `route_origin`
  2. `route_destination`
  3. `marketing_carrier`
  4. `canonical_operating_carrier_slot`
  5. `normalized_flight_number`
  6. `departure_wall_clock`
  7. `arrival_wall_clock`

## Files changed
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`
- `tests/Unit/SabreGdsCanonicalSegmentRowSchemaAndHashTupleCorrectionPhaseTest.php`
- `docs/phases/SABRE-GDS-CANONICAL-SEGMENT-ROW-SCHEMA-AND-HASH-TUPLE-CORRECTION-6-SUMMARY.md`

## Routes changed
None.

## Database changes
None.

## Backend changes
- Tuple builders `scheduleHashTupleFromSegment()`, `scheduleHashTuplesFromSegments()`, `scheduleHashTupleDigest()`, `scheduleHashTupleSegmentDigests()`.
- `safeLinkageDigestComparison()` uses tuple labels + `mismatchCategoryForTupleField()`.
- `signaturePartsFromSegments()` uses unit separator `\x1f` (display/digest helper only).
- Runtime propagation + mapper flat keys for tuple diagnostics.

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter=SabreGdsCanonicalSegmentRowSchemaAndHashTupleCorrectionPhaseTest
php artisan test --filter=SabreGdsLiveBfmOperatingCarrierCanonicalSignatureActivePathCorrectionPhaseTest
php artisan test --filter=SabreGdsLiveBfmOperatingCarrierAndFareBasisApplicabilityCorrectionPhaseTest
php artisan test --filter=SabreRevalidationSegmentSignatureAndFareLinkageConsolidatedCorrectionPhaseTest
php artisan test --filter=SabreRevalidationOnlyProbeCanonicalDiagnosticsPersistenceCorrectionPhaseTest
php artisan test --filter=SabreGdsLiveRevalidationOnlyProbeTest::test_success_with_valid_linkage
```

## Assertion counts
- Phase 6: 11 tests, 30 assertions — **pass**
- Phase 5: 5/5
- Phase 4: 6/6
- Consolidated linkage: 10/10
- Probe canonical persistence: 5/5
- Probe success linkage: 1/1

## Screenshots
N/A (backend-only).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Schedule identity hash intentionally excludes booking class, fare basis, cabin, equipment (gated separately).
- `signaturePartsFromSegments()` retained for diagnostics digests; authoritative linkage digest is tuple-based.

## Risks
- Deploy changes digest version (`v3`); stale artifacts comparing old digests to new code will not match until re-run.
- Low: genuine codeshare and route/time/carrier mismatches still fail closed.

## Rollback instructions
Revert the four `app/` files above to Phase 5 commit; re-run regression filters. No migrations.

## Commit SHA
*(pending user-requested commit)*

## Final status
**PASS** (local zero-call verification). Recommend **one fresh QR revalidation-only probe** after deploy to confirm live artifact: `exact_segment_signature_match_count=1`, empty `candidate_mismatch_categories`, matching per-segment tuple digests, `usable_fare_linkage=true`.

## SFTP upload (`app/` only)
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`

## Server SSH clears
```bash
php artisan optimize:clear
```

## Zero-call verification
No `sabre:*` live commands run in this phase; PHPUnit only.
