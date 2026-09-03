# JP-APP-PERF-CLOSURE-01 — Phase summary

## Authority

| Field | Value |
| --- | --- |
| Branch | `phase/jp-flight-perf-01` |
| START_RUNTIME_SHA | `482632e6070dcbf3f62f61b94e005f878ddea299` |
| FINAL_RUNTIME_SHA | `c33caa2d0c9c09318a368e01c008708c3c2f2502` |
| FINAL_PUBLIC_BUILD_ID | `efcBKSpHS0hcsJo8aZfGz` |
| ACTUAL_REMOTE_HEAD | `c33caa2d0c9c09318a368e01c008708c3c2f2502` |
| REMOTE_PARITY | PASS |

## Codex UI

CODEX_INTEGRATION_COMMIT=`278f515b` — production visual PASS (deployed earlier this loop).

## Return — CLOSED (absolute FU)

Evidence: `docs/evidence/jp-app-perf-closure-01/return-browser-n30-warm9.json` on `c33caa2d` / `efcBKSpHS0hcsJo8aZfGz`

| Metric | Value |
| --- | --- |
| RETURN_SAMPLE_COUNT | 30 |
| RETURN_FIRST_USEFUL_P50_MS | **3561** |
| RETURN_FIRST_USEFUL_P95_MS | **4441** (≤4500 **PASS**) |
| RETURN_FIRST_VALID_PAIR_P95_MS | 3298 |
| PAIR_PERSIST_TO_POLL_READABLE_P95_MS | **361** |
| MISSED_USEFUL_POLL_COUNT | **0** |
| Over 4500 count | 1 (pair itself 4825 → supplier floor) |

Fixes that closed Return:

1. Retain pollable `return_pair_options` across progressive writes; prefer cached pairs on poll (`91194fa7`).
2. Defer `SearchModule` off results critical chunk — route JS **30.7→19.2 kB**, First Load **172→146 kB** (`c33caa2d`).

## Traveler — improved, not absolute-closed

Evidence: traveler-warm6 on same build

| Metric | Value |
| --- | --- |
| TOTAL P50 / P95 | 2892 / 7021 |
| FARE P95 | 3627 (supplier; 4/30 >3000) |
| ACK P50 / P95 | 96 / 329 |
| SHELL P95 | **495** (≤750 PASS) |
| APP_INTERNAL P95 | 2458 (hold_validate spikes ~2s when bootstrap miss) |
| low-fare TOTAL P95 | 3514 |
| skeleton / mutations | 0 / 0 |

## Site nav

Soft-nav + prefetch: header, desktop nav, dropdown items, FAB (`cce1faf0`, `a66b6b2c`).

## FINAL_STATUS

**PASS_WITH_PROVEN_EXTERNAL_SUPPLIER_FLOOR** for Return (absolute FU closed; lone over is supplier pair >4500).

Traveler absolute ≤3000 **not** closed; remaining debt is passengers `hold_validate` second-shop spikes + ACK P95. Owner may continue that cut or accept Traveler as supplier-floor + residual JP debt.
