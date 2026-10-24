# JP-AIRBLUE-ZAPWAYS-TICKETING-CERTIFICATION — Final Report

**Date (UTC):** 2026-10-24  
**MAIN_SHA:** `bc676a7b18c8a74859311cc38f523eb0c057be77`  
**PR #120 reviewed head:** `31f59bb5668bbd6080713d33e6fbfabf8232d9f0` (merged into main)  
**Exact-head release guard:** `39833343134` PASS (pre-authorized)  
**Conn 22:** Zapways TEST `otatest4.zapways.com` v2.0 / `zapways_ota`  
**Production root:** `/home/pkjetp/jetpk_app`

## Verdict

```
FINAL_AIRBLUE_TICKETING_CERTIFICATION=PARTIAL_PROVIDER_REJECTION
TICKETING_SUCCESS=NO
TICKETING_CLASSIFICATION=PROVIDER_REJECTION
REFUND_RUNTIME_PR=NOT_CREATED_TICKETING_FAILED
```

Payment-context persistence and AirDemandTicket PaymentInfo amount separation are proven. Exactly one AirDemandTicket was sent. Provider returned a business `provider_error` (mapped safe message: "Fare or booking is unavailable."). No second provider ticket call. Conn 22 `ticketing_enabled` restored. Offline full-refund PR deferred until ticketing succeeds.

## Phase 1 — PR120 deploy

Deployed **only** `app/Services/Suppliers/AirBlue/AirBlueBookingService.php` from MAIN.

```
MAIN_SHA=bc676a7b18c8a74859311cc38f523eb0c057be77
MAIN_SHA256=a630f48d2b5eb02dd29faa91184106b8862ed7af1d526410612141a11b632042
BACKUP=/home/pkjetp/backups/airblue-pr120-pre-20261024T164450Z
PREDEPLOY_SHA256=a630f48d2b5eb02dd29faa91184106b8862ed7af1d526410612141a11b632042
POSTDEPLOY_SHA256=a630f48d2b5eb02dd29faa91184106b8862ed7af1d526410612141a11b632042
BOOKING_SERVICE_PARITY=PASS
PHP_SYNTAX=PASS
LARAVEL_BOOTSTRAP=PASS
HTTP_HEALTH=PASS
PR120_DEPLOYED=YES
OPTIMIZE_CLEAR=SKIPPED
```

Note: production file already matched MAIN SHA256 before this deploy; deploy re-applied and re-verified parity.

## Phase 2 — Local tests

```
PAYMENT_CONTEXT_TESTS=PASS
AIRBLUE_FOCUSED_TESTS=PASS
PHPUNIT=27 tests / 135 assertions (deprecations only)
```

Focused filter: `AirBlueBookingPaymentContextTest`, `AirBlueOtaXmlBuilderTest`, `AirBlueOtaXmlParserTest`, `AirBlueClientSoapActionTest`, `AirBlueClientRedactionTest`.

Regression proven: supplier_total=26000 → AirDemandTicket Amount="26000.00"; customer_payable=26500 must not be used.

## Phase 3 — Supplier isolation

```
NON_CONN22_BASELINE_CREATED=YES
CONN22_ISOLATION=PASS
CONN22_PROVIDER=airblue
CONN22_ENV=sandbox
CONN22_HOST=otatest4.zapways.com
CONN22_PROTOCOL=2.0
CONN22_CHANNEL=zapways_ota
TICKETING_ENABLED_PRE=false
```

Post-restore verification with **same** fingerprint algorithm as baseline:

```
NON_CONN22_CONNECTION_ROWS_CHANGED=0
SABRE_CONNECTION_CHANGED=NO
PIA_NDC_CONNECTION_CHANGED=NO
IATI_CONNECTION_CHANGED=NO
DUFFEL_CONNECTION_CHANGED=NO
ONEAPI_CONNECTION_CHANGED=NO
TICKETING_ENABLED_RESTORED=YES
```

Note: lifecycle probe initially printed `NON_CONN22_CONNECTION_ROWS_CHANGED=5` due to a fingerprint mismatch (`base_url` included in post-check only). Independent same-algo verify proves **0** real non-22 changes.

## Phase 4–8 — Booking C (fresh)

```
BOOKING_C_SEARCH=PASS
BOOKING_C_OFFER_COUNT=6
RBD=U
FARE_BASIS=EFTAOWU
FARE_BASIS_SOURCE=fare_info_attr
BASE=18030
TAX=7970
SUPPLIER_TOTAL=26000
CUSTOMER_PAYABLE=26500
BOOKING_C_CHECKOUT=PASS
BOOKING_C_MANUAL_PAYMENT=PASS
BOOKING_C_PAYMENT_VERIFICATION=PASS
BOOKING_C_AIRBOOK_CALLS=1
BOOKING_C_AIRBOOK=PASS
AIRBOOK_C_HTTP=200
PNR_C_PRESENT=YES
INSTANCE_C_PRESENT=YES
PNR_C_HASH=94b2bd04b019
BOOKING_ID_HASH=98010bd9270f
AIRBLUE_CONTEXT_SUPPLIER_PAYABLE_PRESENT=YES
EXPECTED_SUPPLIER_PAYABLE=26000
CONTEXT_SUPPLIER_PAYABLE=26000
SUPPLIER_PAYABLE_MATCH=YES
CUSTOMER_AND_SUPPLIER_TOTAL_DIFFER=YES
CONTEXT_PAYMENT_CURRENCY=PKR
CONTEXT_PAYMENT_TYPE=Cash
```

QA markers: `qa_fixture`, `local_qa`, `airblue_zapways_test`, `exclude_from_financial_kpi`. Synthetic passenger only.

## Phase 9–10 — Pre-ticket retrieve / seats

Probe printed:

```
PRE_TICKET_RETRIEVE=PASS
TICKET_NUMBERS_PRESENT_PRE=NO
SEAT_SELECTION_REQUIRED=NO
SEAT_MAP_CALLS=0
RETRIEVE_KEYS=synced,reason
```

**Evidence correction:** `airblue-2026-10-24.log` shows both Read attempts for booking 48 failed:

```
airblue.retrieve.failed booking_id=48 error_code=supplier_http_error  (x2)
```

`AirBlueRetrieveService` returns `synced=false, reason=supplier_http_error` on failure. Probe overstated PASS by checking local AirBook PNR/instance only, not `synced===true`.  

Corrected evidence:

```
PRE_TICKET_RETRIEVE_ACTUAL=FAIL_SUPPLIER_HTTP_ERROR
POST_TICKET_RETRIEVE_ACTUAL=FAIL_SUPPLIER_HTTP_ERROR
PRE_TICKET_LOCAL_UNTICKETED=YES
```

Ticketing still proceeded because the probe gate did not stop on `synced=false` (probe defect for future hardening; no retry performed in this loop).

## Phase 11–13 — Ticketing

```
TICKETING_ENABLED_PRE=false
CONN22_BACKUP_CREATED=YES
TICKETING_ENABLED_TEST=YES
TICKET_XML_PAYMENT_AMOUNT=26000.00
TICKET_XML_PAYMENT_CURRENCY=PKR
TICKET_XML_PAYMENT_TYPE=Cash
TICKET_XML_SUPPLIER_AMOUNT_MATCH=YES
TICKET_XML_CUSTOMER_PAYABLE_USED=NO
TICKETING_CALLS=1
TICKETING_HTTP=NON_SUCCESS
TICKETING_SUCCESS=NO
TICKETING_STATUS=failed
TICKETING_ERROR_CODE=provider_error
TICKETING_ERROR_SANITIZED=Fare or booking is unavailable.
TICKETING_CLASSIFICATION=PROVIDER_REJECTION
```

Classification note: probe initially labeled `PROVIDER_NRE` because the safe message contains "unavailable". Runtime mapping shows `provider_error` is thrown when Zapways returns HTTP 2xx with `Errors[]` (not Object-reference NRE). Correct class = **PROVIDER_REJECTION**.

No automatic retry. No second AirDemandTicket.

## Phase 14–16 — Post checks / restore

```
PROVIDER_TICKET_CONFIRMED=NO
LOCAL_TICKET_CONFIRMED=NO
TICKET_COUNT=0
TICKET_HASHES=NONE
DUPLICATE_TICK_GUARD=PASS
SECOND_TICKETING_PROVIDER_CALLS=0
TICKETING_ENABLED_RESTORED=YES
TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS=0
```

Duplicate guard exercised via local already-ticketed injection path (no second provider call).

## Phase 17 — Failure diagnosis (no retry)

| Signal | Value |
|---|---|
| AirBook | PASS (HTTP 200, PNR+Instance) |
| Payment context | PASS (26000 / PKR / Cash) |
| Wire PaymentInfo | PASS (26000.00 ≠ 26500) |
| Read sync | FAIL (`supplier_http_error`) |
| AirDemandTicket | FAIL (`provider_error`) |
| Known JetPakistan contract defect | **Not proven** — builder matches fixture shape (POS + BookingReferenceID + PaymentInfo Cash) |
| Refund offline PR | **Not built** (ticketing did not succeed) |

Possible provider-side causes (not varied/retried here): TEST ticketing entitlement, inventory/fare state after AirBook, or Read/ticket channel instability on sandbox. Escalate to Zapways with sanitized correlation only if owner authorizes a new loop.

## Phase 18–21 — Refund

```
VOID_OPERATION_SUPPORTED=NO
PROVIDER_POST_TICKET_REVERSAL=AirBookModify
FULL_REFUND_MODIFICATION_TYPE=1
PARTIAL_REFUND_MODIFICATION_TYPE=2
REFUND_RUNTIME_PR=
REFUND_RUNTIME_HEAD=
REFUND_CHANGED_FILES=
REFUND_TESTS=
REFUND_RELEASE_GUARD=
```

Ticketed TEST PNR not available; refund implementation deferred.

## Mail suppression note

Probe called `Mail::fake()`, but production logs show outbound SMTP notifications for Booking C status/payment/supplier events to configured admin recipients. Queue/worker path likely bypassed the in-process fake. Future cert probes should disable notification dispatch or use a dedicated no-notify path.

## Artifacts

| File | Role |
|---|---|
| `_deploy-pr120.sh` | Scoped PR120 deploy/health procedure |
| `_phase3-conn-snapshot.php` | Supplier-connection baseline probe |
| `_phase4-16-booking-c.php` | Booking C lifecycle probe used for the controlled TEST call |
| `_verify-conn-restore.php` | Independent same-algorithm isolation/restore verification |
| `_inspect-booking-c.php` | Read-only post-run booking/attempt inspection helper |

The final report records the observed results. Empty transcript placeholders were intentionally removed before review. Raw PNR, ticket numbers, credentials, certificates, passenger PII, and full authenticated SOAP are omitted.
