# Pre-supplier breakdown — BEFORE

## Historical browser/OLS wall (02D authority — do not treat as server T0→supplier)

| Metric | ms |
|---|---|
| HISTORICAL_BROWSER_OLS_PRE_SUPPLIER_WALL_P50_MS | 860 |
| HISTORICAL_BROWSER_OLS_PRE_SUPPLIER_WALL_P95_MS | 4421 |

Source: `docs/evidence/jp-next-perf-02/oneway-aligned-n20-02d.json`

## Fresh direct API N20 (pre-deploy; still client wall, not server spans)

| Metric | ms |
|---|---|
| INIT_WALL_P50 | 301 |
| INIT_WALL_P95 | 340 |

Source: `before-n20.json` — no server `search_perf` yet.

## Like-for-like server BEFORE

**Not available.** `SearchPerfTrace` did not exist on production before JP-LARAVEL-PERF-01 deploy.

`LIKE_FOR_LIKE_PRE_SUPPLIER_IMPROVEMENT_PCT=NOT_PROVABLE`
