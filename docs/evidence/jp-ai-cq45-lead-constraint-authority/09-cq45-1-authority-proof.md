# CQ45.1 — Authority proof & evidence hardening

Branch: `work/jp-ai-cq45-lead-constraint-authority`  
PR: #50  
Previous head: `c634424d408603d8cd8d965bb9739d7ffac4712c`  
Scope: TEST / EVIDENCE only. No application code changes. No ranking/time fix. No PERF. No merge/deploy.

## Strengthened proofs

### PRIOR_OPEN_JAW_DIRECT_QWEN=PASS

- Deterministic complete remains `false` / `prior_multi_leg_requires_semantic`.
- Retained `ScriptedInferenceProvider` instance; measured `$provider->callCount() === $before + 1`.
- After `"direct only"`: `lead_name=null`, `trip_type=open_jaw`, two legs preserved, `AI_FLIGHT_SEARCH_READ_CALLS=0`, `WRONG_ROUTE_ACTION_READY=0`.

### BARE_YES_CONFIRMATION_AUTHORITY=PASS

Endpoint fixture (Closure29-style hybrid, semantic planner off):

- Seed: KHI→JED dated, 2 adults, pending confirmation, `lead_capture_pending=true`, stage=`name`, `lead_name=null`.
- Send: `Yes`
- Result: `CONFIRMATION_BEFORE_SEARCH=true`, `AI_FLIGHT_SEARCH_READ_CALLS=1`, `lead_name=null`, pending cleared, lead stage does not advance to contact.

### BARE_NO_CONFIRMATION_AUTHORITY=PASS

Same seed; send `No`:

- `lead_name=null`, pending cleared, search reads=0, stage remains `name`.

### DIRECT_ONLY_HISTORICAL_REPRO=PASS

Unchanged CQ43 residual expectations: `max_stops=0`, route/pax preserved, `MODEL_CALLS=0`, false lead capture=0.

### RELATED ranking/time — endpoint repro (no fix)

| Phrase   | LEAD_NAME_AFTER | LEAD_STAGE_AFTER | RANKING_AFTER | TIME_PREFERENCE_AFTER | MODEL_CALLS | STATUS |
| -------- | --------------- | ---------------- | ------------- | --------------------- | ----------- | ------ |
| cheapest | cheapest        | contact          | null          | null                  | 0           | ok     |
| fastest  | fastest         | contact          | null          | null                  | 0           | ok     |
| morning  | morning         | contact          | null          | null                  | 0           | ok     |

- `RELATED_CONSTRAINT_FALSE_LEAD_REPRODUCED=YES`
- `CQ46_RANKING_TIME_LEAD_AUTHORITY_REQUIRED=YES`
- Confirmation snapshot does **not** include `ranking_preference` / `time_preference` — do not copy CQ45 `stop_refinement` into `deterministicAuthorityComplete` blindly.

## Gates

- Backend: 185/185 PASS, 1813 assertions (local PHPUnit authoritative)
- Frontend continuity 11/11 PASS · typecheck PASS
- PERF-02 non-regression: suite included; PASS
- Safety: SEARCH_BEFORE_CONFIRMATION=0; only Bare Yes fixture produces exactly one authorized read

## Status

CQ45_1_STATUS=PASS  
READY_FOR_REVIEW=YES · READY_FOR_MERGE=NO · READY_FOR_DEPLOY=NO
