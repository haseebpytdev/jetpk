# SABRE-GDS-QR-UNTICKETED-POST-CANCEL-RETRIEVE-AND-CANCELLATION-PERSISTENCE-CLOSURE-15

## Phase name
SABRE-GDS-QR-UNTICKETED-POST-CANCEL-RETRIEVE-AND-CANCELLATION-PERSISTENCE-CLOSURE-15

## Branch name
(To be created from `claude/ui-master` per workflow.)

## Objective
Post-cancel retrieve for booking id=3 after confirmed Phase 14 cancellation; fix local cancellation persistence and duplicate cancel-attempt rows on future runs. **No live Sabre calls in this coding phase.**

## Included scope
- `sabre:gds-qr-unticketed-post-cancel-retrieve` command (plan + gated send)
- Prior cancellation evidence gate (Phase 14 lifecycle artifact / meta / successful `cancel_pnr`)
- One retrieve max; ambiguity → manual reconciliation, no retry
- Atomic local closure via reconciliation + `cancellation_status` after zero active segments
- Future cancel: `defer_local_cancellation_closure` + `skip_booking_cancel_service_attempt_row`
- Default cancel path: reconciliation after verified cancel (non-deferred)

## Excluded scope
- Live retrieve/cancel on production in this commit
- Rewriting supplier booking attempts 4/5/7/8
- Refund/void/ticketing/AirTicket

## Investigation findings (persistence audit)
1. **`SabreGdsCancelService::persistCancelMeta()`** only writes `meta.sabre_gds_cancel`; it does not set `bookings.status`, `cancellation_status`, `cancelled_at`, or `supplier_bookings.status`.
2. **`SabreGdsCancellationReconciliationService`** performs full local closure but was **not** invoked from the QR cancel lifecycle after verified cancel.
3. **`skip_post_cancel_retrieve`** only skips `postCancelSync()`; it does not skip column updates (those were never applied in `persistCancelMeta`).
4. Phase 14 artifact **`supplier_booking_status_after`** reads `bookings.supplier_booking_status`, not `supplier_bookings.status` → explains `pending_payment_or_ticketing` vs row `pending_ticketing`.
5. Expected persister for canonical local state: **`SabreGdsCancellationReconciliationService`** (now also called from `finalizeVerifiedCancel` when not deferred).
6. QR Phase 14 defers local closure until Phase 15 retrieve confirms zero active segments.

## Duplicate attempt root cause
- Phase 14 recorder: `cancel_pnr` **started → success** (id=7).
- `SabreGdsCancelService` skipped internal `cancel_booking` in-progress rows when flagged.
- **`SabreBookingCancelService::recordCancelAttempt()`** still inserted `cancel_booking` success (id=8) on HTTP success.
- Fix: `skip_booking_cancel_service_attempt_row` in execution context.

## Files changed
- `app/Console/Commands/SabreGdsQrUnticketedPostCancelRetrieveCommand.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelRetrieveLifecycle.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelRetrieveIdentityResolver.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelPriorCancellationGate.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedPostCancelLocalClosureService.php` (new)
- `app/Support/Sabre/Scenario/SabreGdsQrUnticketedCancelLifecycle.php` (defer closure + skip booking cancel attempt row)
- `app/Services/Suppliers/Sabre/Cancel/SabreGdsCancelService.php` (reconcile when not deferred)
- `app/Services/Suppliers/Sabre/Cancel/SabreBookingCancelService.php` (skip duplicate attempt row)
- `app/Services/Suppliers/Sabre/Cancel/SabreGdsCancellationReconciliationService.php` (`cancellation_status`)
- `tests/Unit/SabreGdsQrUnticketedPostCancelRetrievePhase15Test.php` (new)

## Tests executed
```bash
php -d memory_limit=1G artisan test tests/Unit/SabreGdsQrUnticketedPostCancelRetrievePhase15Test.php tests/Unit/SabreGdsQrUnticketedCancelProductionReadinessPhase14Test.php tests/Unit/SabreGdsQrUnticketedPostRevalidationHandoffPhase13Test.php
```
13 + 7 + 8 = 28 tests (Phase 15 file: 13 assertions).

## Zero-call production plan
```bash
php artisan sabre:gds-qr-unticketed-post-cancel-retrieve \
  --booking-id=3 \
  --supplier-booking-id=2 \
  --prior-cancellation-lifecycle-run-id=5f265d7f-834f-4f4b-8376-4df358a4e9d7 \
  --plan
```

## One-call retrieve (operator; production only)
```bash
php artisan sabre:gds-qr-unticketed-post-cancel-retrieve \
  --booking-id=3 \
  --supplier-booking-id=2 \
  --prior-cancellation-lifecycle-run-id=5f265d7f-834f-4f4b-8376-4df358a4e9d7 \
  --send \
  --confirm-production=APPROVE-LIVE-SABRE-GDS-POST-CANCEL-RETRIEVE \
  --confirm-retrieve=LIVE-SABRE-GDS-RETRIEVE-ONE-CANCELLED-PNR \
  --confirm-no-ticketing=CONFIRM-SABRE-TICKETING-DISABLED
```

## Stop conditions
- Retrieve ambiguous → `manual_reconciliation_required=true`; do not mark full local closure.
- Active air segments after retrieve → no cancellation closure.
- No second cancellation or retrieve retry.

## Canonical local cancellation states
- `bookings.status` = `cancelled`
- `bookings.cancellation_status` = `cancelled`
- `bookings.cancelled_at` populated; PNR retained
- `bookings.supplier_booking_status` = `cancelled`
- `supplier_bookings.status` = `cancelled`

## Manual attempt cleanup (zero-call)
- **7 vs 8:** Mark attempt **7** (`cancel_pnr`) as canonical supplier cancellation; mark **8** (`cancel_booking`) `superseded_duplicate` with `duplicate_of_attempt_id=7` (preserve both).
- **4 vs 5:** Mark **4** `superseded_duplicate` / `needs_review` with `duplicate_of_attempt_id=5`.

## Commit SHA
(Pending user-requested commit.)

## Final status
Implementation and unit tests complete; **no live Sabre call in this phase**.
