# JP-AIRBLUE-ZAPWAYS-READ-DIAGNOSTIC-PROBE — Final Report

**Date (UTC):** 2026-10-24  
**MAIN_SHA:** `4f0f73ead66a2a639331b4c5ffb586bcf5b56027`  
**PR #125 reviewed head:** `4f55868a1ad4bff2392f05ed0e1fbb0ef02dd2de`  
**Release guard:** `40122513696` PASS  
**Production root:** `/home/pkjetp/jetpk_app`  
**Conn 22:** Zapways TEST `otatest4.zapways.com` v2.0 / `zapways_ota`

## Verdict

```
FINAL_STATUS=READ_DIAGNOSTIC_CAPTURED
READ_SYNCED=NO
READ_REASON=supplier_http_error
READ_HTTP_STATUS=500
READ_FAULT_CLASS=soap_fault
READ_CLASSIFICATION=HTTP_500_SOAP_FAULT
TICKETING_CALLS=0
```

Exactly one Read. Provider rejected the documented SOAPAction header value.

## Phase 1 — PR125 deploy

```
BACKUP=/home/pkjetp/backups/airblue-pr125-pre-20261024T185607Z
MAIN_SHA256=49d6879e54368bb047d4183bb1f5bce38e4bcc9239f5975ef9e133533d829bda
PRE_SHA256=eb517d313eb60872e46e945c34bdc5a59ac252d56460013a2d1ade8376720844
POST_SHA256=49d6879e54368bb047d4183bb1f5bce38e4bcc9239f5975ef9e133533d829bda
RETRIEVE_SERVICE_PARITY=PASS
PHP_SYNTAX=PASS
LARAVEL_BOOTSTRAP=PASS
HTTP_HEALTH=PASS
PR125_DEPLOYED=YES
OPTIMIZE_CLEAR=SKIPPED
```

## Offline contract (no network)

```
RUNTIME_READ_SOAP_ACTION=https://ota.qa.zapways.com/Read
RUNTIME_READ_ENDPOINT=https://otatest4.zapways.com/v2.0/OTAAPI.asmx
READ_XML_PNR_ONLY=YES
READ_XML_TYPE14_ABSENT=YES
READ_XML_INSTANCE_ABSENT=YES
PLACEHOLDER_READ_SENT=NO
```

## Booking C

```
BOOKING_C_FOUND=YES
BOOKING_C_MATCH_COUNT=1
BOOKING_C_ID_HASH=98010bd9270f
PNR_HASH=94b2bd04b019
BOOKING_C_CONNECTION_ID=22
TICKETED_PRE=NO
CONTEXT_SUPPLIER_PAYABLE=26000
```

## Exactly one Read — sanitized diagnostics

```
READ_CALLS=1
READ_SYNCED=NO
READ_REASON=supplier_http_error
READ_HTTP_STATUS=500
READ_FAULT_CLASS=soap_fault
READ_FAULT_SNIPPET_SANITIZED=Server did not recognize the value of HTTP Header SOAPAction: https://ota.qa.zapways.com/Read.
READ_CORRELATION_ID_PRESENT=YES
READ_CORRELATION_ID_HASH=f47ba9df3689
READ_CLASSIFICATION=HTTP_500_SOAP_FAULT
```

## Isolation

```
CONN22_CHANGED=NO
NON_CONN22_CONNECTION_ROWS_CHANGED=0
TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS=0
TICKETING_CALLS=0
AIRBOOK_CALLS=0
CANCEL_CALLS=0
REFUND_CALLS=0
```

## Interpretation (evidence only)

`otatest4.zapways.com` rejected the v2.06-document SOAPAction host `ota.qa.zapways.com/Read` with HTTP 500 SOAP fault “Server did not recognize the value of HTTP Header SOAPAction”.

No second Read was performed. No ticketing. No runtime fix in this loop.

## Artifacts

| File | Role |
|---|---|
| `_raw-pr125-deploy.txt` | Deploy parity + health |
| `_raw-one-read.txt` | Single Read diagnostic output |
| `_deploy-pr125.sh` | Deploy script |
| `_one-read-diagnostic.php` | Probe (not product code) |

Raw PNR, Instance, credentials, certificates, and full SOAP omitted.
