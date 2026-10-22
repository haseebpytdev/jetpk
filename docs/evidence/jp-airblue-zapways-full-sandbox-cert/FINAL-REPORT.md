# JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT — Final Report

**Date (UTC):** 2026-10-22  
**CURRENT_MAIN_SHA:** `de2d22195a844c2184e61ac72df0f0375b674b8e`  
**Endpoint:** `otatest4.zapways.com`  
**Environment:** `sandbox`  
**Protocol:** Zapways OTA `2.0`  
**PRODUCTION_AIRBLUE_CONNECTION_USED:** NO

## Executive result

| Area | Status |
|------|--------|
| TLS / mTLS / connectivity | **CERTIFIED** |
| Auth + Search (+ offers) | **CERTIFIED** |
| Search contract invariants | **PASS** |
| Booking A (book/retrieve/cancel) | **BLOCKED** — Zapways rejects `Gender="M"` (`DocumentTypeGender`); further NRE after Male/fare-basis probe |
| Booking B / Ticketing | **NOT ATTEMPTED** (blocked on Booking A) |
| Void | **NOT_SUPPORTED_CURRENT_OTA_CONTRACT** (`void_unsupported`) |
| Kill switches after loop | **OFF** |
| Production live activation | **NOT_STARTED** |

Runtime code fix prepared (not deployed): `fix/airblue-zapways-airbook-gender-farebasis`  
Maps `M`/`F` → `Male`/`Female`, NamePrefix, OperatingAirline, fare_basis←RBD fallback.

## Phase results

### Connection identity
- `AIRBLUE_CONNECTION_ID=22`
- `AIRBLUE_CONNECTION_ACTIVE=YES`
- `AIRBLUE_ENVIRONMENT=sandbox`
- `AIRBLUE_PROTOCOL_VERSION=2.0`
- `AIRBLUE_API_CHANNEL=zapways_ota`
- `AIRBLUE_ENDPOINT_HOST_ONLY=otatest4.zapways.com`

### mTLS
- Canonical paths restored/present under `/home/pkjetp/jetpk_secrets/zapways/`
- `TLS_CERT_EXISTS=YES` / `TLS_KEY_EXISTS=YES`
- `TLS_CERT_VALID_PEM=YES` / `TLS_KEY_VALID_PEM=YES` / `TLS_CERT_KEY_MATCH=YES`
- `TLS_CERT_READABLE_RUNTIME=YES` / `TLS_KEY_READABLE_RUNTIME=YES`

### Transport
- `ZAPWAYS_TEST_TLS=PASS`
- `ZAPWAYS_TEST_MTLS=PASS`
- `ZAPWAYS_TEST_CONNECTIVITY=PASS`

### Search
- `SANDBOX_SEARCH_CALLS=1` (matrix stopped on first offer-rich response)
- Route `ISB-KHI` date `2026-12-06` → `offers_count=6`
- `AUTH_ACCEPTED=YES` / `SEARCH_TRANSPORT_PASS=YES` / `SEARCH_PROVIDER_PASS=YES` / `SEARCH_OFFER_FOUND=YES`
- Invariants: normalization/price/tax/currency/markup/provenance = **PASS**

### Booking A
- QA fixture booking id `41` (`qa_fixture` / `local_qa` / synthetic passenger)
- `BOOKING_A_AIRBOOK_CALLS=1` via service → `supplier_http_error` HTTP **500**
- Fault: `Instance validation error: 'M' is not a valid value for DocumentTypeGender`
- Gender probes: `Male` / omit pass XSD then `Object reference not set…` (additional payload gap; fare_basis empty from live search)
- No PNR created → retrieve/cancel not executed
- `CUSTOMER_EMAIL_SENT=0`

### Ticketing / Void
- `ticketing_enabled=false` — entitlement not proven → ticketing not sent
- Void: code hard-deny `void_unsupported`; `AIRBLUE_VOID_PROVIDER_CALLS=0`

### Failure / redaction (offline)
- `AirBlueBookingUncertainStateTest` PASS (2)
- Filter `AirBlueExternalCallGateTest|AirBlueLiveCliGateTest|AirBlueClientRedactionTest|AirBlueVoid` PASS (14)
- XML builder gender/fare-basis unit tests PASS (2)

### Restore
```
AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED_AFTER=NO
AIRBLUE_CLI_LIVE_ENABLED_AFTER=NO
AIRBLUE_PUBLIC_SEARCH_ENABLED_AFTER=NO
AIRBLUE_SEARCH_CERTIFIED=NO
AIRBLUE_CERTIFICATION_STATUS=pending
AIRBLUE_PRODUCTION_LIVE_CREDENTIALS_CONFIGURED=NO
AIRBLUE_PRODUCTION_LIVE_ACTIVATION=NOT_STARTED
```

## Supplier call counts (approx / this loop)

| Op | Count |
|----|------:|
| Search (successful inventory) | 1 |
| AirBook attempts (failed) | ~8 diagnostic + 1 cert |
| Retrieve | 0 |
| Cancel | 0 |
| Ticketing | 0 |
| Void | 0 |
| Production-live endpoint | 0 |

## Certification labels

```
AIRBLUE_ZAPWAYS_SANDBOX_TLS=CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_AUTH=CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_SEARCH=CERTIFIED
AIRBLUE_ZAPWAYS_SANDBOX_BOOKING=BLOCKED_RUNTIME_FIX_PENDING_REVIEW
AIRBLUE_ZAPWAYS_SANDBOX_RETRIEVE=NOT_REACHED
AIRBLUE_ZAPWAYS_SANDBOX_CANCEL=NOT_REACHED
AIRBLUE_ZAPWAYS_SANDBOX_TICKETING=BLOCKED_ENTITLEMENT_NOT_PROVEN
AIRBLUE_ZAPWAYS_SANDBOX_VOID=NOT_SUPPORTED_CURRENT_OTA_CONTRACT
```

## Next action

1. Review/merge runtime PR for Gender/FareBasis/OperatingAirline/NamePrefix.  
2. Protected deploy of that SHA only.  
3. Resume Booking A→cancel and Booking B→ticketing entitlement probe on `otatest4` only.  
4. Do not enable public search or production credentials.
