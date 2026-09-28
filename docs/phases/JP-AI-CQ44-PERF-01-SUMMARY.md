# JP-AI-CQ44-PERF-01-SUMMARY

## Phase

CQ44-PERF-01 — Qwen latency optimization via deterministic semantic short-circuit.

CQ44-PERF-01.1 — Prior multi-leg / open-jaw bypass safety.

## Branch

`work/jp-ai-cq44-perf-01-semantic-short-circuit`

## Objective

Skip Qwen when shared server authorities fully resolve material travel refinements. Preserve CQ43-certified behavior and essential Qwen paths. Never short-circuit bare refinements when prior state is already multi-leg.

## Changes

| File | Change |
| --- | --- |
| `app/Services/Ai/Hybrid/ServerTravelSignals.php` | `deterministicAuthorityComplete()` + PERF-01.1 `prior_multi_leg_requires_semantic` |
| `app/Services/Ai/Semantic/SemanticBrain.php` | Early planner bypass before `plan()` |
| `app/Services/Ai/AiChatOrchestrator.php` | Force hybrid (no legacy LLM) on bypass |
| Tests | Unit + `Cq44PerfDeterministicShortCircuitTest` (incl. prior open-jaw Qwen) |

## PERF-01.1 guard

Treat prior state as multi-leg if `trip_type === 'open_jaw'` OR `legs` array count ≥ 2.

Return `complete=false`, `reason=prior_multi_leg_requires_semantic`.

No leg-target inference. No new phrase regex.

## Results

- CQ44_PERF_01_STATUS=PASS
- PRIOR_MULTI_LEG_SHORT_CIRCUIT_GUARD=PASS
- PRIOR_OPEN_JAW_DATE_QWEN=PASS
- PRIOR_OPEN_JAW_REFINEMENT_QWEN=PASS
- SIMPLE_ONEWAY_REFINEMENT_BYPASS=PASS
- SIMPLE_RETURN_CONTEXTUAL_DATE_BYPASS=PASS
- OPEN_JAW_CURRENT_TURN_QWEN=PASS
- Unnecessary simple refinements still bypassed (origin/date/pax/cabin/dest/return)
- Dest-led, explicit route, current-turn open-jaw, GK/CURRENT remain active
- PHPUnit 149/149, 1424 assertions (see `phpunit-out.txt`)
- Frontend continuity 11/11 + typecheck PASS
- No deploy; runtime stays `24dbf524`

## Performance claims

- AFTER_QWEN_CALL_RATE = PROJECTED (simple refinements decrease; multi-leg keep Qwen)
- AFTER_SEMANTIC_P50_MS = N/A_REAL_QWEN_NOT_RERUN
- AFTER_SEMANTIC_P95_MS = N/A_REAL_QWEN_NOT_RERUN

## Remaining candidates

- Explicit A→B short-circuit (v2, needs stronger proof)
- Planner context shrink for essential calls
- Runtime warm/concurrency telemetry
- Authorized real-Qwen re-soak for SEMANTIC_P95 after deploy
- Optional future: leg-target inference for multi-leg date refinements (out of PERF-01.1)
