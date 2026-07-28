# Phase 11 — BookingProviderRouter failure audit

## Test under audit

| Item | Value |
|------|--------|
| File | `tests/Unit/BookingProviderRouterTest.php` |
| Methods | 6 (`test_duffel_*`, `test_sabre_*`, `test_unknown_*`, `test_checkout_*` ×2, `test_duffel_booking_never_invokes_sabre_*`) |

## Stale test wiring (root cause)

The test constructs `BookingProviderRouter` manually with **four** constructor arguments:

```php
new BookingProviderRouter(
    $inner,                                    // SupplierBookingService
    new DuffelBookingService($inner),          // DuffelBookingService
    $this->app->make(SabreBookingService::class), // ← passed as 3rd arg
    $this->app->make(PlatformModuleEnforcer::class),
);
```

Production `BookingProviderRouter` (committed `b155b10`, **before** One API patch hunks) requires **seven** typed services:

1. `SupplierBookingService`
2. `DuffelBookingService`
3. `IatiBookingRouterService`
4. `PiaNdcBookingRouterService`
5. `AirBlueBookingRouterService`
6. `SabreBookingService`
7. `PlatformModuleEnforcer`

Current working tree adds **`OneApiBookingRouterService`** as an additional dependency (eight total). One API does not appear in the generic unit test file.

## Failure mode (current tree and clean HEAD)

| Observation | Detail |
|-------------|--------|
| First failure | `ArgumentCountError` / type error: 3rd parameter expects `IatiBookingRouterService`, test supplies `SabreBookingService` |
| Follow-on errors | `There is already an active transaction` on subsequent tests after bootstrap failure (RefreshDatabase) |
| One API in stack? | **No** — failure occurs in test helper `router()` before routing logic runs |
| Assertions reached? | **No** on first test; cascade on others |

## Baseline vs current

| Run context | Result |
|-------------|--------|
| Clean HEAD worktree `b155b10` (`C:\Users\khadi\ota-jetpk-oneapi-head-router`) | **0/6 pass**, same constructor mismatch |
| Current working tree | **0/6 pass**, identical primary error |

**Classification: A — pre-existing test drift** (test never updated when IATI/PIA/AirBlue router services were added to production constructor). **Not introduced by One API.**

## Production dependency graph (container)

`BookingProviderRouter` is resolved via Laravel container with all router services injected. Peer suppliers are delegated by `supplier_provider` meta inside `createSupplierBooking()` / checkout helpers — unchanged semantics for Sabre, PIA, IATI aside from the new One API branch.

## Phase 11 resolution (no generic test rewrite in One API package)

- **Do not** change production constructor to satisfy stale unit test.
- **Do not** include a broad rewrite of `BookingProviderRouterTest` in the isolated One API commit.
- **Added:** `tests/Feature/Suppliers/OneApiBookingProviderRouterIntegrationTest.php` — container-resolved router; mocks per supplier; `Http::fake()`; proves One API + IATI + PIA NDC + Sabre delegation without live HTTP.

## Dedicated One API router test result (current tree)

| Metric | Value |
|--------|--------|
| Tests | 4 |
| Passed | 4 |
| Assertions | 13 |
| Network | `Http::assertNothingSent()` on all cases |
