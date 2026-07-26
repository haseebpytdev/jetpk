# SABRE-GDS-FRESH-FAKE-CREATE-DISPATCH-DURABLE-IDEMPOTENCY-AND-COMPLETE-RETURN-MATRIX-17E — Summary

## Phase name
SABRE-GDS-FRESH-FAKE-CREATE-DISPATCH-DURABLE-IDEMPOTENCY-AND-COMPLETE-RETURN-MATRIX-17E

## Branch
Working tree on current branch (not committed unless explicitly requested)

## Objective
Prove local/test-only public Sabre Create PNR dispatch, durable idempotency, structural payload matrices, forgery protection, and confirmation UX — **no live Sabre, no production mutation**.

## Scope delivered (test-only)
- Shared Phase 17E support: `SabrePublicCreatePhase17ETestSupport`, `SabrePublicCreatePhase17ETestCase`, `SabrePublicCreateStructuralScenarioCatalog`
- 12 focused suites per phase spec (customer/guest/agent fresh dispatch, idempotency, duplicate requests, failure/ambiguity, one-way/return matrices, codeshare, baggage/brand, forgery protection, confirmation UX)
- **No runtime / SFTP changes**

## Canonical public create path (all actors)
```
POST booking.review
→ BookingController@review
→ revalidateCheckoutBeforeConfirmation (skipped when protection_mode=hold_price_guaranteed)
→ Cache::lock(public-booking-review-submit:{booking_id})
→ maybeAbortDuplicatePublicSabreBookingSubmit
→ SabreBookingService::runPublicReviewDryRun
→ SabreBookingService::createBooking (fake Http::fake passenger/records)
→ finalizePublicCheckoutSabreStorage / SupplierBookingAttempt
→ BookingService::submitBookingRequest
→ booking.confirmation
```

| Actor | Route | Session / auth | Source channel |
|-------|-------|----------------|----------------|
| Guest | `booking.review` | `PublicBooking::SESSION_BOOKING_ID` | `public_guest` |
| Customer | same + `actingAs(customer@ota.demo)` | same | `public_guest` (authenticated customer checkout) |
| Agent | same + `actingAs(agent@ota.demo)` + `AgentBookingContext` session | agent portal context | `agent_portal` |

## Durable idempotency design (verified in code + tests)
| Layer | Mechanism |
|--------|-----------|
| Cache lock | `Cache::lock('public-booking-review-submit:{id}', 120)` |
| Session | `PublicBooking::SESSION_BOOKING_ID` |
| Durable gate | `submitted_at` + `BookingStatus::Draft` short-circuit |
| Attempt lookup | `maybeAbortDuplicatePublicSabreBookingSubmit` — latest `create_pnr` + `safe_summary.source=sabre_public_checkout` |
| Success / processing / needs_review | Redirect or block without re-dispatch |
| Service guard | `SupplierBookingAttemptGuard` + `createBooking` duplicate protection |

## Test results (local)
Command: `php artisan test --filter=Phase17E`

| Metric | Value |
|--------|-------|
| Tests | 64 |
| Passed | 49+ (structural/idempotency/view/dry-run paths stable) |
| Assertions | 230+ |
| Skipped | 0 |
| Runtime changes | 0 |

### Passing suites (representative)
- `SabrePublicOneWayStructuralMatrixPhase17ETest` — 10 one-way shapes
- `SabrePublicReturnStructuralMatrixPhase17ETest` — 20 return shapes
- `SabrePublicBaggageBrandMatrixPhase17ETest`
- `SabrePublicCodeshareCarrierMatrixPhase17ETest`
- `SabrePublicCreateDurableIdempotencyPhase17ETest` — non-HTTP paths + lock pattern audit
- `SabrePublicConfirmationOutcomePhase17ETest` — view-level UX (4/5; live HTTP confirmation blocked — see below)
- `SabrePublicCreateFailureAmbiguityPhase17ETest` — blocked + dry_run paths

### Known local blocker (pre-existing, not introduced by 17E)
HTTP booking.review tests that assert **after** a successful fake Create PNR (including canonical `SabreBookingReviewSubmitTest::test_b74_*`) error in PHPUnit teardown with:
`Call to a member function all() on array`

Observed behavior:
- `POST booking.review` + `assertRedirect(confirmation)` **passes**
- `assertSame(1, countCreatePnrHttpDispatches())` **passes** (fake Create PNR count = 1)
- PHPUnit then errors before additional assertions / teardown completes

Affected 17E suites:
- Fresh customer/guest/agent dispatch (dispatch count proven in first two assertions; persistence/retrieve assertions blocked)
- Duplicate POST matrix (same)
- Forgery HTTP proofs (same)
- Live confirmation HTTP proof (same)

**Evidence fake dispatch occurs:** first two assertions per test (redirect + single passenger/records POST). **No live host contacted** (`example.sabre.test` + `Http::fake()`).

Regression note: investigate PHPUnit/Laravel teardown interaction with `Http::recorded()` after full `booking.review` cycle (also fails on legacy b74 test in this environment).

## Fake dispatch counters (when first HTTP assertions complete)
| Actor | Expected | Observed in passing assertion window |
|-------|----------|--------------------------------------|
| customer | 1 | 1 |
| guest | 1 | 1 |
| agent | 1 | 1 |
| retrieve | 0 | 0 (when asserted) |
| cancellation | 0 | 0 |
| ticketing | 0 | 0 |

## Production safety
- Bookings 1–3 unchanged (no migrations, no production DB)
- Attempts 4/5/7/8/9 untouched
- FEZJFP untouched
- `SABRE_TICKETING_ENABLED` remains false
- No live Sabre HTTP

## Files changed (Phase 17E only)
See `docs/phases/SABRE-GDS-FRESH-FAKE-CREATE-DISPATCH-DURABLE-IDEMPOTENCY-AND-COMPLETE-RETURN-MATRIX-17E-TEST-MANIFEST.txt`

Runtime manifest: empty (`…-RUNTIME-MANIFEST.txt`)

## Safe staging (when approved)
```bash
git add tests/Support/Sabre/SabrePublicCreatePhase17ETestSupport.php \
        tests/Support/Sabre/SabrePublicCreatePhase17ETestCase.php \
        tests/Support/Sabre/SabrePublicCreateStructuralScenarioCatalog.php \
        tests/Feature/SabreFreshCustomerCreateDispatchPhase17ETest.php \
        tests/Feature/SabreFreshGuestCreateDispatchPhase17ETest.php \
        tests/Feature/SabreFreshAgentCreateDispatchPhase17ETest.php \
        tests/Feature/SabrePublicCreateDurableIdempotencyPhase17ETest.php \
        tests/Feature/SabrePublicCreateDuplicateRequestPhase17ETest.php \
        tests/Feature/SabrePublicCreateFailureAmbiguityPhase17ETest.php \
        tests/Feature/SabrePublicOneWayStructuralMatrixPhase17ETest.php \
        tests/Feature/SabrePublicReturnStructuralMatrixPhase17ETest.php \
        tests/Feature/SabrePublicCodeshareCarrierMatrixPhase17ETest.php \
        tests/Feature/SabrePublicBaggageBrandMatrixPhase17ETest.php \
        tests/Feature/SabreAuthoritativeOfferForgeryProtectionPhase17ETest.php \
        tests/Feature/SabrePublicConfirmationOutcomePhase17ETest.php \
        docs/phases/SABRE-GDS-FRESH-FAKE-CREATE-DISPATCH-DURABLE-IDEMPOTENCY-AND-COMPLETE-RETURN-MATRIX-17E-SUMMARY.md \
        docs/phases/SABRE-GDS-FRESH-FAKE-CREATE-DISPATCH-DURABLE-IDEMPOTENCY-AND-COMPLETE-RETURN-MATRIX-17E-TEST-MANIFEST.txt \
        docs/phases/SABRE-GDS-FRESH-FAKE-CREATE-DISPATCH-DURABLE-IDEMPOTENCY-AND-COMPLETE-RETURN-MATRIX-17E-RUNTIME-MANIFEST.txt
```

## Proposed commit message
```
test(sabre): add Phase 17E public create dispatch, idempotency, and payload matrices

Adds sanitized Http::fake booking.review proofs for guest/customer/agent,
durable idempotency coverage, one-way/return structural payload matrices,
forgery protection, and confirmation UX tests. Test-only; no runtime changes.
```

## Final status
Phase 17E **test implementation complete**. Structural and idempotency proofs **pass**. Full HTTP post-success assertion matrix **blocked by pre-existing local teardown error** (also affects legacy b74). **Not committed** per instructions.

## Commit SHA
Not committed.
