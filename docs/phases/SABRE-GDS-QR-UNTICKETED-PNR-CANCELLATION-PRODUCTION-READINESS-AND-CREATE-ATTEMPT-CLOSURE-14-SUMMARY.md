# SABRE-GDS-QR-UNTICKETED-PNR-CANCELLATION-PRODUCTION-READINESS-AND-CREATE-ATTEMPT-CLOSURE-14

## Objective
Prepare controlled production cancellation for booking id=3 (plan-only in this phase) and close create_pnr attempt duplication (started row + separate success row).

## Create-attempt root cause
Phase 13 introduced `SabreGdsQrUnticketedSupplierCreateAttemptRecorder::recordStarted()` before PNR dispatch (attempt id=4), but `SabreBookingService::finalizePublicCheckoutSabreStorage()` still always inserted a new `create_pnr` success row on `pending_payment_or_ticketing` (attempt id=5).

## Create-attempt correction
- Pass `qr_unticketed_pre_dispatched_attempt_id` through scenario runner → `createBookingForScenarioRunner` → checkout result.
- On success, `completeFromCheckoutResult()` updates the same attempt row (`started` → `success`, `completed_at`, `supplier_reference`).

## Historical attempt id=4 recommendation (zero-call, manual)
Do not delete attempt id=5. Mark attempt id=4 `status=superseded_duplicate` or `needs_review` with `safe_summary.duplicate_of_attempt_id=5`, `safe_summary.closure_note=qr_unticketed_phase14_pre_dispatch_row`, retain audit trail.

## Cancellation command
`sabre:gds-qr-unticketed-cancel`

Options: `--booking-id`, `--supplier-booking-id=2`, `--lifecycle-run-id`, `--plan`, `--send`, `--confirm-production`, `--confirm-cancellation`, `--confirm-no_ticketing`.

Locator resolved only from Booking → SupplierBooking (no raw locator CLI input).

Post-cancel retrieve deferred to Phase 15 (`skip_post_cancel_retrieve` on send).

## Files changed
- `app/Console/Commands/SabreGdsQrUnticketedCancelCommand.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedCancelLifecycle.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedCancelIdentityResolver.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedSupplierCancelAttemptRecorder.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedSupplierCreateAttemptRecorder.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRunnerPnrExecutor.php`
- `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php`
- `app/Services/Suppliers/Sabre/Cancel/SabreGdsCancelService.php`
- `tests/Unit/SabreGdsQrUnticketedCancelProductionReadinessPhase14Test.php` (new)

## Tests
`php -d memory_limit=1G artisan test tests/Unit/SabreGdsQrUnticketedCancelProductionReadinessPhase14Test.php tests/Unit/SabreGdsQrUnticketedPostRevalidationHandoffPhase13Test.php`

## Final status
Ready for operator plan verification; live cancel send requires production tokens and booking id=3 only.
