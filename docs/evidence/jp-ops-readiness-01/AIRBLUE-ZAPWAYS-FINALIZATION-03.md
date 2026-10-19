# AirBlue / Zapways — Finalization 03 (post-commit uncertain notify)

**Date (UTC):** 2026-10-19  
**Branch:** `supplier/airblue-zapways-finalization`  
**PR:** #104  
**Mode:** OFFLINE ONLY — fixtures/mocks; zero Zapways HTTP  

## Hard safety

```text
AIRBLUE_EXTERNAL_CALLS_ENABLED=false (default)
AIRBLUE_EXTERNAL_NETWORK_CALLS=0
```

## Fix

`SupplierBookingService::persistUncertainSupplierBookingOutcome()` no longer calls
`notifyManualReviewRequired()` inside the persistence transaction.

Canonical uncertain rows + audit commit first. Notification is scheduled with
`DB::afterCommit(...)` via `scheduleUncertainManualReviewNotification()`, wrapped in
try/catch that logs `supplier_booking.uncertain_notify_failed` with safe IDs only.

Notification failure cannot roll back:

- `SupplierBookingAttempt` status=`uncertain`
- `SupplierBooking` status=`pending_reconciliation`
- booking `supplier_booking_status` / `meta.airblue_context` uncertain flags
- audit `booking.supplier_booking_uncertain`

Retry remains blocked via preflight (`booking_outcome_uncertain`).

## Regression evidence

```text
AIRBLUE_NOTIFICATION_POST_COMMIT=YES
AIRBLUE_NOTIFICATION_EXCEPTION_FORCED=YES (pipeline test)
NOTIFICATION_FAILURE_ROLLS_BACK_STATE=NO
RETRY_AFTER_NOTIFICATION_FAILURE=DENY
SECOND_PROVIDER_CALL=0
AIRBLUE_UNCERTAIN_PIPELINE=PASS
MANUAL_REVIEW_NOTIFICATION_AFTER_COMMIT=PASS
```

Artifacts:

- `_phpunit-airblue-finalization-03.txt` / `.xml`
- `_phpunit-generic-supplier-booking-03.txt` / `.xml`

## Readiness

```text
AIRBLUE_CODE_READINESS=PASS
AIRBLUE_OFFLINE_CONTRACT_READINESS=PASS
AIRBLUE_UI_READINESS=PASS
AIRBLUE_BOOKING_LOGIC_READINESS=PASS
AIRBLUE_SECURITY_READINESS=PASS
AIRBLUE_PRODUCTION_CONFIG_READINESS=PASS_RO_BOOLEANS
AIRBLUE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
AIRBLUE_OVERALL_READINESS=READY_AWAITING_LIVE_CERTIFICATION
```
