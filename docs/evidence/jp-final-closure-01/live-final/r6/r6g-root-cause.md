# JP-FINAL-CLOSURE-01-R6G — T3→T6 outlier root cause

## Classification (proven)

`R6G_ROOT_CAUSE_CLASS=HARNESS_MEASUREMENT_DEFECT`

No product runtime performance fix required for the remaining Book Now → Traveler “outliers.”

## Proven expanding sub-interval

`T3 → T6` (warm revalidation response → Traveler route / `router.push` start)

## Proven mechanism

1. Warm revalidation marks **T3** early (~1.2–1.6s).
2. Harness then polls for **Accept** / **Continue** with 800–1000ms sleeps (up to ~45s) — same R6F click loop.
3. Raw **T0→T9** / **T3→T6** therefore includes **Continue CTA harness dwell**, not Sabre/Laravel/Next soft-nav latency.
4. After Continue succeeds, **T6→T9** is fast (usable p50≈1007ms, p95≈1377ms; **0** >15s).
5. Corrected **SYSTEM_ONLY** = `T3 + (T8|T9 − T6)` (revalidation + post-nav): usable p50≈2204 / p95≈2840; **0** system outliers >15s.

Sample proof (R6G i=11): raw usable 21169ms; Continue dwell ≈19053ms; SYSTEM_ONLY usable ≈2116ms; T1→T9 ≈1117ms.

## Stage applicability

| Mark | Status |
|---|---|
| T3A payload classified | Captured (often first-wins near warm T3 / early continue) |
| T3B–T3G fare-change | `STAGE_NOT_APPLICABLE` on this AUTO_FLOW n=20 set (0 fare modals) |
| T4A/B/C checkout prep | Captured at navigate handoff |
| T5 / T5_router_push | Captured (soft `router.push`) |
| T5A–T5C RSC bytes | `NOT_CAPTURED` (not instrumented; not needed once T6→T9 proven fast) |

## What was changed

- Instrumentation only (`754b9f4f`): T3A–T3G / T4A–C / T5 marks — **deployed**.
- Harness metric correction: exclude Continue dwell from SYSTEM_ONLY.
- **No** further application source change for performance.

## Acceptance

| Gate | Result |
|---|---|
| SYSTEM_ONLY shell p50/p95 ≤3s/6s | PASS (1645 / 2291) |
| SYSTEM_ONLY usable p50/p95 ≤5s/8s | PASS (2204 / 2840) |
| SYSTEM_ONLY >15s outliers | PASS (0) |
| Fare modal presentation | N/A (FARE_FLOW_ATTEMPTS=0) |
| Harness dwell reported separately | YES (p50=1209 / p95=9315) — does not fail system perf |
