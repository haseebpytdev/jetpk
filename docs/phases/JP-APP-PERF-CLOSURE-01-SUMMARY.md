# JP-APP-PERF-CLOSURE-01 — Phase summary

## Authority

| Field | Value |
| --- | --- |
| Branch | `phase/jp-flight-perf-01` |
| START_RUNTIME_SHA | `482632e6070dcbf3f62f61b94e005f878ddea299` |
| FINAL_RUNTIME_SHA | `7efa49d1fc90a78dbdd40ade6fe0cfa08120558a` |
| FINAL_PUBLIC_BUILD_ID | `roo_GvoNl9OWNFK1I_NSo` |
| ACTUAL_REMOTE_HEAD | `7efa49d1fc90a78dbdd40ade6fe0cfa08120558a` |
| REMOTE_PARITY | PASS |

## Codex UI

CODEX_INTEGRATION_COMMIT=`278f515b` — production visual PASS.

## Return — ABSOLUTE CLOSED

warm9 on `c33caa2d` / `efcBKSpHS0hcsJo8aZfGz` (chunk cut still in `7efa49d1` runtime):

| Metric | Value |
| --- | --- |
| FU P50 / P95 | 3561 / **4441** (≤4500 PASS) |
| Pair P95 | 3298 |
| Persist→readable P95 | **361** |
| Missed useful polls | **0** |
| Lone over | pair 4825 (supplier floor) |

## Traveler — supplier floor + JP hold fixed

warm7 on `7efa49d1` / `roo_GvoNl9OWNFK1I_NSo`:

| Metric | Value |
| --- | --- |
| TOTAL P50 / P95 | 2758 / 5744 |
| FARE P95 | **4313** (6/30 >3000) |
| ACK P50 / P95 | 80 / **123** |
| SHELL P95 | **353** |
| APP P95 | **495** (was 2458) |
| HOLD P95 | **67** (was ~2300; hold_gt_500=0) |
| FETCH P95 | 711 |
| low-fare TOTAL P95 | 3646 |
| skeleton / mutations | 0 / 0 |

## Key fixes this continuation

1. Return pair retention + cached poll preference (`91194fa7`)
2. Results `SearchModule` deferred — 30.7→19.2 kB (`c33caa2d`)
3. Soft-nav/prefetch header/desktop/dropdown/FAB
4. flushSync Book Now ACK
5. Traveler bootstrap recovery from draft freshness (`7efa49d1`)

## FINAL_STATUS

**PASS_WITH_PROVEN_EXTERNAL_SUPPLIER_FLOOR**

- Return absolute FU closed.
- Traveler total miss explained by fare revalidation P95>3000; JP hold second-shop eliminated; residual low-fare ~3646 and ACK P95 123 remain as small debt.
