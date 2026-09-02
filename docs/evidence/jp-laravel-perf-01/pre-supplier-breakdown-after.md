# Pre-supplier breakdown — AFTER (N30 server search_perf)

## Correct clocks (01R)

| Metric | P50 | P95 |
|---|---|---|
| HISTORICAL_BROWSER_OLS_PRE_SUPPLIER_WALL (02D) | 860 | 4421 |
| CURRENT_SERVER_T0_TO_FIRST_SUPPLIER | 45.5 | 58.8 |
| CURRENT_SERVER_INIT_RESPONSE | 4.7 | 8.4 |
| Client init wall (external, after deploy N30) | 309 | 3199 |

`LIKE_FOR_LIKE_PRE_SUPPLIER_IMPROVEMENT_PCT=NOT_PROVABLE` — historical wall ≠ server T0→first-supplier.

## Component (server search_perf)

| Metric | P50 | P95 |
|---|---|---|
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

Application-controlled pre-supplier P95 is **58.8 ms**. External client init-wall tails are edge/network (see `ols-php-queue-01r.md`); not Laravel prep CPU.
