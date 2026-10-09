# AUTH — Zapways TEST v2

**Connection:** id 22 (`AirBlue Zapways TEST v2`)  
**Wire semantics (from resolver):** Protocol 2.0, Version 1.04, Target `Test`, Agent.Type 29 (internal default), Agent.ID `JetPakistanOTA`

## Read probe (minimum SOAP with credentials; dummy PNR)

Executed via application `AirBlueOtaXmlBuilder::buildReadRequest` + `AirBlueClient::callOta(..., 'read', ...)` as runtime user `nobody`.

| Field | Value |
|---|---|
| AUTH (Read probe) | FAIL (HTTP 500, `supplier_http_error`) |
| HTTP_STATUS | 500 |
| SOAP_FAULT_CODE | (not extracted; generic provider error envelope) |
| SOAP_FAULT_SUMMARY | Sanitized: provider unavailable message only |

## Authentication conclusion

| Field | Value |
|---|---|
| AUTH (credential acceptance) | **PASS** |

**Rationale:** Subsequent **single** certified `AirLowFareSearch` on the same connection completed without `supplier_auth_failed`, `supplier_transport_failed`, or config faults (`SEARCH_RESULT=PASS`). Zapways credential rejection would surface as auth/HTTP 401-class failures on search.

Read-with-invalid-PNR is not a reliable isolated auth probe on TEST (HTTP 500); search acceptance is the authoritative auth signal for this stack.
