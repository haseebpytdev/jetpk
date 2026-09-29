# JP-AI-CQ46 — Soft Preference Authority Summary

## Objective

Close ranking/time false-lead residual (`cheapest` / `fastest` / `morning` → `lead_name`) via progressive authority + server-owned soft preference persistence — without confirmation-material changes or AI deep-link preference wiring.

## Branch / PR

| Item | Value |
| --- | --- |
| Branch | `work/jp-ai-cq46-soft-preference-authority` |
| Base | post-PR52 main `699832fdf5199ad52d2603c89734b0dd7bc7d680` |
| Application PR | NEW (not PR #52) |
| Deploy | NO |

## Included scope

1. `ServerTravelSignals::progressiveTravelAuthority` — `ranking_refinement` / `time_refinement` from `TravelConstraintResolver` (active + travel_refinement with prior; never travel_start alone; not in `deterministicAuthorityComplete`).
2. `AiChatOrchestrator::trySoftTravelPreferenceTurn` — simple single-leg soft-preference-only path; Hybrid-owned ranking/time persist; pending confirmation preserved; honest soft ack.
3. Bare `business` cabin token aligned with bare `economy` for mixed cabin+ranking.
4. Feature tests + evidence.

## Excluded scope

- Confirmation snapshot schema / material correction for ranking/time
- `AiShoppingTools::searchFlights` / results deep-link preference query params
- Qwen model/runtime changes
- PERF reopen / iframe
- CQ47 preference execution wiring

## Root cause

Resolver already owned ranking/time vocabulary; progressive signals did not expose them → lead FSM stole bare preference turns.

## Files changed

- `app/Services/Ai/Hybrid/ServerTravelSignals.php`
- `app/Services/Ai/AiChatOrchestrator.php`
- `app/Services/Ai/Hybrid/TravelConstraintResolver.php` (bare business)
- `tests/Feature/Ai/Cq46SoftPreferenceAuthorityTest.php` (new)
- `tests/Feature/Ai/Cq46RankingTimeAuthorityDiagnosticTest.php` (assert updates)
- `docs/evidence/jp-ai-cq46-soft-preference-authority/*`
- `docs/phases/JP-AI-CQ46-SOFT-PREFERENCE-AUTHORITY-SUMMARY.md`

## Tests

- CQ46 soft preference suite + diagnostic
- CQ45 / CQ44 PERF / CQ43 / CQ42 / Qwen / Public AI / booking presenter regression
- Frontend Ask JetPakistan continuity 11/11 + typecheck

BACKEND: 195 passed / 0 failed / 2051 assertions  
FRONTEND_CONTINUITY=PASS  
FRONTEND_TYPECHECK=PASS

## Status

READY_FOR_REVIEW=YES  
READY_FOR_MERGE=NO  
READY_FOR_DEPLOY=NO  
NEXT_OPTIONAL_PHASE=CQ47_PREFERENCE_EXECUTION_WIRING  
CQ44_PERFORMANCE_PROGRAM=CLOSED  
PERMANENT_QWEN_OWNER_DECISION=APPROVED  
IFRAME_PILOT=HOLD
