# Model decision

## Gates

| Gate | Result |
|------|--------|
| Resource | **PASS** |
| Quality | **FAIL** |
| OTA regression | **NO** |
| Wrong confident | **62** (fail) |
| Lineage / license | **PASS** |

## Case mapping

- Not CASE A (quality fail)
- **CASE B**: resource PASS, quality below target (critical 57.4% vs ≥90%; English/Roman Urdu/Urdu all weak)
- Not CASE C (resource did not fail)
- Not CASE D alone (Urdu is weak, but English/Roman Urdu are also weak — not “Urdu-only” failure)

```
AI_MODEL_CANDIDATE_APPROVED=NO
AI_MODEL_DECISION=TEST_~3B_IN_AI_01E
URDU_PRIMARY_FAILURE=NO
DEDICATED_TRANSLATION_MODEL_NEEDED=MAYBE_LATER
```

Do **not** permanently activate Ask JetPakistan V1 on this 1.7B candidate.

Retain: structured parser first, validation, tools, ranking, handoff.

Next: JP-AI-ASSIST-01E ~3B local benchmark (do not auto-download in this phase).

```
AI_CONCURRENCY_TESTED=1
AI_RECOMMENDED_CONCURRENCY=1
```

Concurrency 2 not attempted (quality already failing; CPU ~200% on 2 threads).
