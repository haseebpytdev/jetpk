# Phase 18A — Sabre GDS Search, Cache, Revalidation, and Checkout Baseline

**Phase:** SABRE-GDS-SEARCH-CACHE-REVALIDATION-FINAL-CLOSURE-18
**Subphase:** 18A — Baseline, inventory, and defect register (read-only)
**Date:** 2026-07-26
**Branch at inventory:** `main`
**Baseline commit:** `29f21e51e1241eb6f74b990d006abe73848a130f` (`docs: record Phase 17F Create PNR safety closure`)
**Remote sync:** `jetpk/main` matches local `HEAD`
**Production Laravel root:** `/home/pkjetp/jetpk_app`
**Ticketing:** `SABRE_TICKETING_ENABLED` must remain `false` (confirmed in `config/suppliers.php`)

---

## 1. Git state

| Item | Value |
|------|-------|
| Branch | `main` |
| HEAD | `29f21e5` |
| `jetpk/main` | `29f21e5` (synchronized) |
| Prior closure commits | `6c792e3` fix(sabre): close Create PNR safety and CMS route precedence; `9595414` fix(jetpk): reconcile isolated runtime parity |
| Working tree | Clean for tracked files; many **pre-existing untracked** paths preserved (`dashboard/`, `deployment_packages/`, `docs/audit/`, phase summaries, etc.) |
| Phase 18 runtime changes | **None** in 18A (documentation only) |

**Git hygiene rules for Phase 18:** explicit `git add <path>` only; no `git add .`, `git clean`, blanket restore/reset; do not delete unrelated untracked files.

---

## 2. End-to-end Sabre GDS public flow (active path)

```
PublicFlightSearchRequest::criteria()
  → FlightController::results() / resultsData() / runSearch()
  → FlightSearchService::searchWithMeta()
       → expandOriginVariants() [nearby departure airports]
       → collectOffersFromConnections() per SupplierConnection
            → SabreFlightSupplierAdapter::search()
                 → SabreChannelGateResolver [GDS vs NDC lane selection]
                 → SabreClient::searchFlights() [BFM HTTP]
                 → SabreFlightSearchNormalizer::normalize() [alias → Gds\SabreFlightSearchNormalizer]
       → DirectFlightsOfferFilter, departure lead-time, mixed-carrier filter
       → pricing / toDisplayOffer / SabreFareVerificationDigest
  → FlightSearchResultStore::store()  → Cache key flight_search:{uuid}, TTL 1800s
  → Results UI (filters, return-split, branded fare carousel, nearby date strip)

Fare select → FlightController::selectOffer / passengers redirect
  → BookingController::passengers()
       → FlightSearchResultStore::findOffer(search_id, offer_id)
       → SabreSelectedOfferRevalidationGate::evaluateCheckoutTransition()
       → createDraftBooking()

Review → BookingController::review()
       → guardSabreOfferFreshnessAtCheckout / AtBookingSubmit
       → revalidateCheckoutBeforeConfirmation() [FareHoldService]
       → applySabreOfferRefreshBeforePublicPnr() [optional]
       → SabreBookingService::runPublicReviewDryRun() → createBooking() when enabled

Confirmation → BookingController::confirmation()
```

**Primary controllers:** `app/Http/Controllers/Frontend/FlightController.php`, `app/Http/Controllers/Frontend/BookingController.php`

**Primary services:** `FlightSearchService`, `FlightSearchResultStore`, `SabreFlightSupplierAdapter`, `SabreBookingService`, `SabreGdsRevalidationService`, `SabreSelectedOfferRevalidationGate`

---

## 3. Search request normalization

| Layer | File | Responsibility |
|-------|------|----------------|
| HTTP validation | `app/Http/Requests/PublicFlightSearchRequest.php` | Uppercase IATA; trip_type OW/RT/multi-city; cabin; pax caps; `stops=direct` → `direct_only`; `include_nearby=1` → `nearby_airports` |
| DTO | `app/Data/FlightSearchRequestData.php` | Typed criteria; `returnOrigin()`; multi-city segments; traveller normalization via `TravellerCountRules` |
| Supplier request | `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchRequestBuilder.php` | BFM request envelope from `FlightSearchRequestData` |
| Origin expansion | `FlightSearchService::expandOriginVariants()` | Nearby **departure** airports only when `nearby_airports=true`; sets `requested_origin`, `search_origin_variant` |
| Direct-only | `DirectFlightsOfferFilter` + supplier hints | Post-normalization `stops=0`; Sabre `DirectFlightsOnly` in adapter |

**Not implemented in public flight search:** flexible dates ±1 day as a search dimension. The results page exposes a **nearby date fare strip** (`NearbyDateFareStripService`, ±`radius_days` default 3) that runs separate mini-searches per date — not a single flexible-date BFM request.

---

## 4. Cache layers

| Layer | Key pattern | TTL | Isolation dimensions | File |
|-------|-------------|-----|----------------------|------|
| **Search results (authoritative selection store)** | `flight_search:{uuid}` | 1800s | **Per search session UUID only** — criteria stored inside payload, not in key | `FlightSearchResultStore` |
| Sabre OAuth token | `sabre:token:connection:{id}` | `expires_in - 60` | `connection_id` | `SabreClient`, `SabreActiveAuthDiagnostic` |
| Nearby date strip | `nearby_date_strip:{md5(json)}` | 900s (config) | origin, destination, depart, return, trip, pax, cabin, radius — **missing** agency, client, direct_only, currency, supplier channel, nearby-airport expansion flag | `NearbyDateFareStripService` |
| Review submit lock | `public-booking-review-submit:{bookingId}` | 120s | booking id | `BookingController::review()` POST |
| Checkout lock meta (session/meta, not Cache) | `{searchId}\|{offerId}\|{fareOptionKey}` | n/a | composite handoff | `BookingController::buildCheckoutLockKey()` |

**Critical finding:** There is **no criteria-level search deduplication cache**. Every public search allocates a new UUID. Phase 18B must introduce or harden a **deterministic search cache-key matrix** if supplier result caching is required; today isolation is only at the **selected-offer store** level (`search_id` + `offer_id`).

**Offer freshness (logical TTL, not Cache TTL):** `config/ota.php` → `offer_freshness.refresh_due_seconds` (default 300), `stale_after_seconds` (default 600). Enforced by `SabreOfferFreshness`.

---

## 5. Search cache-key construction (current vs required)

### Current

- **Result store:** random UUID; criteria duplicated in payload `criteria` key.
- **Offer ID (normalized):** `sha256(provider|connectionId|rawReference|airline+flight|departureAt|total|currency)` in `Gds\SabreFlightSearchNormalizer`.
- **Branded fare dedupe:** `brand|round(total)|pricing_information_ref|piIndex`.
- **Itinerary consolidation:** `ItineraryFareConsolidator::signatureForOffer()` → `sha256(json…)`.
- **Return-split leg keys:** `ReturnSplitComboService::buildLegKey()`.
- **SabreFareVerificationDigest:** integrity metadata, **not** a cache key; flags `stale_cached_result_possible`.

### Required for Phase 18B (not yet implemented)

A single deterministic builder must isolate: tenant/client, agency, supplier connection, supplier channel, Sabre GDS vs NDC lane, environment, POS, origins (including expanded nearby), destination, trip type, dates, multi-city segments, pax mix, cabin, currency, direct-only, nearby-airport flag, flexible-date flag (when added), airline filters, branded-fare controls, result-source controls, search mode/version, normalization schema version.

---

## 6. Search result persistence and reconstruction

**Store:** `FlightSearchResultStore::store()`

- Max **150** offers (`MAX_STORED_OFFERS`).
- Sabre offers stamped with `ensureSabreBookingContextOnCachedOffer()`.
- Round-trip: optional `return_split` index via `ReturnSplitComboService::safeBuildIndexForStore()`.

**Read paths:**

- `get(searchId)` — raw payload; maps `created_at` → `search_created_at`.
- `listOffersForDisplay()` — `ItineraryFareConsolidator` + `SabreMixedCarrierSearchResultsFilter`.
- `findOffer(searchId, offerId)` — display list first, then raw offers; blocks multicity inquiry-only and mixed-carrier policy blocks.

**Reconstruction invariants (enforced in normalizer + tests):** segment order, O/D, times, marketing/operating carrier, flight number, RBD, cabin, fare basis, baggage, brand, currency, pricing, pax-type pricing, stops, overnight rollover, outbound/inbound partition.

**Normalizer:** `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php` (~6.9k lines). Root `SabreFlightSearchNormalizer.php` is a **deprecated class_alias** only.

---

## 7. Offer identifiers and authoritative offer linkage

| Identifier | Role |
|------------|------|
| `search_id` | UUID; session handoff from results → passengers → review |
| `offer_id` / `id` | Deterministic hash from normalizer |
| `fare_option_key` | Branded fare / PI selection within offer |
| `checkout_lock_key` | `searchId|offerId|fareOptionKey` |
| `accepted_fare_context_hash` | Fare-change acceptance on review POST |
| `sabre_booking_context` / `sabre_shop_context` | Leg refs, schedule refs, fare linkage in `raw_payload` |
| Canonical segment signatures | `SabreGdsRevalidationCanonicalSegmentSignature` |
| Host rejection fingerprint | `SabreHostRejectionFingerprint` + matcher at checkout |

**Gaps (from STALE-CACHED-FARE audit):** public `search_correlation_id` and end-to-end `safe_offer_fingerprint` shop→revalidate→PNR are **partial** vs scenario/certification path.

---

## 8. Revalidation request construction and normalization

| Component | File |
|-----------|------|
| Payload builder | `SabreRevalidationPayloadBuilder` |
| GDS service | `SabreGdsRevalidationService` — `revalidateDraft()`, `revalidateForBooking()`, fare comparison, booking meta persistence |
| Checkout gate | `SabreSelectedOfferRevalidationGate` — freshness + `runSelectedOfferRevalidation()` → `SabreBookingService::runRevalidationBeforeBooking()` |
| Freshness policy | `SabreOfferFreshness` |
| Outcome mapping | `SabreGdsLiveScenarioRevalidationOutcomeMapper`, linkage diagnostics under `app/Support/Sabre/Revalidation/` |
| Config | `config/suppliers.php` — `revalidate_path`, `revalidate_before_booking`, `revalidate_payload_style` |

**Live revalidation gate flags:** `booking_enabled` ∧ `booking_live_call_enabled` ∧ `revalidate_before_booking`.

**Architectural note:** `SabreSelectedOfferRevalidationGate` does **not** call `SabreGdsRevalidationService` directly; it uses `SabreBookingService::runRevalidationBeforeBooking()`. Fare-comparison enrichment and meta persistence paths may diverge — Phase 18E must prove parity.

---

## 9. Search-to-review session handoff

1. Results store `search_id` in view/JSON (`FlightController`).
2. Fare select POST includes `search_id`, `offer_id`, `fare_option_key`.
3. `StoreBookingPassengersRequest` resolves offer via `FlightSearchResultStore::findOffer()`.
4. `BookingController::passengers()` — `prepareSabreOfferForCheckoutHandoff()`, freshness gate, draft booking with `meta.checkout_search_id`, offer snapshots.
5. `ClientCheckoutContextResolver` persists JetPK client slug across redirects.
6. Stale recovery: `attemptStaleOfferRecovery()` with session loop guard.

---

## 10. Review-to-Create-PNR handoff

1. `BookingController::review()` GET — fare-change modal sync.
2. POST — `guardSabreOfferFreshnessAtBookingSubmit`, `revalidateCheckoutBeforeConfirmation`, fare-change acceptance gates, `Cache::lock`, duplicate submit guard (`maybeAbortDuplicatePublicSabreBookingSubmit`).
3. `applySabreOfferRefreshBeforePublicPnr()` optional re-shop.
4. `SabreBookingService::runPublicReviewDryRun()` → `createBooking()` when live flags allow.
5. Phase 17E/17F: durable idempotency, ambiguous timeout → `needs_review`, no auto-retry.

---

## 11. Guest, customer, and agent flow differences

| Role | `source_channel` | Notes |
|------|------------------|-------|
| Guest | `public_guest` | Default in `FlightSearchService::searchWithMeta()` |
| Customer (logged in) | `public_customer` / session-derived | Same checkout blades; header shows profile dropdown |
| Agent | `agent_portal` via `AgentBookingContext` | Agency/agent ids in session; `SOURCE_CHANNEL_AGENT_PORTAL` on booking |

Search, cache, revalidation, and Sabre gates are **shared**; differences are channel metadata, agency resolution, and dashboard visibility after booking. No separate Sabre normalizer per role.

---

## 12. Trip-type behavior

| Trip type | Search | Results | Checkout |
|-----------|--------|---------|----------|
| One-way | Standard BFM | Single-direction cards | Standard handoff |
| Return | BFM RT | Combined cards + optional **return-split** UI (`OTA_RETURN_SPLIT_SELECT_ENABLED`) | Combo `offer_id` from paired supplier offers only |
| Multi-city | BFM multi-slice | **Inquiry-only** for public (`PublicSabreMulticitySearchPostProcessor`); no checkout/PNR | Blocked at passengers with `multicity_plan_only_not_certified` |

---

## 13. Filters and supplier dimensions

| Dimension | Implementation |
|-----------|----------------|
| Direct-only | `direct_only` criteria + `DirectFlightsOfferFilter` |
| Nearby origin | `nearby_airports` → `expandOriginVariants()`; return leg uses `return_origin` / requested origin |
| Cabin | `economy`, `premium_economy`, `business`, `first` in request |
| Pax mix | Adults/children/infants; infants ≤ adults |
| Currency | Criteria `currency` (default PKR); pricing conversion in `FlightSearchService` |
| Client / agency | `Agency` from default slug; `ClientCheckoutContextResolver` for JetPK slug |
| Supplier channel | `SabreChannelGateResolver` — GDS vs NDC lanes; adapter may call both |
| Airline filters | Results UI client-side / sort; not in `PublicFlightSearchRequest` base rules |
| Flexible ±1 day | **Not a search parameter** — nearby date **strip** only |

---

## 14. JetPakistan checkout and master-client fallback risk

**Mitigations in place:**

- `ClientNoFallbackGuard`, `client_route()`, `client_safe_url()`, `clientRedirect()` in `BookingController`.
- Audits: `ota:client-no-fallback-audit`, `ota:jetpk-flow-leak-audit`, `ota:jetpk-result-flow-leak-audit`, `jetpk:master-trace-audit`.
- Checkout views under `resources/views/themes/frontend/jetpakistan/frontend/booking/`.
- Payment options: Manual Payment, Pay by Card (JetPK-branded flow per Phase 8G scope).

**Residual risk:** Any unprefixed `/booking/*` fallback must keep resolving JetPK theme via `ClientCheckoutContextResolver` + `current_client_slug()`.

---

## 15. Existing Sabre and flight-related tests (inventory)

**Approximate counts:** ~260 `*Sabre*` test files, ~7 `*FlightSearch*`, ~2 `*OfferFresh*`.

### Search / normalize / cache

- `tests/Feature/SabreSandboxSearchTest.php`
- `tests/Unit/SabreFlightSearchNormalizerScheduleTimeTest.php`, `SabreFlightSearchNormalizerMultiCityTest.php`
- `tests/Feature/SabreFareVerificationPhaseS32Test.php`
- `tests/Unit/Support/FlightSearch/ItineraryFareConsolidatorTest.php`
- `tests/Unit/Http/Requests/PublicFlightSearchRequestFiltersTest.php`, `PublicFlightSearchRequestMulticityTest.php`
- `tests/Feature/Phase2PublicFlightSearchSecurityTest.php`
- `tests/Unit/StaleCachedFareAndCustomerUxClosureAuditPhaseTest.php`

### Return split

- `tests/Unit/Services/FlightSearch/ReturnSplitComboServiceTest.php`
- `tests/Feature/FlightSearch/ReturnSplitSelectFlowTest.php`

### Freshness / revalidation / checkout

- `tests/Feature/SabreOfferFreshnessPhase11KFTest.php`, `SabreOfferFreshnessPhase11KGTest.php`
- `tests/Feature/SabreOfferRefreshPublicCheckoutTest.php`, `SabreOfferRefreshAcceptanceTest.php`
- `tests/Feature/SabreHostRejectionFingerprintPhase11KITest.php`
- `tests/Feature/SabreBookingReviewSubmitTest.php`
- `tests/Feature/SabrePublicOneWayStructuralMatrixPhase17ETest.php`
- `tests/Feature/SabrePublicReturnStructuralMatrixPhase17ETest.php`
- `tests/Feature/SabrePublicBaggageBrandMatrixPhase17ETest.php`
- `tests/Feature/SabrePublicCodeshareCarrierMatrixPhase17ETest.php`
- `tests/Feature/SabreAuthoritativeOfferForgeryProtectionPhase17ETest.php`
- `tests/Feature/SabrePublicCreate*Phase17ETest.php` (idempotency, duplicate, ambiguity, guest/agent/customer)
- Many `tests/Unit/SabreRevalidationBfm*CorrectionPhaseTest.php`
- `tests/Unit/SabreGdsRevalidationToPnrCreationReadinessAuditPhaseTest.php`

### Nearby date strip

- `tests/Feature/NearbyDateFareStripTest.php`

---

## 16. TODO / FIXME / legacy markers in active flow

| Area | Finding |
|------|---------|
| `app/Services/Suppliers/Sabre/**` (search/revalidation/checkout) | **No inline TODO/FIXME** in active search/normalizer/gate paths |
| `SabreBookingService` | Docblocks reference `dry_run`, `runPublicReviewDryRun`; operational guards, not placeholders |
| Root normalizer alias | Legacy `class_alias` — documentation hazard only |
| Stale audit doc | Documents **PARTIAL** gaps (correlation id, acceptance token, GET review redirect) |
| Flexible dates | Not implemented — no TODO; feature gap |
| Group ticketing `flexible` | Separate module (`GroupInventorySearchService`), out of Sabre GDS public search scope |

---

## 17. Prior phase closure context (17E–17G)

- Create PNR: durable idempotency, duplicate prevention, ambiguous post-dispatch timeout → `needs_review`, no auto-retry.
- Route precedence: `/admin` → `admin.dashboard`; CMS reserved slugs.
- Ticketing remains disabled.
- Protected production bookings/attempts must not be touched in any probe.

---

## 18. Initial defect register

| ID | Severity | Area | Reproducible symptom | Root cause | Affected files | Routes | Risk class | Test coverage | Proposed correction | Deploy | Dependency |
|----|----------|------|----------------------|------------|----------------|--------|------------|---------------|---------------------|--------|--------------|
| DEF-18-001 | **High** | Search cache key | No criteria-level cache isolation; every search is new UUID; cannot prove collision matrix | `FlightSearchResultStore` uses random UUID only; no `SearchCacheKeyBuilder` | `FlightSearchResultStore.php`, `FlightSearchService.php` | `flights.results`, `flights.results.data` | Stale/wrong-tenant data if dedup added incorrectly | Partial (`StaleCachedFare*`) | Phase **18B**: introduce deterministic cache-key builder + matrix tests | Yes | None |
| DEF-18-002 | **High** | Nearby strip cache | Strip cache may return fares for wrong agency/channel/filter mix | `NearbyDateFareStripService` md5 key omits agency, client, `direct_only`, currency, supplier channel | `NearbyDateFareStripService.php` | `flights.results.nearby-dates` | Wrong price display | `NearbyDateFareStripTest` (partial) | Extend key dimensions in **18B** | Yes | None |
| DEF-18-003 | **Medium** | Result truncation | Offer selected on results page missing at passengers (410 / re-search) | `MAX_STORED_OFFERS = 150` | `FlightSearchResultStore.php` | `booking.passengers` | Selection loss | Limited | Cap handling: fail safe + user message; or raise cap with perf audit (**18C**) | Yes | None |
| DEF-18-004 | **Medium** | Stale offer | Expired search payload still readable until Cache TTL; freshness relies on `SabreOfferFreshness` clocks | Cache TTL 1800s > stale_after 600s; no hard reject on `get()` | `FlightSearchResultStore.php`, `SabreOfferFreshness.php` | checkout | Stale checkout if gate bypassed | `SabreOfferFreshnessPhase11K*` | **18C**: reject expired/stale at store read for selection paths | Yes | None |
| DEF-18-005 | **Medium** | Revalidation parity | Checkout gate path may lack `SabreGdsRevalidationService` fare_comparison meta | `SabreSelectedOfferRevalidationGate` → `SabreBookingService` only | `SabreSelectedOfferRevalidationGate.php`, `SabreGdsRevalidationService.php` | `booking.passengers`, `booking.review` | Wrong price at PNR | Many unit tests; integration gap | **18E**: unify authoritative linkage | Yes | None |
| DEF-18-006 | **Medium** | Offer linkage | Shop→revalidate→PNR fingerprint continuity partial | No public `search_correlation_id` / `safe_offer_fingerprint` | `BookingController.php`, scenario support classes | checkout | PNR wrong offer | Audit tests | **18E**: propagate canonical signatures | Yes | None |
| DEF-18-007 | **Medium** | Freshness bypass | Branded fare complete context may skip revalidation gate block | `guardSabreOfferFreshnessAtCheckout` branded-context bypass | `BookingController.php` | `booking.passengers` | Stale branded fare | `SabreOfferRefreshPublicCheckoutTest` | **18C/18E**: tighten bypass conditions | Yes | None |
| DEF-18-008 | **Low** | Fare digest | `offer_identity_mismatch` never set in `buildFromDisplayOffer` | Hard-coded `identity mismatch = false` | `SabreFareVerificationDigest.php` | results JSON | Diagnostic blind spot | `SabreFareVerificationPhaseS32Test` | **18D**: enable when inputs available | Optional | None |
| DEF-18-009 | **Low** | UX / session | Back-button review after submit | No GET redirect when `submitted_at` set | `BookingController.php` | `booking.review` | Double-submit confusion | Partial | **18G**: GET guard redirect | Yes | None |
| DEF-18-010 | **Info** | Feature gap | Phase 18 spec flexible ±1 day not in public search | Only nearby date strip (separate searches) | N/A | N/A | Spec mismatch | N/A | **Defer** or implement in **18F** if product requires | TBD | Product decision |
| DEF-18-011 | **Info** | GDS/NDC | Lane isolation in any new search cache | GDS and NDC share adapter but separate lanes | `SabreFlightSupplierAdapter.php`, `SabreChannelGateResolver.php` | search | Cross-lane cache pollution | Channel gate tests | **18B**: lane dimension in cache key | Yes | None |
| DEF-18-012 | **Info** | Live probe | Production search/revalidation not exercised post-17G | By design — requires approval | N/A | N/A | None locally | N/A | **18H** plan only | No | User approval |
| DEF-18-013 | **Blocked** | Ticketing | Ticket issuance | LNIATA / flags | `SabreGdsTicketingService` | admin | Supplier | Readiness tests | Out of scope | No | LNIATA |
| DEF-18-014 | **Blocked** | NDC | PIA NDC / Sabre NDC booking | Explicit exclusion | `PiaNdc*`, `SabreNdc*` | various | Supplier | NDC tests | No changes | No | Phase policy |

---

## 19. Phase 18 subphase plan (execution order)

| Subphase | Commit subject | Scope |
|----------|----------------|-------|
| **18A** | `docs(sabre): inventory Phase 18 search and cache closure` | This document |
| **18B** | `fix(sabre): harden search cache key isolation` | Cache-key matrix + tests |
| **18C** | `fix(sabre): reject stale offers and stabilize cache reconstruction` | TTL, stale detection, reconstruction |
| **18D** | `fix(sabre): close shopping normalization matrix gaps` | Fake-HTTP normalizer matrix |
| **18E** | `fix(sabre): enforce authoritative revalidation linkage` | Revalidation path parity |
| **18F** | `fix(flights): align filters and JetPakistan checkout continuity` | Filters, UX, no master fallback |
| **18G** | `fix(checkout): close Sabre role and JetPakistan flow parity` | Guest/customer/agent E2E |
| **18H** | `docs(sabre): prepare controlled search and revalidation probe` | Live probe plan (no execution) |
| **18I** | `docs(sabre): finalize Phase 18 release evidence` + `test(sabre): complete Phase 18 regression matrix` | Gates, manifests, hashes |

---

## 20. Safety constraints (carried forward)

1. `SABRE_TICKETING_ENABLED=false` — no AirTicket, void, refund, EMD, LNIATA ops.
2. No PIA NDC logic changes.
3. No Sabre GDS/NDC assumption merge.
4. No production migrations, seeders, or live Create PNR in Phase 18 unless explicitly approved (18H probe: search/revalidation only).
5. Protected bookings 1–3 and attempts 4,5,7,8,9 untouched.
6. No automatic retry on ambiguous supplier outcomes.

---

## 21. 18A status

| Criterion | Status |
|-----------|--------|
| Read-only inventory complete | **PASS** |
| Defect register seeded | **PASS** |
| Runtime code modified | **NO** |
| Ready for 18B | **YES** |

**Next action:** Phase 18B — implement search cache-key builder and deterministic isolation tests per defect DEF-18-001, DEF-18-002, DEF-18-011.
