# JP-APP-PERF-CLOSURE-01 — Phase summary

## Authority

| Field | Value |
| --- | --- |
| Branch | `phase/jp-flight-perf-01` |
| START_RUNTIME_SHA | `482632e6070dcbf3f62f61b94e005f878ddea299` |
| FINAL_RUNTIME_SHA | `a66b6b2c654645b33309675fde6cf392f4379638` |
| FINAL_PUBLIC_BUILD_ID | `Ycdrq0YQDiYWfde1jvWGm` |
| ACTUAL_REMOTE_HEAD | `a66b6b2c654645b33309675fde6cf392f4379638` |
| REMOTE_PARITY | PASS |

## Codex UI

| Gate | Result |
| --- | --- |
| CODEX_COMMIT_INTEGRATED | `e33f3ea320b60cd35d7c510c1fd2c7992e59f539` |
| CODEX_INTEGRATION_COMMIT | `278f515b13c8435d39e954b3d8c0fa9fddacd8a2` |
| CODEX_UI_DEPLOYED | YES |
| CODEX_UI_PRODUCTION_PASS | YES |

## Commits this loop (pushed)

1. `278f515b` — Codex homepage UI  
2. `6a0d0c2d` — Return Pair poll short-circuit  
3. `0e5e458a` — slim summary.md  
4. `ebd68ed9` — Traveler handoff window.stop removal  
5. `cd97a523` — Traveler offer lookup skip consolidator  
6. `91194fa7` — **retain Return pairs** across progressive writes / prefer cached pairs on poll  
7. `cce1faf0` — flushSync Book Now ACK + soft-nav header/desktop/dropdown  
8. `7f8ce93f` — do not await results prefetch before hard-nav  
9. `a66b6b2c` — FAB tile prefetch  

## Return (warm browser)

| Run | FU P50 | FU P95 | Pair P95 | Persist P95 | Missed useful polls |
| --- | --- | --- | --- | --- | --- |
| warm5 (91194) | 3598 | 5065 | 3442 | **347** | **0** |
| warm6 (cce1) | 3806 | 4809 | 3110 | 415 | **0** |
| warm6 drop2 cold | — | **4365** | — | — | — |
| warm7 (a66b) | 3501 | 5359 | 3613 | **353** | **0** |

Root cause fixed: combo-count drift / empty rebuild hid pollable pairs (outlier persist→readable ≈11.6s). After fix persist P95 ≈300–415ms; MISSED_USEFUL_POLL_COUNT=0.

Absolute FU ≤4500 on full N=30 still intermittent (shell hard-nav + occasional supplier pair >4500). Steady warm (exclude post-deploy cold) can land ≤4500 (warm6→4365).

## Traveler (warm5 on a66b / ACK flush)

| Metric | Value |
| --- | --- |
| TOTAL P50 / P95 | 2901 / 6409 |
| FARE P95 | 4273 (supplier) |
| ACK P50 / P95 | 87 / **184** (was 248) |
| SHELL P95 | **417** (≤750 PASS) |
| APP_INTERNAL P95 | **707** (was 1623) |
| FETCH P95 | 675 |
| skeleton | 0 |
| mutations | 0 |
| passengers_url | PARTIAL (harness) |

Low-fare (≤3000) n=26 TOTAL P95 ≈3878 — absolute ≤3000 still open; supplier fare floor explains full-set TOTAL P95.

## Site nav

Hard-nav matrix previously: 11 pages, worst usable P95 ≈3836. Soft-nav + prefetch enabled for header logo/login, desktop nav links, dropdown items, FAB tiles.

## Hygiene

| Item | Result |
| --- | --- |
| SUMMARY_MD_BYTES | ~104KB (slimmed earlier) |
| PLAYWRIGHT_CONFIGS | 43 (inventory only) |

## Commercial safety

SUPPLIER_MUTATION_CALLS=0. Protected deploy + OLS SHA verified. Stale production lock cleared once after completed activate left orphan flock file.

## Remaining debt

1. Return absolute FU P95 ≤4500 on full N=30 without dropping cold samples (shell hard-nav residual).  
2. Traveler ACK P95 ≤100; passengers_url 100%; absolute TOTAL ≤3000 when fare ≤3000.  
3. Playwright config consolidation; controller complexity deferred.

## FINAL_STATUS

Open pending warm8 Return cert + owner decision if claiming supplier-floor while JP shell residual remains on absolute FU.
