# SABRE-GDS-QR-UNTICKETED-BOOK-AND-RETRIEVE-POST-REVALIDATION-OFFER-HANDOFF-AND-PERSISTENCE-ORDER-CORRECTION-13

## Objective
Fix post-revalidation `offer_validation_failed` after successful revalidation in the QR unticketed lifecycle; attach authoritative revalidated offer context; gate local Booking creation after pre-create validation; private lifecycle artifacts at mode 0600.

## Root cause (production run `4addc6ef-6bdc-4a3c-9138-bedb9b69ab9c`)
1. `SabreGdsLiveScenarioRunnerPnrExecutor` always called `SabreGdsLiveScenarioRevalidationGate::revalidateForBooking()` after a local Booking was created.
2. `SabreGdsRevalidationService::resolveOfferFromBooking()` only read `meta.offer_snapshot` / `sabre_booking_context.offer`, not `normalized_offer_snapshot` written by the scenario booking factory.
3. Empty offer → `validateNormalizedSabreOffer()` failed → `reason_code=offer_validation_failed`.
4. `blockedResult()` merged full revalidation evidence after `reason_code`, overwriting the block reason with `offer_validation_failed` while `booking_created=true`.

## Predicate / field
- **Method:** `SabreGdsRevalidationService::revalidateForBooking()` lines 55–62.
- **Failed predicate:** `validateNormalizedSabreOffer($offer)->success`.
- **Input path:** resolved offer from booking meta (empty).
- **Expected:** non-empty Sabre normalized offer with `supplier_offer_id`, segments, priced fare.
- **Actual:** `[]` (missing alias keys).
- **Source:** stale/wrong object — booking meta snapshot keys not read; not revalidated candidate.

## Corrections
- Authoritative revalidated booking context built after successful scenario revalidation.
- Final offer validator runs **before** local Booking insert on `lifecycle_dedicated` runs.
- PNR executor skips redundant revalidation when authoritative context is present; sanitizes merged evidence on block.
- `resolveOfferFromBooking()` falls back to `normalized_offer_snapshot` chain.
- Lifecycle artifacts written via atomic private writer (`0700` dir, `0600` file on Unix).

## Files changed
- `app/Support/Sabre/Scenario/SabreGdsAuthoritativeRevalidatedBookingContext.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsAuthoritativeRevalidatedBookingContextBuilder.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsPrivateLifecycleArtifactWriter.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostRevalidationFinalOfferValidator.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedSupplierCreateAttemptRecorder.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff.php`
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedBookAndRetrieveLifecycle.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunner.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunnerBookingFactory.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunnerPnrExecutor.php`
- `app/Services/Suppliers/Sabre/Gds/SabreGdsRevalidationService.php`
- `tests/Unit/SabreGdsQrUnticketedPostRevalidationHandoffPhase13Test.php` (new)

## Tests
`php artisan test tests/Unit/SabreGdsQrUnticketedPostRevalidationHandoffPhase13Test.php tests/Unit/SabreGdsQrUnticketedBookAndRetrieveProductionReadinessPhaseTest.php`

## Booking id=2 recommendation (no auto change)
- Mark `status=failed` or `validation_failed` with meta `lifecycle_run_id=1627aed5-aa67-4cde-91b8-6067c48f3b7c`, `safe_reason_code=offer_validation_failed`, `pre_create_validation_skipped=true`.
- Exclude from customer confirmed views; retain audit history.

## Live retry after deploy
One new controlled QR book-and-retrieve `--send` attempt is warranted after deploy and zero-call replay verification, with new `lifecycle_run_id` (do not reuse `1627aed5-…`).

## Final status
Implementation complete locally; commit/deploy pending operator workflow.
