# Flights results supplier identity contract closure

**Base main:** `605bf037a5a4e110d2c9e4da00547a2b03578869`  
**Branch:** `fix/results-supplier-identity-contract`  
**Zero provider calls**

## Failure

`BookingCheckoutProviderRoutingTest::test_flights_results_data_json_includes_supplier_identity_fields`

- Expected `supplier_connection_id` = Duffel connection id (observed `1` in local seed)
- Actual public JSON cast `(int)(… ?? 0)` = `0` because the key is absent

## Classification

- `PR132_CAUSED_ROUTING_FAILURE=NO` — PR132 did not touch `FlightController`, `FlightSearchResultStore`, or this test
- `ROUTING_FAILURE_PREEXISTING=YES` — same public sanitize contract existed at `a0e30df1` (`PublicFlightSearchSecurity::SENSITIVE_OFFER_KEYS` already strips `supplier_connection_id` / `supplier_offer_id`)

## Intended contract

Public `/flights/results/data` **must not** expose:

- `supplier_connection_id`
- `supplier_offer_id`

Authority: `PublicFlightSearchSecurity::SENSITIVE_OFFER_KEYS` + `Phase2PublicFlightSearchSecurityTest` (absence assertions).

Server-side store **must** retain identity for checkout reload via `search_id` + `flight_id` on `select_url`.

Public JSON **must** keep:

- `supplier_provider`
- `select_url` containing `search_id` + `flight_id`, without `raw_payload`

## Fix

**TEST-only alignment** in `BookingCheckoutProviderRoutingTest` — assert store-side identity + public absence of sensitive IDs. No FlightController / sanitize change (would conflict with Phase2 security).

## Gates

```
BOOKING_CHECKOUT_PROVIDER_ROUTING_TESTS=PASS (3/3)
AIRBLUE_CONNECTION_POLICY_TEST=PASS
AIRBLUE_OFFLINE_HTTP_GUARD=PASS
AIRBLUE_NO_GLOBAL_KILL_SWITCH=PASS
TOTAL_PROVIDER_CALLS=0
BOOKING_F_CHANGED=NO
```
