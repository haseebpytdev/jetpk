# Phase 6 — Security verification (workflow ownership + transport isolation)

**Branch:** `phase/one-api-flyjinnah-airarabia-full-supplier-integration-1`  
**Date:** 2026-07-23  
**Status:** Partial — expanded tests added; full enumerated matrix **not** complete.

## Audited components

| Area | Location | Notes |
|------|----------|--------|
| Workflow context | `OneApiWorkflowContext`, `OneApiWorkflowContextStore` | Owner, agency, booking, session/signed-offer/passenger fingerprints, expiry, lifecycle |
| Guard | `OneApiWorkflowContextGuard` | HTTP + booking mutation; generic 404 `workflow_not_found` |
| Checkout HTTP | `OneApiCheckoutController`, `OneApiCheckoutFlowService` | Auth middleware; rejects fixture path params on final-price |
| Booking | `OneApiBookingService`, `OneApiSupplierBookingAdapter` | `authorizeBookingMutation` before SOAP |
| Transport contract | `OneApiSoapTransportContract` | Live vs fixture implementations |
| Live transport | `LiveOneApiSoapTransport` | No fixture reads |
| Fixture transport | `FixtureOneApiSoapTransport`, `OneApiFixtureTransportScope`, `OneApiFixtureCaseCatalog` | Explicit scope + allowlisted keys |
| DI | `OneApiServiceProvider`, `bootstrap/providers.php` | Fixture binding only when scope enabled |
| Routes | `routes/web.php` | One API checkout under `auth` |

## Workflow ownership — evidence

| Criterion | Evidence | Result |
|-----------|----------|--------|
| Correct owner succeeds | `OneApiCheckoutFlowFeatureTest`, matrix runner internal bypass | **Pass** (fixture paths) |
| Unauthenticated denied | `OneApiWorkflowOwnershipFeatureTest::test_unauthenticated_catalog_request_is_rejected` | **Pass** |
| Different user denied | `test_http_cross_user_catalog_denied`, `test_guard_denies_cross_user_context_access` | **Pass** |
| Different agency denied | Not dedicated test | **Gap** |
| Different local booking denied | Partial via `authorizeBookingMutation` | **Gap** |
| Different SupplierConnection denied | `test_wrong_supplier_connection_returns_not_found` | **Pass** |
| Different session fingerprint denied | `OneApiSecurityPhase6Test::test_session_fingerprint_mismatch_denied` | **Pass** |
| Changed signed-offer fingerprint denied | `OneApiSecurityPhase6Test::test_tampered_signed_offer_fingerprint_denied` | **Pass** |
| Changed passenger profile denied | Not dedicated test | **Gap** |
| Expired context denied | `OneApiSecurityPhase6Test::test_expired_context_denied_on_catalog` | **Pass** |
| Replaced/revoked context | Not tested | **Gap** |
| Completed context cannot mutate | `OneApiSecurityPhase6Test::test_completed_lifecycle_denied` | **Pass** |
| Ambiguous/locked context | Guard denies `locked_ambiguous` on booking mutation only | **Partial** |
| Generic 404, no enumeration | Ownership tests assert 404 | **Pass** |
| No transport on ownership failure | Not asserted with transport spy | **Gap** |

## Transport isolation — evidence

| Criterion | Evidence | Result |
|-----------|----------|--------|
| Production → live transport | `OneApiTransportBindingTest::test_production_scope_resolves_live_transport` | **Pass** |
| PHPUnit → fixture via explicit scope | `test_fixture_scope_resolves_fixture_transport`, `OneApiEnablesFixtureTransport` | **Pass** |
| Matrix command fixture scope | `OneApiMatrixTwentyFourCasesTest`, matrix command test | **Pass** |
| Live cannot resolve fixture without scope | Provider binding guard | **Pass** (code + binding test) |
| HTTP cannot select fixture | `test_fixture_path_in_final_price_body_is_rejected` | **Pass** |
| SupplierConnection cannot select fixture | Live transport ignores fixture_path | **Pass** (`OneApiFixtureTransportSecurityTest`) |
| Env cannot enable fixture in production | Scope requires explicit enable call | **Pass** (design) |
| Arbitrary/traversal/unknown fixture key | `OneApiFixtureTransportSecurityTest`, `OneApiSecurityPhase6Test::test_unknown_fixture_key_rejected` | **Pass** |
| Live failure never falls back to fixture | Not dedicated test | **Gap** |

## PHPUnit (security-focused)

```text
vendor/bin/phpunit --filter=OneApi
```

**Result (Phase 6 pass):** 69 tests, 200 assertions, **0 failures** (fixture-safe, `Http::fake` where REST used).

## Conclusion

Phase 5 security implementation is **verified for core paths** but **does not** satisfy the full Part 1 enumeration (agency/booking/passenger dimensions, transport spy on deny, live→fixture fallback).
