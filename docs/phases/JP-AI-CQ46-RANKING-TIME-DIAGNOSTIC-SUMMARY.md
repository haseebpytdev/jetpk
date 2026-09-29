# JP-AI-CQ46 — Ranking & time preference lead-authority diagnostic

## Status

`CQ46_DIAGNOSTIC=PASS` — diagnostic only. **No application changes. No deploy.**

## Baseline

Main `15ce02cc…` · Runtime `c115cc57…` (CQ45 certified)  
False-lead family confirmed: `cheapest` / `fastest` / `morning` (and broader resolver vocab) → `lead_name` + contact stage.

## Root gap

1. `TravelConstraintResolver` already resolves ranking + time.
2. `ServerTravelSignals::progressiveTravelAuthority` exposes stop/cabin/date/pax/route — **not** ranking/time.
3. Therefore `isTravelAuthorityTurn=false` and `looksLikeBareName=true`.
4. Unambiguous pending-lead wins → lead FSM steals the turn before hybrid/confirmation.

## Dual problems

| A Lead precedence | B Search semantics |
| --- | --- |
| Must block false lead | Deep-link omits `sort` / `departure_window`; results page already supports them |
| Fixable via progressive authority | Separate wiring; not required for A |

## Hybrid / patch / confirmation proofs

- Hybrid sets `rankingPreference` + `state.ranking_preference`; `TravelIntent.timePreference` + state time.
- `ConversationStatePatcher` / `TravelIntent::toArray` persist **time**, not **ranking**.
- Confirmation snapshot / `snapshotsEqual` include `max_stops`, **not** ranking/time.

## Recommendation

**B — SOFT_PREFERENCE_STATE**

- Lead override via `ranking_refinement` / `time_refinement` when resolver hits.
- Persist soft prefs in shopping_state.
- Do **not** make confirmation material / do **not** blind-copy CQ45 into `deterministicAuthorityComplete`.
- Open-jaw: block false lead; keep semantic for leg ambiguity.
- Optional later: AI deep-link `sort` / `departure_window`.

## Evidence

`docs/evidence/jp-ai-cq46-ranking-time-authority-diagnostic/`  
Test: `tests/Feature/Ai/Cq46RankingTimeAuthorityDiagnosticTest.php` (1 test, 48 assertions)

## Flags

CQ44_PERFORMANCE_PROGRAM=CLOSED  
PERMANENT_QWEN_OWNER_DECISION=APPROVED  
IFRAME_PILOT=HOLD  
READY_FOR_IMPLEMENTATION_REVIEW=YES · READY_FOR_MERGE=NO · READY_FOR_DEPLOY=NO
