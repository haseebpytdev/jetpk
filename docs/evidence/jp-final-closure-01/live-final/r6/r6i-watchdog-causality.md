# JP-FINAL-CLOSURE-01-R6I — Watchdog causality

## R6H engineering audit (`0eceb104`)

| Field | Value |
|---|---|
| R6H_ENGINEERING_FILES | `frontend/features/flight-details/hooks/use-revalidation.ts` |
| R6H_FUNCTIONAL_CHANGES | await `router.prefetch` up to 1s before soft push |
| R6H_TIMING_CHANGES | none beyond nav meta |
| R6H_WATCHDOG_CHANGE | threshold 2800 → 3200 |
| R6H_WATCHDOG_THRESHOLD_BEFORE | 2800 (from `e9a6994e`) |
| R6H_WATCHDOG_THRESHOLD_AFTER | 3200 |

## Watchdog authority

| Field | Value |
|---|---|
| WATCHDOG_FILE | `frontend/features/flight-details/hooks/use-revalidation.ts` |
| WATCHDOG_FUNCTION | `navigateHandoff` (inside `useRevalidation`) |
| WATCHDOG_START_LINE | ~191 (`router.push` then timers) |
| WATCHDOG_THRESHOLD_MS | 3200 (hard); 900 (soft retry, R6I) |
| WATCHDOG_START_EVENT | immediately after `router.push(resolved)` |
| WATCHDOG_SUCCESS_CANCEL_EVENT | pathname includes `/booking/passengers` (poll/popstate) |
| WATCHDOG_OTHER_CANCEL_EVENTS | cleanup on settle; soft retry does not cancel hard timer alone |
| WATCHDOG_FALLBACK_ACTION | `window.location.assign(target)` |

## Timing mark semantics

| Mark | File | Actual event | Proves |
|---|---|---|---|
| T5 | `use-revalidation.ts` | `markBookNowTiming("T5_router_push")` **before** `router.push` | push **about to be called**, not that App Router transition started |
| T6 | `use-revalidation.ts` | `T6_nav_start` before soft push | checkout handoff entered — **not** destination mount |
| T7 | same | marked with soft_push / hard_assign | same as T5 cohort; overwritten on hard |
| T8 | `BookNowShellTimingMark.tsx` / `PassengerDetailsPage.tsx` | loading UI or passengers loading effect | Traveler shell visible (first-wins) |
| T9 | `PassengerDetailsPage.tsx` | form ready effect | editable field ready |

## Soft-nav progress signals (codebase)

| Signal | Source | Reliability |
|---|---|---|
| `history.pushState` to `/booking/passengers` | Next soft-nav | **High** — present on all soft-only successes |
| pathname `/booking/passengers` | browser | High once URL committed |
| `passengers-route-loading` | `loading.tsx` | Medium — may not paint on stuck soft |
| RSC/fetch to passengers | network | High when transition actually starts |

`SOFT_NAV_PROGRESS_SIGNAL=history.pushState(/booking/passengers)`  
`SOFT_NAV_PROGRESS_SOURCE=Next App Router soft navigation`  
`SOFT_NAV_PROGRESS_RELIABILITY=HIGH for success path; absent for genuine hangs`

## Diagnostic result (n=30 on `0eceb104` / `3PmfQm35akHHL5gviK5Dy`)

```
WATCHDOG_ROOT_CAUSE_CLASS=GENUINE_SOFT_NAV_HANG
WATCHDOG_FIRED_COUNT=10
FALSE_TRIGGER_COUNT=0
GENUINE_STALL_COUNT=10
SOFT_ONLY_COUNT=20
SOFT_ONLY_OUTLIERS_OVER_15S=0
WATCHDOG_FIRED_OUTLIER_COUNT_OVER_15S=9
ALL_LONG_TAILS_USED_HARD_FALLBACK=YES
```

Every hard fire: `FIRE_LAG≈3200ms`, `SOFT_NAV_PROGRESS_BEFORE_WATCHDOG=NO`, no page-world progress events before fire.

## Proven sequence

`T5/T7 soft marks → router.push → (no pushState / no RSC for 3200ms) → hard location.assign → ~15–30s document load`

## Minimal fix (`9eddd7a2`)

1. Cancel recovery when destination pathname reached (poll + popstate).  
2. Soft re-`router.push` at 900ms if still no pathname.  
3. Hard assign only at 3200ms if still no progress.  
4. Fire-and-forget prefetch (remove blocking 1s await).  
5. Clear all timers on settle (no stale assign).
