# JP-AIRBLUE-ZAPWAYS-READ-FIRST-TICKETING-RETRY — Final Report

**Date (UTC):** 2026-10-24  
**MAIN_SHA:** `d4258db6baba3894feca412bbff5fe80a7c6d1b1`  
**PR #123 reviewed head:** `3618dc26a9abdcbf3512b14bdccf9f41d3b2f4fd`  
**Release guard:** `40121042526` PASS  
**Production root:** `/home/pkjetp/jetpk_app`  
**Conn 22:** Zapways TEST `otatest4.zapways.com` v2.0 / `zapways_ota`

## Verdict

```
FINAL_STATUS=READ_STILL_BLOCKED
READ_SYNCED=NO
READ_ERROR_CODE=supplier_http_error
TICKETING_CALLS=0
REFUND_RUNTIME_PR=NOT_CREATED_TICKETING_FAILED
```

PR #123 deployed with SHA256 parity. Offline runtime smoke proved documented Read SOAPAction + PNR-only UniqueID. Exactly one live Read on Booking C still returned `supplier_http_error` (`synced=false`). Hard stop enforced: ticketing was not enabled and no AirDemandTicket was sent.

## Phase 1 — Deploy

Backup: `/home/pkjetp/backups/airblue-pr123-pre-20261024T184148Z`

| File | MAIN SHA256 | Parity |
|---|---|---|
| `config/suppliers.php` | `56071fcc…000fc36` | PASS |
| `AirBlueOtaXmlBuilder.php` | `69f2c96c…cbc6759` | PASS |
| `AirBlueRetrieveService.php` | `eb517d31…6720844` | PASS |
| `AirBlueTicketingService.php` | `06c2537e…23f37e4` | PASS |

```
PHP_SYNTAX=PASS
LARAVEL_BOOTSTRAP=PASS
HTTP_HEALTH=PASS (/up /login /lookup-booking = 200)
CONFIG_CACHE_PRE=NO
CONFIG_REFRESH_REQUIRED=NO
CONFIG_REFRESH_ACTION=NONE
OPTIMIZE_CLEAR=SKIPPED
PR123_DEPLOYED=YES
```

## Phase 2 — Offline smoke (no network)

```
RUNTIME_READ_SOAP_ACTION=https://ota.qa.zapways.com/Read
RUNTIME_READ_ENDPOINT=https://otatest4.zapways.com/v2.0/OTAAPI.asmx
READ_XML_PNR_ONLY=YES
READ_XML_TYPE14_ABSENT=YES
READ_XML_INSTANCE_ABSENT=YES
PLACEHOLDER_READ_SENT=NO
```

## Phase 3–4 — Isolation + Booking C

```
CONNECTION_BASELINE=PASS
BOOKING_C_FOUND=YES
BOOKING_C_MATCH_COUNT=1
BOOKING_C_ID_HASH=98010bd9270f
PNR_C_HASH=94b2bd04b019
BOOKING_C_CONNECTION_ID=22
BOOKING_C_PAYMENT_VERIFIED=YES
CONTEXT_SUPPLIER_PAYABLE=26000
CUSTOMER_PAYABLE=26500
TICKET_NUMBERS_PRESENT_PRE=NO
TICKETED_PRE=NO
```

## Phase 5 — Exactly one Read

```
READ_CALLS=1
READ_SYNCED=NO
READ_REASON=supplier_http_error
READ_ERROR_CODE=supplier_http_error
READ_HTTP=NON_SUCCESS_OR_UNKNOWN
```

Log corroboration (`airblue-2026-10-24.log`):

```
[2026-10-24 18:42:09] airblue.retrieve.failed booking_id=48 error_code=supplier_http_error
```

`info`-level `airblue.provider_call` details were not present in the daily log (prod `LOG_LEVEL` likely filters below warning), so HTTP status / fault snippet were not recovered without a forbidden second Read.

## Stop gate

```
TICKETING_CALLS=0
TICKETING_SUCCESS=NOT_RUN
TICKETING_ENABLED_TEST=NO
TICKETING_ENABLED_PRE=false
TICKETING_ENABLED_NOW=false
TICKETING_ENABLED_RESTORED=YES
```

Ticketing flag was never flipped in this loop (stop before Phase 8).

## Isolation restore

```
NON_CONN22_CONNECTION_ROWS_CHANGED=0
TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS=0
SABRE_CONNECTION_CHANGED=NO
PIA_NDC_CONNECTION_CHANGED=NO
IATI_CONNECTION_CHANGED=NO
DUFFEL_CONNECTION_CHANGED=NO
ONEAPI_CONNECTION_CHANGED=NO
```

## Refund

```
REFUND_RUNTIME_PR=NOT_CREATED_TICKETING_FAILED
REFUND_RUNTIME_HEAD=
REFUND_TESTS=
```

## Artifacts

| File | Role |
|---|---|
| `_deploy-pr123.sh` | Scoped PR123 deploy/config-cache/health procedure |
| `_phase-read-first-ticket.php` | Controlled Read-first certification probe |
| `_verify-restore.php` | Conn22 restore + supplier-isolation verification |

The final report records the observed runtime results. Empty raw transcript placeholders were removed before review. Raw PNR, Instance, credentials, certificates, passenger PII, and full SOAP are omitted.
