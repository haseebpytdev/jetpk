# JP-AI-CQ44-PERF-01-PRODUCTION-SUMMARY

## Objective

Merge PR #45, deploy exact MERGE_SHA, measure real-Qwen production effect of deterministic short-circuit + prior multi-leg guard.

## Merge / deploy

| Field | Value |
| --- | --- |
| PR45 | squash-merged |
| MERGE_SHA | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |
| PREVIOUS_RUNTIME | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| DEPLOYED_RUNTIME | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |
| Config | unchanged (planner on, composer off, iframe off) |

## Production results

- Focused bypass: origin/date via SemanticBrain short-circuit; pax/cabin/dest/return via hybrid pending authority with MODEL_CALLS=0
- FROM_LAHORE invalid_plan tax removed (≈39ms vs prior ~22s)
- Prior multi-leg guard keeps Qwen
- Essential Qwen paths remain (dest-led, explicit route, open-jaw, GK/CURRENT)
- Primary comparable session: **4/41** Qwen calls (was 10/42)
- Total p50 **40ms**, total p95 **6371ms** (was 8006 / 13150)
- Browser continuity 15/15 PASS

## Status

`CQ44_PERF_01_PRODUCTION=PASS`

No PERF-02 changes. Evidence-only PR follows.
