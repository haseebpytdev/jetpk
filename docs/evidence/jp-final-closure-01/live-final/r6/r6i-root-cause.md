# JP-FINAL-CLOSURE-01-R6I — Soft-nav watchdog closure

## Causality

`WATCHDOG_ROOT_CAUSE_CLASS=GENUINE_SOFT_NAV_HANG` (diagnostic n=30 on `0eceb104`): **0 false triggers**.

## Fix

Engineering SHA: `9eddd7a227273a7516e98b858eed29b4101b21db`  
Public build: `8RG3oiyaFHDgBOTMNvWDA`

Progress-aware recovery: cancel on pathname, soft re-push at 900ms, hard assign at 3200ms only if still no progress; non-blocking prefetch.

## Recert (n=30, valid=30)

| Metric | Value |
|---|---|
| WATCHDOG_FIRED_COUNT | 1 |
| WATCHDOG_FALSE_TRIGGER_COUNT | 0 |
| HARD_FALLBACK_USED_COUNT | 1 |
| SOFT_ONLY_COUNT | 29 |
| SOFT_ONLY_OUTLIERS_OVER_15S | 0 |
| HARD_FALLBACK_OUTLIERS_OVER_15S | 0 |
| TRUE_SYSTEM_HANDOFF p50/p95 | 2552 / 3036 |
| TRUE_SYSTEM_USABLE p50/p95 | 3072 / 3617 |
| TRUE_SYSTEM_OUTLIERS_OVER_15S | 0 |
| POST_CLICK p50/p95 | 595 / 1057 |
| ROUTE_TO_SHELL p50/p95 | 495 / 761 |
| ROUTE_TO_USABLE p50/p95 | 1022 / 1351 |

`PERFORMANCE_CERTIFICATION=PASS`
