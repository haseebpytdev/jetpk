# One API Phase 2 — Implementation completeness audit

**Branch:** `phase/one-api-flyjinnah-airarabia-full-supplier-integration-1`  
**Audit date:** 2026-07-22  
**Standard:** Classes alone do not count as “complete”; connection to routes, session, tests, or fixtures required.

Legend: **CC** complete and connected · **INC** implemented not connected · **FIX** fixture-only · **SK** skeleton · **MISS** missing · **BLK** blocked on supplier/config

| # | Capability | Status | Evidence / gap |
|---|------------|--------|----------------|
| 1 | Supplier registration | **CC** | `SupplierProvider::OneApi`; `SupplierAdapterResolver`, `BookingProviderRouter`, `SupplierBookingService`, `TicketingService`; `config/suppliers.php` |
| 2 | Admin connection save/update | **INC** | `SupplierConnectionController` + `one_api.blade.php`; **no** passing HTTP feature test for full admin CRUD (platform 403 in `SupplierConnectionCrudTest`) |
| 3 | Connection normalization | **CC** | `OneApiSupplierConnectionNormalizer` in controller chain; unit-style test `OneApiSupplierConnectionFeatureTest::test_normalizer_preserves_password_on_blank_update` |
| 4 | Credential encryption and masking | **CC** | `SupplierConnection` `encrypted:array`; `test_credentials_are_encrypted_at_rest` |
| 5 | REST authentication | **CC** | `OneApiAuthService`; tests in `OneApiAuthAndSearchTest`, `OneApiPhase2ClosureTest` (malformed, cache scope) |
| 6 | Token caching and expiration | **INC** | Cache+lock in auth service; **missing** explicit tests for JWT margin, opaque TTL, concurrent lock, 401 reauth loop (Parts 6–7 spec) |
| 7 | Search request | **FIX** | `OneApiSearchRequestBuilder`; partial coverage `OneApiAuthAndSearchTest` |
| 8 | Search response parsing | **FIX** | `OneApiSearchResponseParser`, `OneApiResponseNormalizer`; not full ISA matrix |
| 9 | Carrier filtering | **INC** | `OneApiCarrierFilter`; limited assertions |
| 10 | Offer signing | **CC** | `OneApiOfferTokenSigner`; tamper/expiry tests in `OneApiAuthAndSearchTest` |
| 11 | Initial pricing | **FIX** | `OneApiPricingService` + fixture XML; `OneApiPricingAndBundleTest` |
| 12 | Bundle retrieval and selection | **FIX** | `OneApiBundleParser`, checkout flow; matrix bundle cases call final price with fixture bundle id |
| 13 | Baggage retrieval and selection | **SK** | Parsers exist; catalog often empty fixture; **JS does not render/submit baggage** |
| 14 | Meal retrieval and selection | **SK** | Same as baggage |
| 15 | Seat-map retrieval and selection | **SK** | Validator rejects unavailable/duplicate seats; **JS does not render seats**; matrix injects seat in server payload only |
| 16 | Final price | **CC** | `OneApiCheckoutFlowService::saveSelectionsAndFinalPrice`; gate in `OneApiBookingService`; tests + matrix runner |
| 17 | Paid booking | **FIX** | `OneApiBookingService` + fixture `book_paid.xml`; ambiguous idempotency test; **not** full DirectBill/markup/communication suite (Part 10) |
| 18 | On-hold booking | **FIX** | Fixture `book_on_hold.xml`; limited tests |
| 19 | Reservation read | **SK** | `OneApiRetrieveService`, CLI `ota:one-api-read-reservation`; no closed PHPUnit for Type 14 parsing |
| 20 | Held-reservation payment | **SK** | `OneApiHoldPaymentService`; no Part 10 hold-pay test closure |
| 21 | Booking reconciliation | **INC** | `OneApiReconcileBookingCommand`; ambiguous persistence tested; no command integration test |
| 22 | Checkout controller integration | **CC** | `OneApiCheckoutController`, routes `booking.one-api.catalog`, `booking.one-api.final-price` |
| 23 | Checkout browser integration | **INC** | `ota-one-api-checkout.js` loads catalog + POST final price; bundles only in UI; continue button wired via `data-one-api-continue`; **no** Playwright/browser test |
| 24 | Passenger/review/payment continuation | **INC** | Extras on passenger page; final price required server-side for book; **session attachment to review/payment not proven by feature tests** |
| 25 | Booking router integration | **CC** | `OneApiBookingRouterService`, adapter registration |
| 26 | Status persistence | **INC** | Booking meta + workflow store; partial |
| 27 | Ticket/PNR persistence | **FIX** | Parser hooks in booking service; matrix uses static `PNR_FIXTURE_001` without book call |
| 28 | Communication invocation | **MISS** | No test proving `BookingCommunicationService` once on paid book |
| 29 | Admin readiness display | **INC** | `OneApiReadinessService` + admin panel; SOAP blocked when `soap_url` empty |
| 30 | CLI commands | **CC** | Matrix, audit, probes, reconcile; `OneApiMutationCommandGate` |
| 31 | Test matrix | **INC** | `ota:one-api-test-matrix --connection` runs fixture revalidation+catalog+final price for 24 cases; **does not execute book/read/hold-pay**; exits non-zero on failure |
| 32 | Logging/redaction | **INC** | `one-api` channel; **no** dedicated test that tokens never appear in logs/exceptions |

## Checkout E2E (Part 3)

| Step | Status |
|------|--------|
| Signed offer selection | CC (search + signer) |
| Signature/expiry verify | CC (tests) |
| Initial supplier price | FIX (revalidation + pricing fixtures) |
| Reprice UX | INC (generic checkout alerts; One API-specific reprice display thin) |
| Workflow context stored | CC (`OneApiWorkflowContextStore`, revalidation meta) |
| Bundles by O&D | FIX (parser; UI single radio not O&D-paired) |
| Baggage/meals/seats by traveler/segment | SK (server catalog; UI not built) |
| Extras partial rendered | CC (`extras.blade.php` on `one_api`) |
| Selections submitted | INC (final price POST; ancillary arrays empty from browser) |
| Reject manipulated selections | CC (validator + `client_total` prohibited) |
| Final price with TID/RPH/cookies | FIX (fixtures; cookie jar test minimal) |
| Supplier amount replaces indicative | CC (money snapshot) |
| Customer total via markup services | INC (not proven in One API feature test) |
| Context attached through flow | INC |
| Book without final price blocked | CC (`OneApiBookingService`, `OneApiCheckoutFlowService::assertReadyForBooking`) |
| Browser prices ignored | CC |
| Refresh/back duplicate mutations | **MISS** (no idempotency test on final price) |

## SupplierConnection (Part 4)

Proven by tests: blank password preserve, encryption at rest. **Not** proven: full admin HTTP create/update, operational flags, URL resolution, allowlist normalization, test-connection scope limits, 15-case checklist.

## Workflow propagation (Part 5)

Partial via revalidation + checkout flow + matrix runner. **Not** covered by failing tests: TID replacement assertion, cookie loss, markup in DirectBill, client price rejection beyond `client_total`, return O&D split regression suite.

## Test closure Parts 6–11

**Not met.** Current One API suite: **19 tests, 38 assertions** (`php artisan test --filter=OneApi`). Spec lists hundreds of named cases; majority remain **unimplemented**.

## Live blockers

- Official **SOAP endpoint URL** not in vendor documentation → `soap_url` must be configured manually; live SOAP **BLK**
- `live_*_enabled` flags default off; matrix live mode refuses without confirms

## Phase 2 verdict

**Not ready** for commit-as-production-integration or deployment until: expanded PHPUnit (Parts 6–11), checkout UI for ancillary selection, matrix includes booking/read where fixtures allow, admin feature tests unblocked or One API–specific admin tests added, and unrelated Sabre changes kept out of One API commit scope.
