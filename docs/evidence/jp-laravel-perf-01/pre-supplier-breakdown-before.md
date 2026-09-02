# Pre-supplier breakdown — BEFORE

## 02D aligned authority (full page browser wall)

| Metric | ms |
|---|---|
| ONEWAY_LARAVEL_PRE_SUPPLIER_P50_MS | 860 |
| ONEWAY_LARAVEL_PRE_SUPPLIER_P95_MS | 4421 |

Source: `docs/evidence/jp-next-perf-02/oneway-aligned-n20-02d.json`

## Fresh direct API N20 (pre-deploy)

| Metric | ms |
|---|---|
| INIT_WALL_P50 | 301 |
| INIT_WALL_P95 | 340 |

Source: `before-n20.json` — no server `search_perf` yet.

## Interpretation

02D P95 ≫ direct API P95 because 02D included page-load connection contention and OLS queue tails, not Laravel prep CPU.
