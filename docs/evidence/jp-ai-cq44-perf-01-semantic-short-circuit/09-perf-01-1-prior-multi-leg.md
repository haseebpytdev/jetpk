# CQ44-PERF-01.1 — Prior multi-leg bypass safety

## Gap

`deterministicAuthorityComplete()` already kept Qwen when the **current message**
contained 2+ open-jaw legs (`open_jaw_multi_leg`).

It did **not** reject short-circuit when **prior shopping state** was already
multi-leg (`trip_type=open_jaw` or `legs` count ≥ 2). Bare refinements such as
`next Friday` could classify as `date_refinement` with
`DETERMINISTIC_AUTHORITY_COMPLETE=YES`, which is unsafe without explicit leg target.

## Guard

In `ServerTravelSignals::deterministicAuthorityComplete()`:

```
$priorMultiLeg =
    ($priorState['trip_type'] ?? null) === 'open_jaw'
    || (is_array($priorState['legs'] ?? null) && count($priorState['legs']) >= 2);

if ($priorMultiLeg) {
    return complete=false, reason=prior_multi_leg_requires_semantic;
}
```

No new phrase regex. Structure-only. No leg-target inference in this phase.

## Proof

| Gate | Result |
| --- | --- |
| PRIOR_MULTI_LEG_SHORT_CIRCUIT_GUARD | PASS |
| PRIOR_OPEN_JAW_DATE_QWEN | PASS (provider callCount increases on `next Friday`) |
| PRIOR_OPEN_JAW_REFINEMENT_QWEN | PASS (provider callCount increases on `Make it Doha`) |
| SIMPLE_ONEWAY_REFINEMENT_BYPASS | PASS (existing feature test) |
| SIMPLE_RETURN_CONTEXTUAL_DATE_BYPASS | PASS (`trip_type=return`, single leg, `wapis Sunday`) |
| OPEN_JAW_CURRENT_TURN_QWEN | PASS (`open_jaw_multi_leg` / feature essential path) |

## Classification note

Original 10-call CQ43 soak audit unchanged. Prior multi-leg refinements were not
part of that 10-call sample; historical CQ43 latency data is not altered.

AFTER_QWEN_CALL_RATE remains **PROJECTED**.
AFTER_SEMANTIC_P50/P95 = N/A_REAL_QWEN_NOT_RERUN.
