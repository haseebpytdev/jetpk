# AirBlue / Zapways — Non-live finalization

**Date (UTC):** 2026-10-17  
**Branch:** `supplier/airblue-zapways-finalization`  
**Protected main base:** `19b7607edbeba855570e3fed033d0060f3ed820d`  
**Mode:** FULL SOURCE AUDIT + OFFLINE CONTRACT CERTIFICATION + GAP REMEDIATION + AUTOMATED TESTING  

## Hard safety

```text
AIRBLUE_EXTERNAL_NETWORK_CALLS=0
AIRBLUE_LIVE_*_CALLS=0 (auth/search/offer/book/retrieve/cancel/ticket/void/ancillary)
SABRE_PNR_CREATE_CALLS=0
OTHER_SUPPLIER_MUTATIONS=0
LIVE_PAYMENT_CALLS=0
AIRBLUE_BOOKING_PAYLOAD_SENT=NO
AIRBLUE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
```

No artisan command that can reach Zapways was executed without the new fail-closed live gate (and live gate was never enabled in this workstream).

## Architecture map (Phase 1)

| Symbol | Class |
|--------|--------|
| `AIRBLUE_SEARCH_ENTRYPOINT` | `AirBlueFlightSearchService` |
| `AIRBLUE_SEARCH_ADAPTER` | `AirBlueFlightSupplierAdapter` |
| `AIRBLUE_CLIENT` | `AirBlueClient` (`callOta` only; `callNdc` fails closed) |
| `AIRBLUE_CONFIG_RESOLVER` | `AirBlueConfigResolver` |
| `AIRBLUE_PROTOCOL_GUARD` | `AirBlueProtocolGuard` |
| `AIRBLUE_PRICE_ENTRYPOINT` | `AirBlueOfferPriceService` (skip/log; no live price call) |
| `AIRBLUE_BOOKING_ENTRYPOINT` | `AirBlueBookingService` (+ `AirBlueSupplierBookingAdapter`) |
| `AIRBLUE_RETRIEVE_ENTRYPOINT` | `AirBlueRetrieveService` |
| `AIRBLUE_CANCEL_ENTRYPOINT` | `AirBlueCancelService` |
| `AIRBLUE_ANCILLARY_ENTRYPOINT` | `AirBlueAncillaryService` |
| `AIRBLUE_TICKET_ENTRYPOINT` | `AirBlueTicketingService` |
| `AIRBLUE_VOID_ENTRYPOINT` | `AirBlueVoidTicketService` (unsupported) |

Flow: search → adapter → protocol/channel → OTA XML builder → `AirBlueClient` → OTA parser → normalizer → JetPakistan offer → (price skip) → passenger payload → booking → attempt/uncertain guards → retrieve reconciliation.

## Protocol (Phase 2)

```text
ZAPWAYS_PROTOCOL_VERSIONS=2.0,3.0
DEFAULT_PROTOCOL=2.0
PROTOCOL_SELECTION_SOURCE=SupplierConnection.credentials.protocol_version
LEGACY_PROTOCOL_SUPPORTED=NO (Crane NDC retired / fails closed)
CURRENT_PROTOCOL_SUPPORTED=YES (Zapways OTA SOAP)
PROTOCOL_FALLBACK_ALLOWED=NO (invalid version throws)
```

## Config / secrets (Phase 3)

```text
AIRBLUE_CONFIG_COMPLETE=YES (resolver + config/suppliers.php airblue block)
AIRBLUE_SECRET_HANDLING=PASS (credentials on SupplierConnection; client sanitizeXml + SensitiveDataRedactor)
AIRBLUE_ENVIRONMENT_GUARD=PASS (sandbox vs live endpoint resolution; v3 live_enabled gate)
AIRBLUE_TLS_VERIFICATION=PASS (verify=true when client cert applied)
AIRBLUE_TIMEOUT_CONFIGURED=YES (timeout 60s / connect 10s)
AIRBLUE_LIVE_MODE_FAILS_CLOSED=YES (v3 live_enabled; CLI live gate; Crane fails closed)
```

## Network guard (Phase 4)

```text
AIRBLUE_TEST_NETWORK_GUARD=PASS
AIRBLUE_TEST_EXTERNAL_NETWORK_CALLS=0
```

Harness: `Tests\Support\AirBlue\AirBlueOfflineHttp` + `AirBlueOfflineTestCase` uses `Http::preventStrayRequests()` (no blanket `Http::fake()`).

## Fixes landed this loop (offline-certifiable)

1. **Zero-network test harness** for AirBlue unit tests.
2. **XML XXE / size guards** on `AirBlueOtaXmlParser` + `AirBlueXmlParser` (`LIBXML_NONET`, 5MB cap).
3. **Uncertain booking outcome** after transport timeout / unexpected failure — attempt status `uncertain`, supplier booking `pending_reconciliation`, **no auto-retry AirBook**.
4. **CLI live gates** on `airblue:test-search`, `test-booking`, `retrieve`, `cancel-commit`, `test-ticketing` — require `--allow-live` + `AIRBLUE_CLI_LIVE_ENABLED=true`.
5. **Wire `AirBlueConnectionSearchPolicy`** into `FlightSearchService` public search fan-out.
6. **Cancel normalizer bugfix** — no longer `unset($parsedResponse)` before reading it; failed SOAP/errors map to `cancellation_status=failed`.
7. **Baggage honesty** — stop inventing `20 KG` / `7 KG` when Zapways search response omits baggage (`null` = unavailable).
8. **Multi-city search** — emit explicit segments (max 4); reject empty/invalid multicity segments; reject infants > adults.
9. **Infant association metadata** — assign `associated_adult_rph` + validate infants ≤ adults in passenger payload (wire association attribute remains `BLOCKED_MISSING_PROVIDER_SPEC` until Zapways docs confirm).
10. **Retrieve reconciliation** — successful read with PNR+instance clears `booking_outcome=uncertain` → `reconciled` and updates attempt/supplier booking rows.

## Command classification (Phase 28)

| Command | Class | Guard |
|---------|-------|-------|
| `airblue:audit-docs` | OFFLINE_ONLY | n/a |
| `airblue:audit-reference-code` | OFFLINE_ONLY | n/a |
| `airblue:health` | OFFLINE_ONLY | config-only |
| `airblue:ancillary-probe` | OFFLINE_ONLY | no HTTP |
| `airblue:test-offer-price` | FIXTURE_CAPABLE | fixture; no supplier call |
| `airblue:cancel-preview` | OFFLINE_ONLY | unsupported |
| `airblue:test-ticket-preview` | OFFLINE_ONLY | unsupported |
| `airblue:void-ticket` | OFFLINE_ONLY | unsupported |
| `airblue:test-search` | LIVE_NETWORK_CAPABLE | `--allow-live` + env |
| `airblue:retrieve` | LIVE_NETWORK_CAPABLE | `--allow-live` + env |
| `airblue:test-booking` | MUTATING_LIVE_CAPABLE | dry-run offline; live gated |
| `airblue:cancel-commit` | MUTATING_LIVE_CAPABLE | `--allow-live` + env |
| `airblue:test-ticketing` | MUTATING_LIVE_CAPABLE | `--allow-live` + env |

## Deferred go-live gates

Anything requiring real Zapways HTTP:

- auth/connectivity probe
- live search latency/volume
- live offer-price (if provider exposes one beyond current skip model)
- controlled booking + retrieve
- cancel commit on test booking (if commercially permitted)
- ticketing/void only if launch policy later enables product ticketing

```text
AIRBLUE_TICKETING_CURRENTLY_LIVE_ENABLED=NO
AIRBLUE_AUTO_TICKETING=NO
```

## Production read-only config (Phase 31)

Booleans only — no secrets, no Zapways connectivity.

```text
AIRBLUE_CONNECTION_RECORD_PRESENT=YES
AIRBLUE_CONNECTION_COUNT=1
AIRBLUE_CONNECTION_STATUS=active_flag
AIRBLUE_CONNECTION_ACTIVE=YES
AIRBLUE_ENVIRONMENT=sandbox
AIRBLUE_PROTOCOL_VERSION_CONFIGURED=YES(2.0)
AIRBLUE_API_CHANNEL_CONFIGURED=YES(zapways_ota)
AIRBLUE_ENDPOINT_CONFIGURED=YES
AIRBLUE_BASE_URL_OVERRIDE=YES
AIRBLUE_USERNAME_CONFIGURED=YES
AIRBLUE_PASSWORD_CONFIGURED=YES
AIRBLUE_CERTIFICATION_STATUS=pending
AIRBLUE_BOOKING_FEATURE_ENABLED=YES
AIRBLUE_TICKETING_FEATURE_ENABLED=NO
AIRBLUE_CANCEL_FEATURE_ENABLED=NO
AIRBLUE_OTA_V3_LIVE_ENABLED_CONFIG=NO
AIRBLUE_CLI_LIVE_ENABLED_ENV=NO
```

**Deploy note:** wiring `AirBlueConnectionSearchPolicy` into public search excludes v2 connections with `certification_status=pending`. Production connection id=22 is currently `pending`, so after this PR deploys AirBlue offers will not appear in public search until certification is advanced or `search_certified=true` is set intentionally.

## Readiness (Phase 33)

```text
AIRBLUE_CODE_READINESS=STRENGTHENED
AIRBLUE_OFFLINE_CONTRACT_READINESS=PARTIAL
AIRBLUE_UI_READINESS=PARTIAL
AIRBLUE_BOOKING_LOGIC_READINESS=PARTIAL
AIRBLUE_SECURITY_READINESS=STRENGTHENED
AIRBLUE_PRODUCTION_CONFIG_READINESS=PASS_RO_BOOLEANS
AIRBLUE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
AIRBLUE_OVERALL_READINESS=READY_AWAITING_LIVE_CERTIFICATION
```

## Test evidence

```text
AIRBLUE_UNIT+CLI suite: tests=108 passed=108 assertions=301
AIRBLUE_TEST_EXTERNAL_NETWORK_CALLS=0
```

See `_phpunit-airblue-finalization.txt`.

## Final report matrix (Phase 35)

```text
PROTECTED_MAIN_SHA=19b7607edbeba855570e3fed033d0060f3ed820d
BRANCH=supplier/airblue-zapways-finalization

AIRBLUE_IMPLEMENTATION_PRESENT=PASS
AIRBLUE_ZAPWAYS_PROTOCOL_IMPLEMENTED=PASS
ZAPWAYS_PROTOCOL_VERSIONS=2.0,3.0
DEFAULT_PROTOCOL=2.0

AIRBLUE_CONFIG_COMPLETE=YES
AIRBLUE_SECRET_HANDLING=PASS
AIRBLUE_ENVIRONMENT_GUARD=PASS
AIRBLUE_TLS_VERIFICATION=PASS
AIRBLUE_LIVE_MODE_FAILS_CLOSED=YES

AIRBLUE_TEST_NETWORK_GUARD=PASS
AIRBLUE_TEST_EXTERNAL_NETWORK_CALLS=0

AIRBLUE_SEARCH_ONEWAY=PASS
AIRBLUE_SEARCH_RETURN=PASS
AIRBLUE_SEARCH_MULTICITY=PASS
AIRBLUE_ADT_PAYLOAD=PASS
AIRBLUE_CHD_PAYLOAD=PASS
AIRBLUE_INF_PAYLOAD=PASS

AIRBLUE_PRICE_NORMALIZATION=PASS
AIRBLUE_CURRENCY_INVARIANT=PASS
AIRBLUE_TAX_INVARIANT=PASS
AIRBLUE_MARKUP_SINGLE_APPLICATION=PASS
AIRBLUE_ROUNDING=PARTIAL

AIRBLUE_OFFER_TAMPER_PROTECTION=PARTIAL
AIRBLUE_STALE_OFFER_DETECTION=NOT_SUPPORTED
AIRBLUE_REVALIDATION_SAME_PRICE=PARTIAL
AIRBLUE_REVALIDATION_PRICE_CHANGE=NOT_SUPPORTED
AIRBLUE_REVALIDATION_UNAVAILABLE=PARTIAL
AIRBLUE_REVALIDATION_TIMEOUT=NOT_SUPPORTED

AIRBLUE_BAGGAGE_NORMALIZATION=PASS
AIRBLUE_FARE_RULE_NORMALIZATION=NOT_SUPPORTED
AIRBLUE_ANCILLARY_NORMALIZATION=PARTIAL

AIRBLUE_PASSENGER_ADT=PASS
AIRBLUE_PASSENGER_CHD=PASS
AIRBLUE_PASSENGER_INF=PASS
AIRBLUE_INFANT_ASSOCIATION=PARTIAL

AIRBLUE_BOOKING_PAYLOAD_BUILD=PASS
AIRBLUE_BOOKING_PAYLOAD_SENT=NO

AIRBLUE_DUPLICATE_BOOKING_GUARD=PASS
AIRBLUE_CONCURRENT_BOOKING_GUARD=PARTIAL
AIRBLUE_AUTO_RETRY_UNKNOWN_OUTCOME=NO
AIRBLUE_UNCERTAIN_STATE_MODEL=PASS
AIRBLUE_RECONCILIATION_PATH=PASS

AIRBLUE_BOOKING_RESPONSE_NORMALIZATION=PASS
AIRBLUE_REFERENCE_PERSISTENCE=PASS
AIRBLUE_STATUS_MAPPING=PASS
AIRBLUE_RETRIEVE_NORMALIZATION=PASS
AIRBLUE_RETRIEVE_STATE_TRANSITION_SAFETY=PASS

AIRBLUE_CANCEL_COMMIT_LIVE_GATED=PASS
AIRBLUE_TICKETING_CURRENTLY_LIVE_ENABLED=NO
AIRBLUE_AUTO_TICKETING=NO
AIRBLUE_TICKETING_GATE=PARTIAL
AIRBLUE_VOID_GATE=NOT_SUPPORTED

AIRBLUE_SEARCH_RETRY_POLICY=NONE
AIRBLUE_PRICE_RETRY_POLICY=NONE
AIRBLUE_BOOKING_RETRY_POLICY=NONE_BLOCK_UNCERTAIN
AIRBLUE_CANCEL_RETRY_POLICY=NONE
AIRBLUE_TICKETING_RETRY_POLICY=NONE

AIRBLUE_XML_XXE_PROTECTION=PASS
AIRBLUE_XML_SIZE_GUARD=PASS
AIRBLUE_XML_MALFORMED_HANDLING=PASS

AIRBLUE_SECRET_LOGGING=NO
AIRBLUE_PASSENGER_PII_LOGGING=NO

AIRBLUE_AGENCY_ISOLATION=PASS
AIRBLUE_CONNECTION_ISOLATION=PASS
AIRBLUE_DISABLED_CONNECTION_EXCLUDED=PASS

AIRBLUE_SEARCH_AGGREGATION=PASS
AIRBLUE_FAILURE_ISOLATION=PASS
AIRBLUE_OFFER_PROVENANCE=PASS

AIRBLUE_B2C_RESULT_UI=PARTIAL
AIRBLUE_RETURN_PAIRED_VIEW=PASS
AIRBLUE_SEGMENTED_VIEW=PASS
AIRBLUE_BAGGAGE_UI=PARTIAL
AIRBLUE_FARE_POLICY_UI=PARTIAL
AIRBLUE_CHECKOUT_UI=PARTIAL
AIRBLUE_B2B_UI=PARTIAL

AIRBLUE_STATE_MACHINE=PARTIAL
AIRBLUE_FINANCIAL_INVARIANT=PARTIAL

AIRBLUE_UNIT_PASS=108
AIRBLUE_UNIT_FAIL=0
AIRBLUE_FEATURE_PASS=N/A_THIS_LOOP
AIRBLUE_FEATURE_FAIL=0
AIRBLUE_ASSERTIONS=301
AIRBLUE_FRONTEND_PASS=N/A_CONTRACT_ONLY
AIRBLUE_FRONTEND_FAIL=0

AIRBLUE_CONNECTION_RECORD_PRESENT=YES
AIRBLUE_CONNECTION_STATUS=active_flag
AIRBLUE_ENVIRONMENT=sandbox
AIRBLUE_PROTOCOL_VERSION_CONFIGURED=YES(2.0)
AIRBLUE_ENDPOINT_CONFIGURED=YES
AIRBLUE_USERNAME_CONFIGURED=YES
AIRBLUE_PASSWORD_CONFIGURED=YES
AIRBLUE_BOOKING_FEATURE_ENABLED=YES
AIRBLUE_TICKETING_FEATURE_ENABLED=NO
AIRBLUE_CANCEL_FEATURE_ENABLED=NO

AIRBLUE_CODE_READINESS=STRENGTHENED
AIRBLUE_OFFLINE_CONTRACT_READINESS=PARTIAL
AIRBLUE_UI_READINESS=PARTIAL
AIRBLUE_BOOKING_LOGIC_READINESS=PARTIAL
AIRBLUE_SECURITY_READINESS=STRENGTHENED
AIRBLUE_PRODUCTION_CONFIG_READINESS=PASS_RO_BOOLEANS
AIRBLUE_LIVE_API_CERTIFICATION=DEFERRED_GO_LIVE_GATE
AIRBLUE_OVERALL_READINESS=READY_AWAITING_LIVE_CERTIFICATION

AIRBLUE_EXTERNAL_NETWORK_CALLS=0
AIRBLUE_LIVE_AUTH_CALLS=0
AIRBLUE_LIVE_SEARCH_CALLS=0
AIRBLUE_LIVE_OFFER_PRICE_CALLS=0
AIRBLUE_LIVE_BOOKING_CALLS=0
AIRBLUE_LIVE_RETRIEVE_CALLS=0
AIRBLUE_LIVE_CANCEL_CALLS=0
AIRBLUE_LIVE_TICKETING_CALLS=0
AIRBLUE_LIVE_VOID_CALLS=0

NEW_DEFECTS=OPS-DEF-0028
DEFERRED_GO_LIVE_GATES=live_auth;live_search;live_book_retrieve;live_cancel_commit;ticketing_void_if_policy_enables;infant_wire_association_spec;certification_status_for_public_search
NEXT_REQUIRED_ACTION=Open/review PR (no auto-merge); live Zapways cert only in launch window
```

## Next required action

1. Create/review PR from `supplier/airblue-zapways-finalization` — **do not auto-merge**.
2. Before deploy: set production AirBlue `certification_status` / `search_certified` intentionally (currently `pending` → excluded from public search).
3. Live Zapways certification only in the production-launch window.
