# Phase SABRE-LIVE-SCENARIO-PLAN-EXACT-OFFER-EVIDENCE-1 — Summary

## Phase name
SABRE-LIVE-SCENARIO-PLAN-EXACT-OFFER-EVIDENCE-1

## Branch name
*(set when committing — not committed in this pass)*

## Objective
Expose sanitized exact-offer price and fingerprint evidence in plan-mode candidate summaries; enforce book-mode continuity between fresh shop selection, fingerprint, and production revalidation before Booking creation.

## Included scope
- `SabreGdsLiveScenarioExactOfferEvidence` — deterministic SHA-256 fingerprints and plan evidence fields
- Plan candidate summaries extended with `selected_total`, `currency`, `safe_offer_fingerprint`, `offer_identifier_present`, `offer_source`, `shop_timestamp`, `revalidation_linkage_ready`, schedule bounds, booking classes
- Plan output top-level `selected_candidate_index`, `selected_candidate`, and selected-offer evidence mirroring fare-pick
- Book-mode gates: `exact_offer_linkage_unavailable`, `exact_offer_fingerprint_mismatch` before Booking/revalidation
- Revalidation gate continuity: `selected_currency`, `revalidated_currency`, fingerprint passthrough
- Feature tests for plan evidence, linkage blocks, fingerprint mismatch blocks

## Excluded scope
- No live PNR create, cancel, or ticketing in this phase
- No generic migrations
- No Sabre cancellation flag changes
- No JetPK OTP patch changes

## Investigation findings
- Production plan run `504b0dd9-8cae-4342-8ac3-de2c2cd05256` returned 8 eligible QR connecting offers but candidate JSON lacked price/fingerprint continuity fields
- `buildPlanSummaries()` delegated only to routing/strategy diagnostics; fare totals existed on internal `row` but were not surfaced
- Book path selected offers without proving fingerprint continuity into revalidation

## Root causes
1. Plan diagnostics focused on strategy/routing only; exact-offer economics not copied into safe summaries
2. No deterministic cross-step fingerprint shared between plan selection and book/revalidation
3. Revalidation gate lacked currency + fingerprint continuity metadata

## Files changed
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioExactOfferEvidence.php` *(new)*
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioOfferCatalog.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioPlanCandidateDiagnostics.php` *(unchanged — evidence merged in catalog)*
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationGate.php`
- `app/Console/Commands/SabreGdsLiveScenarioRunnerCommand.php`
- `tests/Feature/SabreGdsLiveScenarioPlanExactOfferEvidenceTest.php` *(new)*
- `tests/Support/Sabre/AlwaysSuccessfulScenarioRevalidationGate.php`
- `tests/Support/Sabre/BlockingScenarioRevalidationGate.php`
- `docs/phases/SABRE-LIVE-SCENARIO-PLAN-EXACT-OFFER-EVIDENCE-1-SUMMARY.md` *(new)*

## Routes changed
None

## Database changes
None

## Backend changes
- Shop search records `shop_captured_at` ISO timestamp
- Fingerprints hash normalized itinerary + fare context (SHA-256); raw offer tokens never persisted
- Book mode blocks when `offer_identifier_present=false`, `revalidation_linkage_ready=false`, or fingerprint mismatch

## Frontend changes
None

## Tests executed
```bash
php artisan test --filter="SabreGdsLiveScenarioPlanExactOfferEvidenceTest|SabreGdsLifecycleClosurePhaseTest|SabreGdsLiveScenarioRunnerTest"
```
**Result:** 41 passed, 211 assertions

## Assertion counts (new)
- Plan: `selected_total`, `currency`, 64-char fingerprint on candidates + selected candidate
- Plan: no raw offer tokens in persisted JSON
- Plan: zero bookings/attempts
- Book: fingerprint passed to revalidation gate matches selected candidate
- Book: linkage/fingerprint failures block before Booking with safe reason codes

## Production verification (after deploy)

**Plan only (read-only):**
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

Confirm in `output_json_path`:
- `selected_total` and `selected_currency` populated
- `selected_offer_fingerprint` is 64-char hex
- `offer_identifier_present=true`
- `revalidation_linkage_ready=true`
- At least one candidate in `candidates[]` has matching evidence fields
- `bookings_created=0`, no PNR fields

Confirm DB baseline unchanged (no new Booking/SupplierBooking rows).

**Only after plan passes:** authorize one `book-retrieve-and-cancel` run per END-TO-END-CLOSURE-2 runbook.

## Known limitations
- Fingerprints are deterministic for continuity proof, not a security secret
- BFM index-only offers require `bfm_gds_priced_itinerary` policy for `offer_identifier_present`

## Risks
- Low: stricter book-mode gate may block offers that previously proceeded without linkage evidence (intended)

## Rollback
Revert listed files; no schema rollback.

## Commit SHA
*(pending user commit)*

## Final status
**PASS** (41 tests). Production plan re-run pending after deploy.
