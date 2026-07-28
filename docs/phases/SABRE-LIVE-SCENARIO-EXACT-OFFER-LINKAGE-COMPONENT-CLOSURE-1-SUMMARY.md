# SABRE-LIVE-SCENARIO-EXACT-OFFER-LINKAGE-COMPONENT-CLOSURE-1

## Phase name
SABRE-LIVE-SCENARIO-EXACT-OFFER-LINKAGE-COMPONENT-CLOSURE-1

## Branch name
(working branch at implementation time — not committed in this pass)

## Objective
Close exact-offer linkage component gaps exposed by production plan run `891fae75-9d69-4ee3-866f-de2b7515bdb6`: populate sanitized linkage evidence (`source_identifier_hash`, `segment_signature`, booking classes, readiness diagnostics) and enforce fail-closed continuity before Booking creation using one canonical fingerprint builder.

## Included scope
- Canonical `buildLinkageContext()` in `SabreGdsLiveScenarioExactOfferEvidence` for plan, revalidation gate, and book-mode continuity.
- Deterministic `segment_signature` from ordered segment descriptors (no raw supplier payloads).
- SHA-256 `source_identifier_hash` from itinerary ref + pricing index or other safe identifier material (never raw tokens).
- `revalidation_linkage_ready` + fixed `revalidation_linkage_missing_components` codes.
- Book-mode blocks: `exact_offer_linkage_unavailable`, `exact_offer_fingerprint_mismatch`, `exact_offer_source_identifier_mismatch`, `exact_offer_segment_signature_mismatch`.
- Plan/CLI diagnostics: presence/length flags (hashes not printed in CLI summary except approved `safe_offer_fingerprint`).
- Revalidation gate continuity alignment via `continuity_evidence` / expected hash/signature fields.
- Unit + feature tests for linkage closure acceptance criteria.

## Excluded scope
- Live PNR create/cancel/ticket runs (implementation and tests remain mocked/local).
- Generic migrations.
- Production book-retrieve-and-cancel (requires post-deploy plan verification).

## Investigation findings
Production plan run showed `offer_identifier_present=true` and valid `safe_offer_fingerprint`, but `source_identifier_hash`, `segment_signature`, and `revalidation_linkage_ready` were absent/false for all eight candidates because the prior evidence builder did not emit component fields and readiness still leaned on incomplete pricing-digest policy strings (`bfm_gds_priced_itinerary_incomplete`).

## Root causes
1. Fingerprint was computed without persisting component evidence used in the canonical payload.
2. Readiness gate did not evaluate the minimum exact-linkage contract independently.
3. Revalidation gate recomputed fingerprints from reduced row summaries instead of the canonical builder.
4. `SupplierProvider` enum cast to string broke linkage assessment in tests/runtime.
5. Empty `revalidation_linkage_missing_components` arrays were stripped by `array_filter`.

## Exact files changed
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioExactOfferEvidence.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationGate.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php` (prior pass; continuity wiring)
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioOfferCatalog.php` (prior pass; fingerprint match)
- `app/Console/Commands/SabreGdsLiveScenarioRunnerCommand.php`
- `tests/Unit/Support/Sabre/Scenario/SabreGdsLiveScenarioExactOfferEvidenceTest.php` (new)
- `tests/Feature/SabreGdsLiveScenarioExactOfferLinkageComponentClosureTest.php` (new)
- `tests/Feature/SabreGdsLiveScenarioPlanExactOfferEvidenceTest.php`
- `tests/Feature/SabreGdsLiveScenarioRunnerTest.php`
- `tests/Feature/SabreGdsLifecycleClosurePhaseTest.php`

## Routes changed
None.

## Database changes
None.

## Backend changes
- Single canonical linkage context builder with SHA-256 hashes only (no raw supplier identifiers).
- Segment signature from ordered normalized segment parts.
- Fare basis strengthens fingerprint when present; not required for BFM minimum contract.
- Booking-class resolution falls back to per-segment normalized fields.
- Revalidation gate validates source hash, segment signature, and fingerprint via canonical rebuild.

## Frontend changes
None.

## Tests executed
```bash
php artisan test --filter="SabreGdsLiveScenarioExactOfferLinkageComponentClosureTest|SabreGdsLiveScenarioExactOfferEvidenceTest|SabreGdsLiveScenarioPlanExactOfferEvidenceTest|SabreGdsLifecycleClosurePhaseTest|SabreGdsLiveScenarioRunnerTest"
```
**Result:** 53 passed, 251 assertions.

## Assertion counts
- 7 unit tests (exact-offer evidence builder)
- 5 feature tests (linkage component closure)
- 6 feature tests (plan exact-offer evidence — prior phase)
- 35 feature tests (lifecycle + scenario runner suite subset above)

## Screenshots
N/A (backend/CLI phase).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Production plan verification still required on connection `1` after deploy before authorizing one controlled book-retrieve-and-cancel run.
- Connecting multi-segment BFM offers depend on normalized segment + handoff fields being present from shop normalization.

## Risks
- If production normalization omits booking classes on some BFM shapes, linkage may still report `booking_classes_incomplete` until normalizer/handoff enrichment is aligned.

## Rollback instructions
Revert the files listed above to the pre-phase commit; no migrations to roll back.

## Commit SHA
(not committed in this pass)

## Final status
**IMPLEMENTATION_COMPLETE — awaiting production plan-only verification**

### Production plan-only command (post-deploy)
```bash
php artisan sabre:gds-live-scenario-runner \
  --mode=plan \
  --preset=qr-connecting \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --connection=1 \
  --confirm=LIVE-SABRE-GDS-SCENARIO-RUNNER \
  --production-ops-approval=APPROVE-LIVE-SABRE-GDS-SCENARIO-RUNNER
```

### Required plan evidence before book-retrieve-and-cancel
- `selected_total` set
- `selected_currency` set
- `safe_offer_fingerprint` length 64
- `source_identifier_hash_present=true`
- `segment_signature_present=true`
- booking classes complete
- `revalidation_linkage_missing_components=[]`
- `revalidation_linkage_ready=true`
- no database mutation
