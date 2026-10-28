# Flights results supplier identity contract closure

**Base main:** `605bf037a5a4e110d2c9e4da00547a2b03578869`  
**Branch:** `fix/results-supplier-identity-contract`  
**PR:** #133  
**Zero provider calls**

## Failure 1 — routing assertion

`BookingCheckoutProviderRoutingTest::test_flights_results_data_json_includes_supplier_identity_fields`

- Expected `supplier_connection_id` = Duffel connection id (observed `1` in local seed)
- Actual public JSON cast `(int)(… ?? 0)` = `0` because the key is absent

### Classification

- `PR132_CAUSED_ROUTING_FAILURE=NO`
- `ROUTING_FAILURE_PREEXISTING=YES` — Phase2 sanitize already strips those keys

### Fix

TEST-only: assert store-side identity + public absence of sensitive IDs.

## Failure 2 — Phase2 harness (`all() on array`)

4 errors in `Phase2PublicFlightSearchSecurityTest` during `storeSearch()` HTML `/flights/results` bootstrap (mock `FlightSearchService`), **before** `/flights/results/data`.

```
ERROR_OCCURS_DURING_STORESEARCH_PAGE_BOOTSTRAP=YES
ERROR_OCCURS_DURING_RESULTS_DATA_ENDPOINT=NO
SECURITY_RUNTIME_CHANGE_REQUIRED=NO
TEST_HARNESS_ALIGNMENT=YES
PHASE2_ERRORS_CLOSED=YES
```

### Fix

`storeSearch()` now seeds via `FlightSearchResultStore::store(...)` only. No runtime app changes.

## Intended contract

Public `/flights/results/data` **must not** expose:

- `supplier_connection_id`
- `supplier_offer_id`
- `raw_payload`
- `fare_verification_digest`
- (other `SENSITIVE_OFFER_KEYS`)

Server-side store **must** retain identity for checkout via `select_url` (`search_id` + `flight_id`).

## Gates

```
BOOKING_CHECKOUT_PROVIDER_ROUTING_TESTS=PASS (3/3)
PHASE2_SECURITY_TESTS=PASS (10/10)
AIRBLUE_CONNECTION_POLICY_TEST=PASS
AIRBLUE_OFFLINE_HTTP_GUARD=PASS
AIRBLUE_NO_GLOBAL_KILL_SWITCH=PASS
COMBINED_CLOSURE_SUITE=PASS (34/34)
TOTAL_PROVIDER_CALLS=0
BOOKING_F_CHANGED=NO
FINAL_CERTIFICATION_PACKAGE=PENDING_PR133_MERGE
```
