# JP-LARAVEL-PERF-01R — Measurement integrity correction

## Do not compare these clocks

| Clock | P50 | P95 | Meaning |
|---|---|---|---|
| HISTORICAL_BROWSER_OLS_PRE_SUPPLIER_WALL | 860 | 4421 | JP-NEXT-PERF-02D browser wall of search-init during full page load (RTT + OLS/edge + PHP + response) |
| DIRECT_API_INIT_WALL (pre-deploy N20) | 301 | 340 | External HTTPS client wall of search-init only |
| CURRENT_SERVER_T0_TO_FIRST_SUPPLIER | 45.49 | 58.831 | Server `SearchPerfTrace` T0 → first eligible `adapter->search` |
| CURRENT_SERVER_INIT_RESPONSE | 4.7 | 8.4 | Server time until progressive JSON ready |

`LIKE_FOR_LIKE_PRE_SUPPLIER_IMPROVEMENT_PCT=NOT_PROVABLE`

No identical server-side BEFORE baseline with T0→first-supplier spans existed before deploy of `search_perf`.

The phase still closes on **current** Laravel internal P95 ≤ 1000 ms (`CURRENT_SERVER_T0_TO_FIRST_SUPPLIER_P95_MS=58.831`).

## Separated layers (A–E)

| Layer | Evidence |
|---|---|
| A. Historical browser/OLS wall | 02D N20; P95 4421 |
| B. Laravel request/server internal preparation | `INIT_RESPONSE_MS` / `TOTAL_PRE_SUPPLIER_MS` after deploy |
| C. Supplier execution | Sequential provider start spread (~1.7–2.3 s P50/P95) |
| D. OLS/PHP queueing | 01R ops + localhost probe (below) |
| E. Browser/Next | Closed in JP-NEXT-PERF-02; not reopened |
