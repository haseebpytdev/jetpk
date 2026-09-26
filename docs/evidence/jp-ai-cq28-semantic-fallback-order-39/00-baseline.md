# JP-AI-CQ28-SEMANTIC-FALLBACK-ORDER-39 — Baseline / root cause

BASE_MAIN=1602ec2969ce15d17dd3bb92750cece48d4c6de2  
BRANCH=work/jp-ai-cq28-semantic-fallback-order-39  
PRODUCTION_QWEN=OFF  
PUBLIC_AI_CERTIFIED=YES  
DEPLOYED=NO

## Reproduce (Canary 38 + post-fix local)

Exact primary: `Lahore to Dubai tomorrow for 2 adults`  
Flags (process-local / canary window): conversational=true, semantic_planner=true, semantic_composer=false

### Before fix (Live Canary 38)

| Field | Value |
|---|---|
| SEMANTIC_ATTEMPTED | YES (planner called; result dropped) |
| PLAN_RETURNED | often null / invalid → SemanticBrain returned null |
| PLAN_VALID_JSON | mixed |
| VALIDATOR_VALID | N/A when null |
| SEMANTIC_FALLBACK_REASON | lost at orchestrator (null swallow) |
| FINAL_MODE | LLM_ASSISTED |
| LEAD_CAPTURE_PENDING | soft-pending possible |
| FINAL_MESSAGE | PII-first name/email/phone |

ROOT_CAUSE_CLASS=LEGACY_LLM_TOOK_OVER after PLANNER_NULL / lost telemetry  
LEAD_GATE_PREEMPTED=NO (lead soft-pending already yields to strong travel; monopoly was LLM path)

### After fix (scripted + real-model local)

See `FINAL-REPORT.md` / `r1-real-model-results.json`.
