# PERF soft-nav diagnostic — `5fb4d15c` / `4L3tBqI6YgVUivjpjMENc`

**Date:** 2026-09-19  
**Agent:** soft-nav diagnostic (N=6/route)  
**Gate:** APP_P95 ≤ 1500ms → **FAIL**  
**Full N=20/30 recert:** **NOT READY**

## Results

| Route | APP P50 | APP P95 | Status |
|---|---:|---:|---|
| home_privacy | 564 | 1082 | borderline OK at small N |
| **home_faq** | 614 | **6018** | **FAIL** |

**WORST_SOFT_NAV_ROUTE=`home_faq`**

## Attribution

1. **Primary:** Next RSC cold render / streaming (RSC TTFB up to ~2s; privacy cold fetch p95 ~5.8s)
2. **Secondary:** Laravel `/laravel/api/public/content/config` p95 **~3769ms** (volatile)
3. **Tertiary:** privacy managed-page API p95 ~1841ms
4. Browser CMS calls = 0 (server `cache()` dedupe appears effective)
5. Bimodal: prefetch hit ~50–600ms vs cold 2–6s+

## Vs historical `36221ac0` / H9TY

- privacy P95 8183 → diagnostic ~1082 (median improved; small-N)
- faq P95 7873 → diagnostic 6018 (still far above gate)

## Next actions (no blind caching)

1. Profile Laravel `public-config` production tail (queries / cache / DB)
2. Instrument one RSC request: confirm 1× config + 1× page fetch
3. Consider earlier footer prefetch for `/privacy` `/faq` (currently idle 2.5s+)
4. Re-run diagnostic N≥10 with explicit cold samples before burning N=20 cert

Raw: `raw/profile-5fb4d15c.json`
