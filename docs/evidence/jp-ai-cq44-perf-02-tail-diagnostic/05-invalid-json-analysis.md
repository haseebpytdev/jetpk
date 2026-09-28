# CQ44-PERF-02 — invalid_json analysis

## Observed chat events

Primary explicit-route batch (`02-explicit-route-samples.json`):

| # | Message | SEMANTIC_FALLBACK_REASON | TOTAL_MS | SEM_MS | Final OD/date |
|---|---|---|---|---|---|
| 0 | Now Islamabad to Dubai next Monday | invalid_json | 13184 | 12900 | ISB→DXB 2026-10-05 |
| 1 | Lahore to Dubai next Monday | (valid) | 7629 | 7524 | LHE→DXB 2026-10-05 |
| 2 | Islamabad to Dubai next Monday | invalid_json | 13313 | 13240 | ISB→DXB 2026-10-05 |
| 3 | Karachi to Jeddah tomorrow | (valid) | 9182 | 9068 | KHI→JED 2026-09-29 |
| 4 | Dubai to Lahore tomorrow | (valid) | 10178 | 10072 | DXB→LHE 2026-09-29 |
| 5 | Islamabad se Dubai kal | invalid_json | 9836 | 9742 | ISB→DXB 2026-09-29 |

Pattern: all three `invalid_json` events were Islamabad/ISB utterances. Non-ISB clear A→B samples in the same batch were valid JSON.

Supplemental burst (`05b-invalid-json-burst.json`): 0/10 `invalid_json` (5× Bitcoin + 5× Now Islamabad…). Confirms intermittency.

CURRENT primary + burst: 0/10 `invalid_json` this session (all `FINAL_RESPONSE_SOURCE=SEMANTIC` safe market refusal). Prior PERF-01 RUN2 still observed Bitcoin `invalid_json` (~27s) — treat as intermittent, not eliminated.

## Direct raw-output probes (`05-invalid-json-probes.json`)

Separate HTTP completions (same planner prompt) captured raw shape:

1. **Now Islamabad to Dubai next Monday** → `VALID_JSON_OBJECT`, but semantic mislabel: `intent=open_jaw` / `trip_type=open_jaw` for a clear one-way A→B. finish_reason=stop. Not truncated.
2. **Bitcoin price** → `VALID_JSON_OBJECT`, `domain=current`, safe limitation intent. ~5.5s.
3. **Lahore to Dubai next Monday** → `VALID_JSON_OBJECT`.

## Classification

| Question | Finding |
|---|---|
| raw output present on failed chat turns? | Not captured in-process (provider discards body on decode fail). |
| Direct probes truncated? | NO (`finish_reason=stop`, braces balanced). |
| markdown fenced? | NO in captured probes. |
| schema mismatch (valid JSON, wrong semantics)? | YES — ISB A→B emitted as `open_jaw` in one probe. |
| empty? | NO in probes. |
| token cutoff? | NO in probes (`finish_reason=stop`). |

**INVALID_JSON_PRIMARY_CLASS (chat failures):** `UNKNOWN` for exact raw shape of the 3 decode failures (body not retained).

**Correlated secondary class (valid JSON, wrong plan):** `SCHEMA_MISMATCH` / semantic mislabel (`open_jaw` on clear A→B).

**INVALID_JSON_ROOT_CAUSE:** Intermittent planner decode failure concentrated on Islamabad A→B prompts; when JSON is valid, Qwen may still invent open-jaw structure. Hybrid fallback always rebuilt the correct one-way confirmation (~150ms est after Qwen wait).

Allowed structural evidence only — no hidden reasoning logged.
