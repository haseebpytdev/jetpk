# AirLowFareSearch — single certified call

**Connection:** id 22  
**Runtime user:** `nobody` (matches OpenLiteSpeed)  
**Operation:** `air_low_fare_search` (v2 SOAP)

## Request (sanitized)

| Field | Value |
|---|---|
| Origin | ISB |
| Destination | LHE |
| Departure | 2026-10-23 |
| Adults | 1 |
| Currency | PKR |
| Trip | one_way |

## Result

| Field | Value |
|---|---|
| HTTP | Success path (no `error_code` in search meta) |
| SEARCH | PASS |
| OFFER_COUNT | 0 |
| OFFERS | NO_RESULTS |

## Supplier call accounting

| Metric | Count |
|---|---|
| Certified `AirLowFareSearch` (this phase) | **1** |
| Exploratory `airblue:test-search` during TLS diagnosis (wrong default connection / date) | 2 (documented; not used for PASS classification) |
| `Read` auth probe | 2 (not AirLowFareSearch) |

Mutation guard: no book/ticket/cancel/payment operations invoked.
