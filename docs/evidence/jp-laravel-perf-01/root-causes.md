# JP-LARAVEL-PERF-01 — Root causes (measured; 01R clock correction)

## Before clocks (not one number)

| Source | P50 | P95 | Definition |
|---|---|---|---|
| HISTORICAL_BROWSER_OLS_PRE_SUPPLIER_WALL (02D) | 860 | 4421 | Browser wall during full page load |
| Direct API init wall (pre-deploy) | 301 | 340 | External search-init HTTP wall |
| Intermittent cold/queue probe | — | ≥11702 | Single external request under contention |

## After / current server clock

| Source | P50 | P95 | Definition |
|---|---|---|---|
| CURRENT_SERVER_T0_TO_FIRST_SUPPLIER | 45.49 | 58.831 | SearchPerfTrace T0 → first adapter search |

`LIKE_FOR_LIKE_PRE_SUPPLIER_IMPROVEMENT_PCT=NOT_PROVABLE`

## Ranking

| Rank | Cause | Notes |
|---|---|---|
| ROOT_CAUSE_1 | Historical 02D wall mixed browser/OLS/RTT with Laravel prep | Not a like-for-like server BEFORE |
| ROOT_CAUSE_2 | Sequential multi-supplier start spread | Still true; ~2.3 s P95 spread |
| ROOT_CAUSE_3 | Markup rules re-queried per offer | Fixed with per-request memo (post-supplier) |
| ROOT_CAUSE_4 | Duplicate eligibility/airport reads | Memoized |

## OLS/PHP (01R re-check)

Current: **not** a user-visible queue blocker (localhost pre-controller proxy P95 ≈ 132 ms; swap 90 MB; OLS REQ_PROCESSING 0). Earlier YES flags reflected concurrent harness load + edge outliers, not sustained production saturation.
