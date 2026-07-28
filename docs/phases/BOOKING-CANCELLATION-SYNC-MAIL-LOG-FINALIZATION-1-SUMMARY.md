# BOOKING-CANCELLATION-SYNC-MAIL-LOG-FINALIZATION-1

## Phase name
BOOKING-CANCELLATION-SYNC-MAIL-LOG-FINALIZATION-1

## Branch name
`phase/BOOKING-CANCELLATION-SYNC-MAIL-LOG-FINALIZATION-1`

## Objective
Finalize customer `communication_logs` rows for synchronous mail delivery when `QUEUE_CONNECTION=sync`, matching the existing `OtaNotificationService` behavior and providing a safe repair path for stale `queued` rows (e.g. booking 1 `booking_cancelled`).

## Included scope
- `BookingCommunicationService::sendEmailForBooking()` and itinerary dispatch now treat `queue.default=sync` as synchronous delivery (`Mail::send`) and mark the same log row `sent` with `sent_at`.
- Synchronous mail failures update the same row to `failed` with SMTP-password-safe `error_message`.
- Asynchronous queue drivers (`database`, `redis`, etc.) still create `queued` rows and use `Mail::queue()`.
- `cancellationConfirmedAlreadySent` deduplication preserved.
- `StaleSynchronousCommunicationLogRepairService` + `ota:repair-stale-sync-communication-logs` for evidence-based repair of stale rows.
- Feature tests covering sync success/failure, async queue, duplicate cancellation guard, no supplier HTTP, and repair evidence rules.

## Excluded scope
- No resend of booking 1 cancellation email.
- No reconciliation rerun.
- No supplier HTTP calls.
- No generic migrations.
- No changes to `OtaNotificationService` (already correct for sync queue).
- No ticketing enablement.

## Investigation findings
- Production uses `QUEUE_CONNECTION=sync` and `MAIL_MAILER=smtp`.
- `OtaNotificationService::shouldQueueMail()` already sends synchronously when queue is `sync`, so `booking_status_changed` operational rows finalize as `sent`.
- `BookingCommunicationService` only checked `isImmediateMailer()` (`log`/`array`/`local`), so SMTP + sync still called `Mail::queue()` and left customer `booking_cancelled` rows stuck at `queued` with no `jobs` row.

## Root causes
- Missing sync-queue guard in `BookingCommunicationService` mail dispatch path.
- Customer booking emails and operational emails used inconsistent synchronous-delivery detection.

## Exact files changed
- `app/Services/Communication/BookingCommunicationService.php`
- `app/Services/Communication/StaleSynchronousCommunicationLogRepairService.php` (new)
- `app/Console/Commands/RepairStaleSyncCommunicationLogsCommand.php` (new)
- `tests/Feature/Communication/BookingCancellationSyncMailLogTest.php` (new)
- `tests/Feature/NotificationOperationalCoverageTest.php` (sync-queue SMTP failure mock alignment)
- `docs/phases/BOOKING-CANCELLATION-SYNC-MAIL-LOG-FINALIZATION-1-SUMMARY.md` (new)

## Routes changed
None.

## Database changes
None.

## Backend changes
- Added `shouldQueueMail()` and `safeMailError()` to `BookingCommunicationService`.
- Customer email logs now transition `queued` → `sent`/`failed` on synchronous delivery.
- Repair service corroborates stale `booking_cancelled` rows via a sent `booking_status_changed` operational log with `status_label=cancelled` in the same evidence window; otherwise flags manual review in `meta` + `error_message`.

## Frontend changes
None.

## Tests executed
```bash
php artisan test tests/Feature/Communication/BookingCancellationSyncMailLogTest.php
php artisan test tests/Feature/NotificationOperationalCoverageTest.php --filter="queue|smtp_password"
```

## Assertion counts
- `BookingCancellationSyncMailLogTest`: 8 tests, 27 assertions — all passed.

## Screenshots
N/A (backend-only phase).

## Responsive verification
N/A.

## Accessibility verification
N/A.

## Known limitations
- Repair corroboration is implemented for `booking_cancelled` customer rows only; other events require manual review unless extended later.
- Repair command refuses to run when `QUEUE_CONNECTION` is not `sync`.

## Risks
- Low: behavior change only affects environments where queue is `sync` and mailer is not `log`/`array`/`local`.
- Repair without corroborating evidence intentionally does not mark rows `sent`.

## Rollback instructions
1. Revert the four code/test files listed above.
2. Any rows repaired in production retain `meta.stale_sync_repair`; rollback code only, not data, unless manual DB correction is required.

## Commit SHA
Pending (not committed in this session).

## Final status
Implementation complete; targeted tests passing. Ready for review.

## Production-safe verification commands
```bash
# Confirm queue + mail config (read-only)
php artisan tinker --execute="echo config('queue.default').'|'.config('mail.default');"

# Dry-run stale log assessment for booking 1 (no writes)
php artisan ota:repair-stale-sync-communication-logs --booking-id=1

# Apply repair only after dry-run shows repaired with evidence
php artisan ota:repair-stale-sync-communication-logs --booking-id=1 --log-id=<ID> --apply

# Verify communication log state (read-only SQL/tinker)
php artisan tinker --execute="echo \App\Models\CommunicationLog::query()->where('booking_id',1)->where('event','booking_cancelled')->value('status');"
```
