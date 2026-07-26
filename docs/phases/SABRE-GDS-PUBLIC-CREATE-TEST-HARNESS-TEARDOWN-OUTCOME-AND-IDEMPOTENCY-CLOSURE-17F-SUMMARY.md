# SABRE-GDS-PUBLIC-CREATE-TEST-HARNESS-TEARDOWN-OUTCOME-AND-IDEMPOTENCY-CLOSURE-17F

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | 17F closure on 17E harness |
| Branch | `main` (local working branch; no commit performed) |
| Baseline HEAD | `9595414887f2bb476bfa4f55ff3b9341846f593d` |
| Pre-phase `git diff` | empty (tracked) |
| Objective | Close Phase 17E teardown/outcome/idempotency failures without weakening assertions or bypassing real `booking.review` |
| Final status | **PASS** — Phase 17E gate green; legacy B74 green; no live supplier HTTP |

## Pre-phase snapshot (2026-07-25)

- `git diff --stat`: empty (tracked)
- `git diff --name-only`: empty
- Many pre-existing **untracked** paths preserved (dashboard/, deployment_packages/, audit/integration docs, prior phase summaries). See `PHASE17_UNRELATED_PREEXISTING_FILES.txt`.

## Root cause — `all()` on array

| Item | Detail |
|------|--------|
| Reported symptom | `Call to a member function all() on array` |
| Exception | `Error` in `Illuminate\Testing\TestResponseAssert.php:81` |
| Mechanism | Secondary failure while enriching a **failed** `assertRedirect()` — session `errors` stored as plain array, not `ViewErrorBag` |
| Primary failure | Strategy/context incomplete → redirect to `booking.review` with validation error (`sabre_gds_no_eligible_pnr_strategy` / missing `brand_code`) |
| Classification | **E — combination** |
| B — fixture | Phase 17E/B74 meta missing GDS strategy fields (`brand_code`, segment booking class / fare basis lists, certified route selection) |
| C — harness | `assertExactlyOneCanonicalCreateDispatch()` added for `ConnectionException` paths where `Http::recorded()` may miss thrown requests |
| A — runtime (17F) | Public checkout duplicate-submit guard for non-retryable definitive failures; `createBooking()` duplicate guard for existing public-checkout success |

## Runtime changes (production)

### `BookingController::maybeAbortDuplicatePublicSabreBookingSubmit`

Blocks repeat customer POST when latest `sabre_public_checkout` attempt is `failed`, `live_call_attempted=true`, and `SabrePnrFailureClassifier` reports `retry_allowed=false` (e.g. `sabre_booking_validation_failed`).

### `SabreBookingService::createBooking`

Early return `blocked` / `live_call_attempted=false` when booking already has supplier identity, successful `SupplierBooking`, or successful `sabre_public_checkout` create attempt (unless operator/scenario/admin bypass options active).

**17F correction:** Post-dispatch `ConnectionException` / transport timeout → `status=needs_review`, `manual_reconciliation_required=true`, pending-verification customer copy; no fabricated PNR/`SupplierBooking`.

### `SabrePnrFailureClassifier::isPostDispatchAmbiguousTransportFailure`

When `live_call_attempted=true` and error is connection/timeout class, classify as staff review with `retry_allowed=false` (before temporary-provider retry path).

### `BookingController` (public checkout)

- Routes ambiguous post-dispatch timeout to `booking.confirmation` with pending-verification notice (not definitive failure).
- Blocks duplicate POST for `needs_review` + post-dispatch transport errors.

## Test/support changes (17F)

| File | Change |
|------|--------|
| `tests/Support/Sabre/SabrePublicCreatePhase17ETestSupport.php` | GDS-eligible meta; `scenario_runner`; `cpnr_connecting_same_carrier_public_checkout_enabled`; `assertExactlyOneCanonicalCreateDispatch()` |
| `tests/Feature/SabrePublicCreateFailureAmbiguityPhase17ETest.php` | Enabled definitive rejection (HTTP 400) and ambiguous timeout tests |
| `tests/Feature/SabrePublicCreateDuplicateRequestPhase17ETest.php` | Back/resubmit idempotency fixture fix |
| `tests/Feature/SabreBookingReviewSubmitTest.php` | `sabreB74GdsStrategyEligibleMeta()`; v2.4/v2.5 passenger/records HTTP fake; B74 config parity |

## Exact test gate

```
php artisan test --filter=Phase17E
tests=64 passed=64 failed=0 errors=0 skipped=0 assertions=368 duration≈187s

php artisan test tests/Feature/SabreBookingReviewSubmitTest.php --filter=test_b74
tests=4 passed=4 failed=0 errors=0 skipped=0 assertions=32
```

### HTTP evidence (zero live supplier)

- All supplier HTTP intercepted via `Http::fake` against `example.sabre.test`
- Fresh create: guest/customer/agent dispatch count=1; retrieve/cancel/ticketing=0
- Failure paths: definitive rejection and ambiguous timeout dispatch count≤1; no retrieve/cancel/ticketing

## Fresh actor proofs

| Actor | Route | Create dispatch | Redirect | Persistence |
|-------|-------|-----------------|----------|-------------|
| Guest | `POST booking.review` | 1 | `booking.confirmation` | 1 `SupplierBooking`, 1 success attempt, PNR from fake |
| Customer | same | 1 | confirmation | same |
| Agent | same | 1 | confirmation | same |

## Outcome proofs

| Scenario | Dispatch | Attempt status | Retry blocked |
|----------|----------|----------------|---------------|
| Definitive rejection (HTTP 400) | 1 | `failed` / `sabre_booking_validation_failed` | yes |
| Ambiguous timeout (`ConnectionException`) | 1 (canonical) | `needs_review` / `sabre_booking_connection_error` / `manual_reconciliation_required` | yes (`retry_allowed=false`; duplicate POST dispatch=0) |

## Idempotency (durable)

Covered by `SabrePublicCreateDuplicateRequestPhase17ETest` + `SabrePublicCreateDurableIdempotencyPhase17ETest`: double-click, refresh, back/resubmit, two tabs, cache flush, processing/success/needs_review attempts, service-level duplicate guard. **No unique DB index added** (unchanged).

## Structural matrix

Executed via data providers in `SabrePublicOneWayStructuralMatrixPhase17ETest` and `SabrePublicReturnStructuralMatrixPhase17ETest` using `SabrePublicCreateStructuralScenarioCatalog` (direct/connecting return variants, mixed carriers, codeshare, overnight rollover, baggage/brand/class/fare-basis differences, multi-stop). All included in Phase 17E 64-test gate.

## Forgery protection

`SabreAuthoritativeOfferForgeryProtectionPhase17ETest` — guest/customer/agent forged HTTP fields do not alter authoritative create payload (included in gate).

## Legacy B74

All four `test_b74_*` methods pass after GDS meta helper + passenger/records URL matching (v2.4.0 and v2.5.0).

## Safety confirmations

| Constraint | Status |
|------------|--------|
| Bookings 1–3 untouched | yes (no booking seed mutations) |
| Attempts 4/5/7/8/9 untouched | yes |
| FEZJFP untouched | yes |
| Ticketing disabled in tests | yes (`ticketing_enabled=false`) |
| No live Sabre | yes |
| No production DB mutation | yes (RefreshDatabase local only) |
| No commit/stage | yes |

## Runtime manifest

See `SABRE-GDS-PUBLIC-CREATE-TEST-HARNESS-TEARDOWN-OUTCOME-AND-IDEMPOTENCY-CLOSURE-17F-RUNTIME-MANIFEST.tsv` and `-RUNTIME-SHA256.tsv`.

### Production verification (pending upload)

```powershell
# On server after upload
sha256sum /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/BookingController.php
sha256sum /home/pkjetp/jetpk_app/app/Services/Suppliers/Sabre/Booking/SabreBookingService.php
sha256sum /home/pkjetp/jetpk_app/app/Support/Bookings/SabrePnrFailureClassifier.php
```

Expected local hashes:

- `BookingController.php` → `736D3B1ADB927733606A12D0A5BCC396BA33AE95A9F1F8615066A84309CEC79C`
- `SabreBookingService.php` → `EBBEB560D65E0E048E1CC42638435019F3E787DDBFB4686D1BF5FCD599B0ECE3`
- `SabrePnrFailureClassifier.php` → `3524238D050509861E09507F4E468D12B3208F4D504A4E82E4E98C7209025300`

## Scoped staging block (do not run until authorized)

```bash
git add \
  app/Http/Controllers/Frontend/BookingController.php \
  app/Services/Suppliers/Sabre/Booking/SabreBookingService.php \
  app/Support/Bookings/SabrePnrFailureClassifier.php \
  tests/Feature/SabreAuthoritativeOfferForgeryProtectionPhase17ETest.php \
  tests/Feature/SabreFreshAgentCreateDispatchPhase17ETest.php \
  tests/Feature/SabreFreshCustomerCreateDispatchPhase17ETest.php \
  tests/Feature/SabreFreshGuestCreateDispatchPhase17ETest.php \
  tests/Feature/SabrePublicBaggageBrandMatrixPhase17ETest.php \
  tests/Feature/SabrePublicCodeshareCarrierMatrixPhase17ETest.php \
  tests/Feature/SabrePublicConfirmationOutcomePhase17ETest.php \
  tests/Feature/SabrePublicCreateDuplicateRequestPhase17ETest.php \
  tests/Feature/SabrePublicCreateDurableIdempotencyPhase17ETest.php \
  tests/Feature/SabrePublicCreateFailureAmbiguityPhase17ETest.php \
  tests/Feature/SabrePublicOneWayStructuralMatrixPhase17ETest.php \
  tests/Feature/SabrePublicReturnStructuralMatrixPhase17ETest.php \
  tests/Feature/SabreBookingReviewSubmitTest.php \
  tests/Support/Sabre/SabrePublicCreatePhase17ETestCase.php \
  tests/Support/Sabre/SabrePublicCreatePhase17ETestSupport.php \
  tests/Support/Sabre/SabrePublicCreateStructuralScenarioCatalog.php \
  docs/phases/SABRE-GDS-PUBLIC-CREATE-TEST-HARNESS-TEARDOWN-OUTCOME-AND-IDEMPOTENCY-CLOSURE-17F-SUMMARY.md \
  docs/phases/SABRE-GDS-PUBLIC-CREATE-TEST-HARNESS-TEARDOWN-OUTCOME-AND-IDEMPOTENCY-CLOSURE-17F-RUNTIME-MANIFEST.tsv \
  docs/phases/SABRE-GDS-PUBLIC-CREATE-TEST-HARNESS-TEARDOWN-OUTCOME-AND-IDEMPOTENCY-CLOSURE-17F-RUNTIME-SHA256.tsv \
  docs/phases/PHASE17_RUNTIME_FILES.txt \
  docs/phases/PHASE17_TEST_FILES.txt \
  docs/phases/PHASE17_FIXTURE_FILES.txt \
  docs/phases/PHASE17_DOC_FILES.txt \
  docs/phases/PHASE17_UNRELATED_PREEXISTING_FILES.txt
```

## Rollback

1. Revert the three runtime files to baseline HEAD `9595414887f2bb476bfa4f55ff3b9341846f593d`.
2. Remove Phase 17E/17F test/support files if deployed in error.
3. No migration rollback required.
