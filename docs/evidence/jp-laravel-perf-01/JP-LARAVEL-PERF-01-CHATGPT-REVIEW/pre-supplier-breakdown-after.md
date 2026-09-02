# Pre-supplier breakdown — AFTER (N30 server search_perf)

| Metric | P50 | P95 |
|---|---|---|
| Client init wall | 309 | 3199 |
| Server INIT_RESPONSE_MS | 4.7 | 8.4 |
| Server TOTAL_PRE_SUPPLIER_MS (T0→first network) | 45.5 | 58.8 |
| FIRST_PROVIDER_NETWORK_START_MS | 46.0 | 58.8 |
| LAST_ELIGIBLE_PROVIDER_NETWORK_START_MS | 1722 | 2381 |
| PROVIDER_START_SPREAD_MS | 1678 | 2322 |
| REQUEST_VALIDATION_MS | — | 0.3 |
| AUTH_CONTEXT_MS | — | 3.5 |
| PROVIDER_REGISTRY_MS | — | 40.2 |
| PROVIDER_ELIGIBILITY_MS | — | 10.1 |
| PRE_SUPPLIER_DB_TOTAL_MS | — | 15.4 |
| PRE_SUPPLIER_DB_QUERY_COUNT | — | 9 |
| PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS | — | 0 |

`SUPPLIER_DISPATCH_MODE=SEQUENTIAL`

Client init wall P95 remains OLS/network heavy-tailed; **application-controlled** pre-supplier P95 is **58.8 ms**.
