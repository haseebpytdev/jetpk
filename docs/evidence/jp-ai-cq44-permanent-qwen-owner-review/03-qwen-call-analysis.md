# CQ44 — Qwen necessity review

## Counts (CQ43 final soak SUMMARY)

| Metric | Value |
| --- | --- |
| QWEN_MODEL_CALLS_TOTAL | 13 |
| SEMANTIC_FALLBACK_COUNT | 4 |
| GENERAL_RETRY_COUNT | 0 |
| MODEL_CRASHES | 0 |
| LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK | 0 |

## Role check

On primary soak, ~10/42 turns invoked the semantic brain; ~32/42 were hybrid/deterministic and finished in tens of milliseconds.

Intended advisory uses observed:

- destination-led progressive capture
- ambiguous / open-jaw-ish planning
- open-domain GK / CURRENT
- some route-reset phrasing

Deterministic server paths already own:

- confirmation restates after slots filled
- bare pax/cabin/date refinements once pending confirm exists
- lead FSM name/contact once pending
- handoff / Resume AI
- booking-not-found public contract

## Unnecessary invocation candidates (evidence-only — do not implement here)

| Candidate | Evidence support |
| --- | --- |
| Skip model for unambiguous deterministic refinements that still hit planner | Some Qwen calls on turns that ultimately produced STRUCTURED confirm/clarify with high server determinism (e.g. adult count / destination correction still paid ~10–11s model) |
| Reduce fallback tax on `invalid_plan` | Slowest turn spent 22.5s in model then fell back successfully |
| Prompt/context reduction for progressive OD fills | Early progressive turns are consistently 8–12s model-bound |
| Runtime warm / concurrency tuning | Suspected but **N/A** without cold-start telemetry |
| Cache safe semantic results | Not measured; listed only as future track hypothesis |

## PERFORMANCE_OPTIMIZATION_CANDIDATES

1. Skip or short-circuit Qwen when deterministic travel authority already resolves the turn
2. Reduce semantic fallback cost / avoid long invalid_plan model waits when hybrid can answer
3. Shrink planner prompt context for progressive OD/date fills
4. Local runtime warm-path / concurrency tuning (needs new telemetry)
5. Optional safe semantic response cache for repeated clarify shapes
