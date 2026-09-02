# JP-NEXT-PERF-02C — Nearby Date root cause

## Corrected waterfall (progressive search)

Nearby Date click → `router.push(/flights/results?…new depart…)` (no `search_id`) →
`GET /laravel/flights/results/search` (`beginSearch`, status=SEARCHING) →
poll `GET /flights/results/data` until READY → first card.

Measured P95 (n=10, cold browser per sample):

| Stage | P95 ms |
|-------|--------|
| Click → search API start | 320 |
| Laravel pre-supplier (`/results/search`) | 364 |
| Supplier (`max(supplier_call_summaries.elapsed_ms)`) | 1952 |
| Laravel post (poll residual − supplier) | 0 |
| Response → router / data handoff | 15 |
| Router → useful render | 10 |
| **App-layer sum (overhead)** | **676** |
| Total click → ready | 1667 |

`NEARBY_DATE_APP_OVERHEAD_P95_MS=676` (≤1000 target).

## Root causes

NEARBY_ROOT_CAUSE_1=
**02B overhead figure was a measurement artifact.**  
Measured ms (02B): 3428 (mis-labeled “app”).  
Layer: harness (`total − last resource duration`, wrong endpoint assumption POST `/flights/search`).  
Required: no — superseded by progressive GET `/flights/results/search` attribution.

NEARBY_ROOT_CAUSE_2=
**Date change correctly creates new search_id authority** via progressive init (not representation switch).  
Measured ms: click→API ~288–320; search_id always changed; init GET always fired.  
Layer: application + Laravel `beginSearch`.  
Required: yes — must not reuse stale offers for a new depart date.

NEARBY_ROOT_CAUSE_3=
**Remaining wall is supplier / progressive poll**, not Next remount thrash on this path.  
Measured ms: supplier summaries ~1952; app-layer sum P95 676.  
Layer: supplier (dominant) + small Next router (~300ms) + Laravel beginSearch (~350ms).  
Required: supplier wait required; app layers already under target — **no engineering change**.

## UX

NEARBY_STALE_FARE_FLASH=0  
NEARBY_READY_TO_FULL_SKELETON_REGRESSION=0 (initializing “Searching…” for new date is expected)

## Engineering

PERF_02C_ENGINEERING_FIX_REQUIRED=NO
