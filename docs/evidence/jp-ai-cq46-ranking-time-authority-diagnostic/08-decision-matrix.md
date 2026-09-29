# CQ46 architecture decision matrix

## Problem split

| | Problem A — Lead precedence | Problem B — Search semantics |
| --- | --- | --- |
| Symptom | Phrase stored as `lead_name` | Preference not applied to search/results |
| Proven | YES (endpoint matrix) | YES — hybrid can parse prefs, but lead steals turn; deep-link omits sort/window |
| Fix coupling | Must fix for correctness | Separate product/search-contract work |

**A does not imply B.** Lead override can ship without promising search honor.

## Should override lead capture?

| | Recommendation |
| --- | --- |
| RANKING_SHOULD_OVERRIDE_LEAD_CAPTURE | **YES** (when TravelConstraintResolver resolves ranking) |
| TIME_SHOULD_OVERRIDE_LEAD_CAPTURE | **YES** (when resolver resolves time_preference) |

Safe pattern: expose `ranking_refinement` / `time_refinement` into `progressiveTravelAuthority` → `isTravelAuthorityTurn=true` **without** immediately setting `deterministicAuthorityComplete=true` (do not blindly copy CQ45 stop_refinement into short-circuit).

## Per-preference matrix

| Preference | LEAD_OVERRIDE | STATE_PERSIST | CONFIRMATION_MATERIAL | REQUIRES_RECONFIRMATION | QWEN_REQUIRED (simple active) | DOWNSTREAM_EXECUTION_SUPPORTED |
| --- | --- | --- | --- | --- | --- | --- |
| CHEAPEST | YES | YES (shopping_state `ranking_preference`) | NO | NO | NO | PARTIAL (results `sort=cheapest`; AI deep-link NO) |
| FASTEST | YES | YES | NO | NO | NO | PARTIAL (`sort=fastest`) |
| SHORTEST_LAYOVER | YES | YES | NO | NO | NO | NO (labels only) |
| BEST_VALUE | YES | YES | NO | NO | NO | NO (labels only) |
| MORNING | YES | YES (`time_preference` via TravelIntent) | NO | NO | NO | PARTIAL (`departure_window=morning`) |
| EVENING | YES | YES | NO | NO | NO | PARTIAL (`departure_window=evening`) |
| NIGHT | YES | YES | NO | NO | NO | WEAK (no exact `night` window on results) |

Open-jaw: `LEG_AMBIGUOUS` → keep Qwen/semantic for preference clarification; still **block false lead**.

## Recommended implementation shape

**RECOMMENDED_CQ46_IMPLEMENTATION=B — SOFT_PREFERENCE_STATE**

1. Lead override via progressive `ranking_refinement` / `time_refinement` (prior travel or pending confirmation).
2. Persist prefs in shopping_state (Hybrid already sets `ranking_preference`; `time_preference` via TravelIntent/patcher).
3. Do **not** add ranking/time to confirmation snapshot / `isMaterialCorrection` in the first fix PR.
4. Do **not** force `deterministicAuthorityComplete` until PERF impact is measured.
5. Optional later (not CQ46-minimal): wire AI deep-link `sort` / `departure_window` (moves toward C for execution only).

Rejected for first fix:

- **A** full CQ45-parity deterministic short-circuit — premature
- **C** material confirmation — snapshot/search contract not ready
- **D** lead-name blacklist — violates CQ45 principle
