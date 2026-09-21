# Return / Segmented metric equivalence

**SHA:** `5f78a5e1` / BUILD `GpIljRfA8zEVcmPGCGRQS`  
**Date:** 2026-09-21  
**Verdict:** `poll_total_p95` is **NOT** the historical post-supplier gate.

## Marker definitions (current harness `run-return-n30.mjs`)

| Marker | Definition |
|---|---|
| **POLL_START / SUPPLIER_DONE_MARKER** | First successful `/flights/results/data` response with usable paired/result rows (`api.firstUsefulDataAt`). This is when supplier/pairing output is first readable by the browser. |
| **POLL_END / FIRST_PAIR_CARD_VISIBLE_MARKER** | `[data-testid="pair-return-card"]` (or flight/outbound card) visible (`firstCardAt`). |
| **WHAT_poll_total_MEASURES** | Server-side `search_perf.POLL_TOTAL_SERVER_MS` for a **single** poll HTTP handler (store read + merge + serialize). Typical ~0.5–1ms. **Not** application post-supplier UX time. |
| **HISTORICAL_POST_SUPPLIER** (`e5eead09`) | `firstUsefulAt − browserDataAt` = card visible − first non-empty results/data. |

## Equivalence

| Metric | Same as historical POST_SUPPLIER? |
|---|---|
| `poll_total_p95` / `POLL_TOTAL_SERVER_P95_MS` | **NO** — narrower server poll cost only |
| `BROWSER_RENDER_MS` / console `render` / `RETURN_RESULTS_AVAILABLE_BUT_NOT_RENDERED_MS` | **YES** — `firstCardAt − firstUsefulDataAt` |

## Recomputed from host console (same samples as prior “PASS”)

### Return Pair N=30

| Field | Value |
|---|---|
| RETURN_POST_SUPPLIER_TO_USABLE_P50 | 616ms |
| RETURN_POST_SUPPLIER_TO_USABLE_P95 | **728ms** |
| Gate ≤1000 | **PASS** |
| (wrong) poll_total_p95 | 0.581ms — diagnostic only |

### Segmented N=20 (full search → outbound card)

| Field | Value |
|---|---|
| BROWSER_RENDER P95 (`render`) | 1539ms |
| (wrong) poll_total_p95 | 0.773ms — diagnostic only |

**Pair↔Segmented gate** (N≥20 each, duplicate supplier searches = 0) remains the required gate for view coverage — not the Return post-supplier ≤1000 applied to segmented full-search render.

## Corrected certification statement

```
RETURN_METRIC_EQUIVALENCE=YES_VIA_BROWSER_RENDER_MS
RETURN_POST_SUPPLIER_TO_USABLE_P95=728
RETURN_PAIR_GATE=PASS
POLL_TOTAL_IS_POST_SUPPLIER=NO
```

Harness summaries must emit `RETURN_POST_SUPPLIER_TO_USABLE_P95` (from `BROWSER_RENDER_MS`) as the ≤1000 gate. Keep `poll_total_p95` as diagnostic only — never rename the gate onto it.
