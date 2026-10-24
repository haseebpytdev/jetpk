# JP-AIRBLUE-ZAPWAYS-READ-WSDL-CONTRACT-RESOLUTION — Final Report

**Date (UTC):** 2026-10-24  
**MAIN_SHA:** `d48c38d3a60e0cb548bd2c33249ca920541bf5c7`  
**Conn 22 TEST endpoint:** `https://otatest4.zapways.com/v2.0/OTAAPI.asmx`  
**Provider GET only:** WSDL + `?op=Read` (mTLS). **POST calls: 0**

## Verdict

```
CONTRACT_CLASSIFICATION=DOC_ERRATUM_RUNTIME_DIFFERS
WSDL_READ_SOAP_ACTION=http://zapways.com/air/ota/2.0/Read
DOCUMENT_V206_ACTION=https://ota.qa.zapways.com/Read
CURRENT_JETPK_ACTION=https://ota.qa.zapways.com/Read
SERVICE_METADATA_ACTION_MATCH=YES
READ_SOAP_ACTION_CHANGE_REQUIRED=YES
```

Live TEST WSDL and ASMX help page agree. Published v2.06 PDF / prior JetPakistan TEST config do not.

## Metadata fetch

```
CERT_EXISTS=YES
KEY_EXISTS=YES
WSDL_FETCH_HTTP=200
WSDL_FETCH_PASS=YES
HELP_PAGE_FETCH=200
PROVIDER_GET_CALLS=2
PROVIDER_POST_CALLS=0
```

## WSDL Read binding

```
WSDL_READ_OPERATION_FOUND=YES
WSDL_READ_SOAP_ACTION=http://zapways.com/air/ota/2.0/Read
WSDL_SERVICE_ENDPOINT=https://otatest4.zapways.com/v2.0/OTAAPI.asmx
WSDL_BINDING_NAME=IOTA_Air1
WSDL_SOAP_VERSION=1.1
HELP_PAGE_READ_SOAP_ACTION=http://zapways.com/air/ota/2.0/Read
SERVICE_METADATA_ACTION_MATCH=YES
```

SOAP 1.2 binding also lists the same Read soapAction.

## Runtime change (offline PR)

Update **v2 TEST only** `soap_action_test` → `http://zapways.com/air/ota/2.0/Read` in:

- `config/suppliers.php` `protocol_versions.2.0`
- legacy `ota_operations.read`

**Unchanged:**

- HTTP endpoint (`otatest4` / `ota4`)
- LIVE Read action (`https://ota.zapways.com/Read`) — LIVE WSDL not verified this loop
- v3 Read defaults
- Read XML / RetrieveService / Cancel / Ticketing / AirBook

## Prior fault correlation

Diagnostic loop proved otatest4 HTTP 500:

`Server did not recognize the value of HTTP Header SOAPAction: https://ota.qa.zapways.com/Read`

That rejection matches DOC vs WSDL divergence.

## Safety

```
NON_CONN22_CONNECTION_ROWS_CHANGED=0
TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS=0
PROVIDER_POST_CALLS=0
```

Full WSDL not committed (large binary/metadata). Parse summary retained in `_raw-wsdl-fetch.txt` / this report.
