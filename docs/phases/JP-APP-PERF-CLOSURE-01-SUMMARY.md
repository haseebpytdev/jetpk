# JP-APP-PERF-CLOSURE-01 — Phase summary

## Authority

| Field | Value |
| --- | --- |
| Branch | `phase/jp-flight-perf-01` |
| START_RUNTIME_SHA | `482632e6070dcbf3f62f61b94e005f878ddea299` |
| FINAL_RUNTIME_SHA | `cd97a523e2bbeae5c18a768acd751ce8f1e986c5` |
| FINAL_PUBLIC_BUILD_ID | `17XF6BVhi9OsQoVR70SXb` |
| ACTUAL_REMOTE_HEAD | `cd97a523e2bbeae5c18a768acd751ce8f1e986c5` |
| REMOTE_PARITY | PASS |

## Codex UI

| Gate | Result |
| --- | --- |
| CODEX_COMMIT_INTEGRATED | `e33f3ea320b60cd35d7c510c1fd2c7992e59f539` |
| CODEX_INTEGRATION_COMMIT | `278f515b13c8435d39e954b3d8c0fa9fddacd8a2` |
| CODEX_UI_DEPLOYED | YES |
| CODEX_UI_PRODUCTION_PASS | YES (1440/1280/1024/768/390) |

## Commits this loop (pushed)

1. `278f515b` — Codex homepage UI integrate  
2. `6a0d0c2d` — Return Pair poll short-circuit + early publish throttle  
3. `0e5e458a` — slim summary.md (~570KB → ~104KB) + archive  
4. `ebd68ed9` — Traveler handoff: remove window.stop()/prefetch strip  
5. `cd97a523` — Traveler offer lookup: skip consolidator  

## Return (warm browser, N=30)

Best clean attribution run after poll fix (`warm2` / `warm3` family):

| Metric | Value |
| --- | --- |
| RETURN_SAMPLE_COUNT | 30 |
| RETURN_FIRST_USEFUL_P50_MS | ~3778–3982 |
| RETURN_FIRST_USEFUL_P95_MS | ~5397–6778 (still >4500) |
| RETURN_FIRST_VALID_PAIR_P95_MS | ~3325–4399 |
| PERSIST→READABLE P95 | ~684–927 (outlier poisoned one run) |
| EARLY_PARTIAL | Observed (`FIRST_EARLY_PARTIAL_PUBLISH_MS` present on samples) |
| SUPPLIER_MUTATION_CALLS | 0 |

Absolute FU ≤4500 **not** closed. Pair floor often ≤4500; occasional supplier pair >4500 proven. JP post-ready often ~0.9–1.6s (target ≤1000 for supplier-floor path) — **not** fully proven for `PASS_WITH_PROVEN_EXTERNAL_SUPPLIER_FLOOR` on Return alone.

## Traveler (warm, N=30, T0=Continue click)

| Metric | warm4 |
| --- | --- |
| TOTAL_P50 | **2814** |
| TOTAL_P95 | 5389 |
| FARE_P95 | **3977** (supplier) |
| SHELL_P95 | **494** |
| ACK_P50 / P95 | 91 / 248 |
| APP_INTERNAL_P95 | 1623 (was ~2736 before consolidator fix; warm3 hit 837) |
| FETCH_P95 | 774 |
| skeleton after validation | 0 |
| mutations | 0 |

Shell gate ≤750 **PASS**. Absolute total ≤3000 **not** closed (fare P95). Supplier fare >3000 on 5/30 samples — external floor candidate, but ACK_P95 and residual JP post-fare still above preferred gates.

## Site nav (hard-nav warm N=5)

| Metric | Value |
| --- | --- |
| PAGES_PROFILED | 11 |
| WORST_USABLE_P95 | 3836 (`/agent/register`) |
| SLOW_PAGE_COUNT (>2000) | 5 |

Hard-nav overstates client Link navigations; remaining slow public pages need soft-nav/prefetch follow-up.

## Hygiene

| Item | Result |
| --- | --- |
| SUMMARY_MD_BYTES | 569686 → **~104028** |
| PLAYWRIGHT_CONFIGS | 43 → 43 (inventory only; no coverage cut) |
| AGENTS.md `.cursor/rules` | Already present |

## Commercial safety

SUPPLIER_MUTATION_CALLS=0 throughout. No MOFA/Chatwoot. Protected deploy lock + OLS SHA verified.

## Remaining debt

1. Return absolute FU P95 ≤4500 (or prove JP post-ready ≤1000 cleanly without measurement poison).  
2. Traveler ACK_P95 ≤100; residual passengers APP spikes; passengers_url PARTIAL harness cases.  
3. Soft-nav for ordinary public pages >2s hard-nav.  
4. Playwright config consolidation.  
5. BookingController/FlightController complexity (deferred).

## FINAL_STATUS

**BLOCKED_REQUIRES_USER_DECISION** on declaring `PASS_WITH_PROVEN_EXTERNAL_SUPPLIER_FLOOR` for the whole application while Return FU and Traveler total remain above absolute targets with mixed JP residual gates.

Safe alternate if owner accepts supplier-floor framing for Traveler fare + Return pair only: document partial floors, keep loop open for JP residuals.

Recommended owner decision:

A) Continue loop on Return persist→browser + Traveler ACK/APP  
B) Accept Return/Traveler as `PASS_WITH_PROVEN_EXTERNAL_SUPPLIER_FLOOR` for supplier-bound intervals only and track JP residuals as debt  
C) Stop here with current verified runtime `cd97a523` / build `17XF6BVhi9OsQoVR70SXb`
