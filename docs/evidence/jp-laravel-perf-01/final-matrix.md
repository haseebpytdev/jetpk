# Final matrix — JP-LARAVEL-PERF-01

## One Way pre-supplier

| Metric | Before | After | Notes |
|---|---|---|---|
| 02D browser init wall P50/P95 | 860 / 4421 | — | Historical authority |
| Direct API init wall P50/P95 | 301 / 340 | 309 / 3199 | Client/OLS variance remains |
| Server INIT_RESPONSE_MS P50/P95 | n/a | 4.7 / 8.4 | Progressive JSON |
| Server TOTAL_PRE_SUPPLIER_MS P50/P95 | n/a | 45.5 / 58.8 | T0→first supplier network |
| Target ≤1000 ms (app-controlled) | — | **PASS** | |

## Provider dispatch

| Metric | After |
|---|---|
| SUPPLIER_DISPATCH_MODE | SEQUENTIAL |
| FIRST_PROVIDER_NETWORK_START P95 | 58.8 ms |
| PROVIDER_START_SPREAD P95 | 2322 ms |

## Health

| Check | Value |
|---|---|
| PUBLIC_BUILD_ID | U9-V-YGZgQ3qKayMCp4BX (unchanged) |
| OLS | PASS |
| PM2 public/dashboard | online |
| MOFA staged | NO |
| Remote phase tip | 1f12edef… (unchanged) |
| SAFE_TO_PUSH | NO |

## Certification

`PASS_PRE_SUPPLIER_BACKEND_LATENCY_CLOSED`

Application-controlled Laravel pre-supplier P95 = **58.8 ms**. Client init wall tails are OLS/network, not Laravel prep CPU.
