# Same-SHA perf cert — `5f78a5e1` / BUILD `GpIljRfA8zEVcmPGCGRQS`

## Soft-nav

| N | Pass | Worst APP P95 |
|---|---|---|
| 10 | 10/10 | 561ms |
| 20 | 10/10 | **333ms** |

## Traveler

| Metric | Value |
|---|---|
| valid | 30 |
| total_p95 | **1548ms** (≤2000) |
| dup_reval | 0 |

## Return Pair — corrected metric

`poll_total_p95` is **diagnostic only** (server poll ~0.5ms). Historical post-supplier gate uses `BROWSER_RENDER_MS` (`render` in console) = first useful `/results/data` → first pair card visible.

| Metric | Value |
|---|---|
| RETURN_POST_SUPPLIER_TO_USABLE_P95 | **728ms** (≤1000) |
| valid | 30 |
| duplicate_fetch_sum | 0 |

See `docs/closure/SEO-AEO-GEO/05-return-metric-equivalence.md`.

## Pair ↔ Segmented

| View | N | Gate |
|---|---|---|
| Pair (via return) | 30 | PASS (post-supplier 728; dup=0) |
| Segmented | 20 | PASS (N≥20; dup=0). Full-search `render` P95=1539 is diagnostic, not the Return ≤1000 gate. |

## Verdict

**SAME_SHA_PERF_CERT=PASS** on `5f78a5e1` with **corrected Return post-supplier equivalence**.

URL/AEO/GEO candidate requires a **new runtime SHA** deploy + recert (short-URL cutover).
