# AirBlue / Zapways — Finalization 02 (persistence + kill switch)

**Date (UTC):** 2026-10-19  
**Branch:** `supplier/airblue-zapways-finalization`  
**PR:** #104  
**Mode:** OFFLINE ONLY — fixtures/mocks; zero Zapways HTTP  

## Hard safety

```text
AIRBLUE_EXTERNAL_NETWORK_CALLS=0
AIRBLUE_EXTERNAL_CALLS_ENABLED_DEFAULT=NO
AIRBLUE_CLIENT_FAILS_CLOSED_BEFORE_HTTP=YES
AIRBLUE_DISABLED_GATE_CAUSES_UNCERTAIN_BOOKING=NO
```

## Fixes

1. **Single persistence owner** — `AirBlueBookingService` returns DTO only; `SupplierBookingService` owns canonical `SupplierBooking` + `SupplierBookingAttempt`.
2. **Uncertain ownership** — transport timeout → `manual_review` / `booking_outcome_uncertain`; outer updates same processing attempt → `uncertain` + one `pending_reconciliation` row; preflight blocks retry.
3. **Central kill switch** — `config('suppliers.airblue.external_calls_enabled')` default `false`; `AirBlueClient::callOta` throws `AirBlueValidationException` / `external_calls_disabled` before HTTP.
4. **Ticketing gate** — requires `credentials.ticketing_enabled` AND external-calls gate.
5. **Retrieve reconcile** — writes canonical `supplier_reference` / `pnr` (not unsupported `provider_reference`).

## Test evidence

```text
AIRBLUE_UNIT_PASS=130
AIRBLUE_UNIT_FAIL=0
AIRBLUE_FEATURE_PASS=13
AIRBLUE_FEATURE_FAIL=0
AIRBLUE_ASSERTIONS=505
GENERIC_SUPPLIER_BOOKING_PASS=24
GENERIC_SUPPLIER_BOOKING_FAIL=0
AIRBLUE_EXTERNAL_NETWORK_CALLS=0
```

Artifacts:

- `_phpunit-airblue-finalization-02.txt` / `.xml`
- `_phpunit-generic-supplier-booking-02.txt` / `.xml`

## Readiness (offline-certifiable)

```text
AIRBLUE_EXTERNAL_CALL_GATE=PASS
AIRBLUE_DUPLICATE_PERSISTENCE=PASS
AIRBLUE_CONCURRENT_BOOKING_GUARD=PASS
AIRBLUE_UNCERTAIN_PIPELINE=PASS
AIRBLUE_RETRIEVE_RECONCILIATION=PASS
AIRBLUE_STATE_MACHINE=PASS
AIRBLUE_FINANCIAL_INVARIANT=PASS
AIRBLUE_TICKETING_GATE=PASS
AIRBLUE_LOCAL_TAMPER_PROTECTION=PASS
AIRBLUE_PROVIDER_REVALIDATION=DEFERRED_GO_LIVE_GATE
AIRBLUE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
AIRBLUE_OVERALL_READINESS=READY_AWAITING_LIVE_CERTIFICATION
```

## Deferred go-live gates

- live auth/search/book/retrieve/cancel against Zapways
- provider offer-price / stale-price revalidation (no separate DoOfferPrice in current contract)
- intentional production `certification_status` / `search_certified` for public search
- infant wire association attribute pending provider spec
- product ticketing only if launch policy enables `ticketing_enabled` AND external calls
