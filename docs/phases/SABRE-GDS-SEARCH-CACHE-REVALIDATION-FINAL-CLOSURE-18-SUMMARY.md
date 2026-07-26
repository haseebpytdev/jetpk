# Phase 18 — Sabre GDS Search, Cache, Revalidation, and Checkout Closure Summary

## Phase name
SABRE-GDS-SEARCH-CACHE-REVALIDATION-FINAL-CLOSURE-18

## Branch name
`main`

## Objective
Close Phase 18 search cache isolation, stale-offer safety, shopping normalization, authoritative revalidation linkage, JetPakistan filter/checkout continuity, and release evidence gates (18A–18I).

## Starting commit
`29f21e51e1241eb6f74b990d006abe73848a130f` — `docs: record Phase 17F Create PNR safety closure`

## Phase 18 commits (in order)

| SHA | Message |
|-----|---------|
| `c6303f1` | `docs(sabre): inventory Phase 18 search and cache closure` |
| `5c3e8eb` | `fix(sabre): harden search cache key isolation` |
| `a676320` | `fix(sabre): reject stale offers and stabilize cache reconstruction` |
| `0262be1` | `fix(sabre): close shopping normalization matrix gaps` |
| `b48e2ff` | `fix(sabre): enforce authoritative revalidation linkage` |
| `a1d3a7f` | `fix(flights): align filters and JetPakistan checkout continuity` |
| `b1c6b6f` | `fix(checkout): close Sabre role and JetPakistan flow parity` |
| `e37a573` | `docs(sabre): prepare controlled search and revalidation probe` |
| `e77cdee` | `docs(sabre): finalize Phase 18 release evidence` |

## Included scope

### 18B — Cache key isolation
- `FlightSearchCriteriaCacheKey` deterministic fingerprint (tenant, agency, lane, filters, pax, cabin, dates, nearby, direct, flexible)
- `FlightSearchSupplierResultCache` with config-driven TTL (`ota-flights.search_result_cache.ttl_seconds`, default **300**)
- Nearby strip cache key alignment

### 18C — Stale offer safety
- `FlightSearchResultStore` schema version `v1`, fail-closed malformed payloads
- `forSelection=true` blocks stale offers at store read

### 18D — Shopping normalization matrix
- Fixture-driven one-way/return/pax/cabin structural invariant tests (14 scenarios)

### 18E — Authoritative revalidation linkage
- `FlightController` blocks stale revalidation (410) and stale results selection
- Feature tests through live revalidation path with `Http::fake` (linkage fixture)

### 18F — Filters and JetPakistan continuity
- `flexible_dates` ±1 day outbound expansion
- Public search checkbox + cache fingerprint dimension
- Stale results API sets `can_book=false`

### 18G — Checkout role parity
- Guest/customer/agent JetPakistan review/passengers shell tests (fake HTTP)

### 18H — Live probe plan
- Document only; **not executed**

### 18I — Integrated gate
- PHPUnit Phase 18 matrix: **47 passed, 0 failed, 0 skipped**
- Route audit: **pass=22 fail=0**
- CMS route safety: **fail=0**
- PHP lint: all runtime files clean
- Manifests and SHA-256 in `docs/phases/PHASE18-*`

## Excluded scope
- Live Sabre probe execution (18H plan only)
- Ticketing / AirTicket / void / refund
- PIA NDC changes
- Create PNR path changes
- Production deploy (plan only)

## Tests executed

```bash
php artisan test --filter="FlightSearchCriteriaCacheKeyTest|FlightSearchSupplierResultCacheTest|FlightSearchResultStoreStaleOfferTest|SabreGdsShoppingNormalizationMatrixPhase18D|SabreGdsAuthoritativeRevalidationLinkagePhase18E|FlightSearchFlexibleDatesPhase18F|SabreGdsCheckoutRoleParityPhase18G"
```

**Result:** 47 tests, 249 assertions, exit 0

```bash
php artisan ota:route-page-health-audit --all
php artisan jetpk:cms-route-safety-audit
```

**Result:** route `fail=0`; CMS `fail=0`

## Defect register totals

| Disposition | Count |
|-------------|-------|
| fixed | 8 |
| intentionally deferred | 4 |
| requires approved live probe | 1 |
| blocked by LNIATA | 1 |
| future Sabre NDC scope | 1 |

See `docs/phases/PHASE18-DEFECT-REGISTER.tsv`

## Safety confirmations

| Check | Status |
|-------|--------|
| `SABRE_TICKETING_ENABLED` default false | **CONFIRMED** |
| PIA NDC files modified | **NO** |
| Production mutation | **NO** |
| Live probe executed | **NO** |
| Push to `jetpk/main` | After 18I commits |

## Known limitations
- `MAX_STORED_OFFERS=150` unchanged (DEF-18-003 deferred)
- Branded-fare revalidation bypass unchanged (DEF-18-007 deferred)
- Live search/revalidation requires approved 18H probe

## Rollback
See `docs/phases/PHASE18-ROLLBACK-PLAN.md`

## Final status
**PASS** — Phase 18 local closure complete pending final doc commits and `git push jetpk main`.
