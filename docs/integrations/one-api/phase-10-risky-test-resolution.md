# Phase 10 — Risky test resolution

## Identification

| Field | Value |
|-------|--------|
| Class | `Tests\Feature\Suppliers\OneApiCommunicationIntegrationTest` |
| Method | `test_on_hold_booking_does_not_send_supplier_booking_created` |
| PHPUnit reason | Test was considered **risky** because it performed **no PHPUnit assertions** (Mockery expectations alone do not count). |
| Assertions before fix | 0 (mock-only) |
| Production code exercised | Yes — `SupplierBookingService::dispatchSupplierBookingCommunication()` via reflection |
| Global state | Mockery mock bound in container; torn down in `tearDown()` |

## Resolution

Replaced the Mockery-only mock with **real** `BookingCommunicationService` behaviour and **explicit assertions** on `communication_logs`:

- `SupplierBookingCreated` event count remains **0**
- `BookingStatusChanged` event count is **≥ 1**

This preserves the intended COMM-002 / hold communication policy without a meaningless assertion.

## Verification

```text
vendor/bin/phpunit --filter=OneApi
```

Result after fix: **129 tests, 768 assertions, 0 failures, 0 errors, 0 risky**.
