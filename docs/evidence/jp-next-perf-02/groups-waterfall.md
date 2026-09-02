# JP-NEXT-PERF-02 — Groups waterfall

## GROUPS_WATERFALL_ROOT_CAUSE

Client-only page waited for hydration (~2.7s) before parallel facets/CMS/inventory; browser→OLS→Laravel inventory added ~3.3s vs 0.83s private Laravel. Landing also padded +720ms `setTimeout` before navigate.

Not a sequential airline→sector→category→inventory master chain.

## GROUPS_FILTER_METADATA_BLOCKS_RESULTS

**NO** (URL-driven inventory already allowed before facets; SSR now delivers cards in HTML).

## Before → After

| Metric | Before | After |
|---|---|---|
| GROUP_FILTER_READY_MS | 8137 (02B) | ~1100–2430 (SSR facets in document) |
| GROUP_RESULTS_FIRST_CARD_MS | 9755 (02B) / ~6076 measured | **~2425 coldish** / warm **1100–1851** |
| Client `/groups/search/data` | required | **absent** when SSR matches |
| SSR HTML contains cards | no | **yes** (local SSR 656ms) |

Warm n=4 useful_ms: 1851, 1100, 1233, 1290 → P50≈1261, P95≈1851
