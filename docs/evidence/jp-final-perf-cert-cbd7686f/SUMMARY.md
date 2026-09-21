# Same-SHA perf cert — `cbd7686f` / `kf8S-ybDOI8Vw8LzUye0H`

**RUNTIME:** `cbd7686feadd35773fd0b597117538b8b99b59fa`  
**PUBLIC_BUILD:** `kf8S-ybDOI8Vw8LzUye0H`  
**ROLLBACK:** `67b2ff3d08dad2aa81454711ce4eb244496b0aad`  
**Date:** 2026-09-21

## Performance

| Gate | Result |
|---|---|
| Soft-nav N=20 | **10/10**, worst APP P95=**878ms** ≤1500 — PASS |
| Traveler N=30 | total_p95=**1954ms** ≤2000, dup_reval=0 — PASS |
| Return Pair N=30 post_supplier_p95 | **645ms** ≤1000 — PASS |
| Pair view N=20 | valid=20, dup=0, contention=NO — PASS |
| Segmented view N=20 | valid=20, dup=0, contention=NO — PASS |
| UNNECESSARY_SUPPLIER_CALLS | 0 |
| DUPLICATE_SUPPLIER_SEARCHES | 0 (RETURN_DUPLICATE_FETCH_COUNT=0) |

## Metric equivalence

```
RETURN_METRIC_EQUIVALENCE=YES_VIA_BROWSER_RENDER_MS
POLL_TOTAL_IS_POST_SUPPLIER=NO
RETURN_POST_SUPPLIER_TO_USABLE_P95=645
```

## Short URL (runtime)

| Check | Result |
|---|---|
| OLS proxies `/flights/s/{ref}` to Next | PASS (vhconf rewrite) |
| SSR absolute Laravel resolve | PASS (`publicContentFetchUrl`) |
| Init JSON mint `short_ref`/`short_url` | PASS |
| Short page HTTP 200, no `search_id=` leak | PASS |
| Expired/missing → Search expired UI | PASS |
| Default cutover code path | PASS (`resolveResultsPath` prefers `short_url`) |

## Host paths

- `/home/pkjetp/jp-softnav-cbd7686f/`
- `/home/pkjetp/jp-perf-cbd7686f/`

## Still required before main FF / final VERIFIED PASS

- Full functional regression matrix (§15) beyond short-URL/init probe
- Dashboard rebuild from final main SHA (§18)
- Zero-reference retirement + post-cleanup (§19–20)
- Canonical release lock + CI guards + annotated tag (§21–22)
