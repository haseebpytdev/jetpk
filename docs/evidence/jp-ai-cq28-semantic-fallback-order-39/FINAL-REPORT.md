# FINAL REPORT — JP-AI-CQ28-SEMANTIC-FALLBACK-ORDER-39

## Verdict

Engineering PASS. Production remains dormant (QWEN OFF, DEPLOYED=NO).  
READY_FOR_LIVE_QWEN_RETRY=YES (planner canary may be re-attempted after review; this PR does not enable it).

## ROOT_CAUSE

```
PRIMARY_TURN_SEMANTIC_ATTEMPTED=YES
PRIMARY_FALLBACK_REASON=invalid_json|null_plan (telemetry previously dropped)
LEAD_GATE_PREEMPTED=NO
LEGACY_LLM_TOOK_OVER=YES (pre-fix)
ROOT_CAUSE_CLASS=LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK
```

## FIX

```
SEMANTIC_FALLBACK_TELEMETRY=YES (kind=fallback + SEMANTIC_BRAIN_* meta)
STRONG_TRAVEL_DIRECT_TO_HYBRID=YES
LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK=0
LEAD_SOFT_PENDING_PRESERVED=YES
DESTINATION_ONLY_CORRECTION=YES (LocationResolver: "make that Doha instead")
```

## PRIMARY_VALID_QWEN (scripted)

```
MODE=QWEN_SEMANTIC
STATUS=confirm
SEARCH_CALLS=0
PII_FIRST=NO
Yes → SEARCH_CALLS=1
```

## PRIMARY_FORCED_FALLBACK (scripted)

```
MODE=STRUCTURED_FALLBACK
STATUS=confirm
SEARCH_CALLS=0
PII_FIRST=NO
origin=LHE destination=DXB adults=2
LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK=0
```

## REAL_MODEL (local Laravel + tunneled :3921; prod flags untouched)

```
RUNS=10
QWEN_SEMANTIC_SUCCESS=10
SAFE_HYBRID_FALLBACKS=0
WRONG_ROUTE_ACTION_READY=0
PII_FIRST=0
SEARCH_BEFORE_CONFIRMATION=0
P50_MS=7783
P95_MS=8801
REAL_MODEL_10X_LOCAL=PASS
```

Follow-ups: wife+Dubai → confirm LHE-DXB; Doha correction → confirm LHE-DOH; Urdu primary → confirm; open-jaw → clarify OPEN_JAW=YES.

## TESTS

159 passed (ORDER-39 + CQ26/27/28 + closure29 + lead + public + embed + tenant).  
See `phpunit-regression.txt`.

## PRODUCTION

```
CONVERSATIONAL_ENABLED=false
SEMANTIC_PLANNER_ENABLED=false
SEMANTIC_COMPOSER_ENABLED=false
DEPLOYED=NO
```

Llama on prod was started for local tunnel gate only, then stopped (`QWEN_STOPPED`).
