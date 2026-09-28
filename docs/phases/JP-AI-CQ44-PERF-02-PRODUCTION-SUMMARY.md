# JP-AI-CQ44-PERF-02 — Production certification summary

## Phase

CQ44-PERF-02 MERGE, DEPLOY & PRODUCTION CERTIFICATION

## Merge / deploy

| Field | Value |
| --- | --- |
| PR48_MERGED | YES |
| MERGE_SHA | `9901f285e06c85f550191307ef29c9298904a32c` |
| MAIN_SHA_AFTER_MERGE | `9901f285e06c85f550191307ef29c9298904a32c` |
| PREVIOUS_RUNTIME_SHA | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |
| DEPLOYED_RUNTIME_SHA | `9901f285e06c85f550191307ef29c9298904a32c` |
| DEPLOY_MARKER | `9901f285e06c85f550191307ef29c9298904a32c` |
| APPLICATION_CODE_PARITY | PASS |
| Files deployed | `SemanticBrain.php`, `ServerTravelSignals.php`, `LocationResolver.php` |
| Rollback target | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |

## Config parity (unchanged)

CONVERSATIONAL_ENABLED=true · SEMANTIC_PLANNER_ENABLED=true · SEMANTIC_COMPOSER_ENABLED=false · AI_EMBED_ENABLED=false · brain_enabled=true

PERMANENT_QWEN_OWNER_DECISION=APPROVED · IFRAME_PILOT=HOLD

## Production result

**CQ44_PERF_02_PRODUCTION=PASS**

- CURRENT_UNVERIFIED fast path: Bitcoin 110.6ms / Weather 46.8ms, MODEL_CALLS=0
- Complete dated A→B fast path: five fresh routes MODEL_CALLS=0 (~45–56ms)
- Via/third-location: explicit_route_complete BLOCKED
- Direct + airline constraints preserved
- Open-jaw / ambiguous / no-date / return / prior multi-leg controls remain semantic
- Closure29 + PERF-01 progressive non-regression PASS
- Comparable RUN2: **3/41** Qwen calls (was 5/41 PERF-01, 10/41 CQ43)
- Total p95 5687ms; max 7363ms; >20s=0; >30s=0
- Browser ≥15 turns continuity PASS; safety counters zero

## Remaining Qwen calls (essential only)

1. `I need Dubai` — destination-led  
2. `What is gravity?` — GK  
3. `What is photosynthesis?` — GK post-resume  

## Evidence

`docs/evidence/jp-ai-cq44-perf-02-production/`

## Exclusions

No PERF-03. No application code changes during certification. No iframe enablement. No Qwen model/runtime changes.
