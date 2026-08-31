# JP-FINAL-CLOSURE-01-R6H — Continue actionable + system handoff

## R6G PERFORMANCE=PASS

**CORRECTED** — R6G lacked `CONTINUE_ACTIONABLE_MS`. R6H MutationObserver proves Continue is actionable before warm T3 (`APPLICATION_PRE_CONTINUE` p50/p95/max = 0/0/0). R6G “Continue dwell” was not an application Continue-readiness delay.

## Final runtime under test

| Field | Value |
|---|---|
| Engineering SHA | `0eceb1042cc519d7f7270dc6ebff1aa93324469a` |
| Public build | `3PmfQm35akHHL5gviK5Dy` |
| Branch | `phase/jp-flight-perf-01` |
| Remote freeze | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |

## R6H measurement (n=30, valid=30)

| Metric | P50 | P95 | Max / count |
|---|---:|---:|---:|
| APPLICATION_PRE_CONTINUE_MS | 0 | 0 | 0 |
| HARNESS_DETECTION_LAG_MS | 116 | 227 | 315 |
| HARNESS_CLICK_LAG_MS | 27 | 89 | — |
| POST_CLICK_TO_ROUTER_PUSH_MS | 598 | 3883 | — |
| ROUTE_TO_SHELL_MS | 480 | 20020 | — |
| ROUTE_TO_USABLE_MS | 1019 | 20552 | — |
| TRUE_SYSTEM_HANDOFF_MS | 2355 | 25316 | — |
| TRUE_SYSTEM_USABLE_MS | 2905 | 25878 | — |
| TRUE_SYSTEM outliers >15s | — | — | **5** |

Nav mix: soft_push=22, hard_assign_watchdog=8.

## Root cause

`ROOT_CAUSE_CLASS=PRODUCT_POST_CLICK_NAVIGATION_DELAY` (MIXED soft-nav / hard-watchdog tail)

| Stage | Finding |
|---|---|
| APPLICATION_PRE_CONTINUE | **Not** the defect (always 0) |
| HARNESS_DETECTION / CLICK lag | Small (≤~400ms) — excluded from TRUE_SYSTEM |
| Soft `router.push` | ~73% fast (~0.5s shell); ~27% stalls → 3.2s watchdog hard-assign |
| Hard assign after stall | Often adds ~15–20s document load → TRUE_SYSTEM outliers |

### Engineering applied (this phase)

1. Remove checkout `force-dynamic` (static anonymous shell).
2. Browser session bootstrap `AbortSignal.timeout(2500)` + fail-open guest gate.
3. Soft-nav 3.2s watchdog → `location.assign`.
4. Await `router.prefetch` up to 1s before soft push.

Residual defect: intermittent soft-nav hang to `/booking/passengers` (still `ƒ` Dynamic in Next build) and slow full reload when watchdog fires.

## Performance gates

| Gate | Result |
|---|---|
| TRUE_SYSTEM_HANDOFF p50 ≤3s / p95 ≤6s | FAIL (p95 25316) |
| TRUE_SYSTEM_USABLE p50 ≤5s / p95 ≤8s | FAIL (p95 25878) |
| Outliers >15s = 0 | FAIL (5) |
| APPLICATION_PRE unexplained tail | PASS (none) |

`PERFORMANCE_CERTIFICATION=FAIL`  
`PRODUCT_PERFORMANCE_DEFECT_PROVEN=YES`  
`HARNESS_MEASUREMENT_DEFECT_PROVEN=NO` (A captured independently; harness lag excluded)

## Functional preservation (not retested; R6F evidence retained)

SEGMENTED_RETURN_BOOK_ACTION / REVIEW / TRAVELER / GROUP / AUTH responsive = PASS (prior R6F).  
SUPPLIER_MUTATION_CALLS=0, PAYMENT_EXECUTED=NO, TICKET_ISSUED=NO.
