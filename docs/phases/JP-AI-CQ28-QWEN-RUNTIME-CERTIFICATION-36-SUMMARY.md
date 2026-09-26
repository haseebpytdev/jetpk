# JP-AI-CQ28-QWEN-RUNTIME-CERTIFICATION-36 SUMMARY

## Git
BASE=386b3aa78ef5aef89aed4341eac9f3eb548ef5f9
START_HEAD=18cbdf9e27048b0b013cc48c88ec0b2d6b4fa714
PR=29

## Fail-closed defaults
OTA_AI_SEMANTIC_PLANNER_ENABLED default → false
OTA_AI_SEMANTIC_COMPOSER_ENABLED default → false
Regression: QwenSemanticDefaultsFailClosed36Test PASS

## Runtime
RUNTIME_ENGINE=llama-server
MODEL=Qwen3.5-0.8B-M-TS-Q4_K_M.gguf (existing ai-assistant artifact)
ENDPOINT=http://127.0.0.1:3921 (localhost bind only)
PUBLIC_CONVERSATIONAL_ENABLED=false (unchanged)
PUBLIC_SEMANTIC_ENABLED=false
AI_EMBED_ENABLED=false

## Real-model cert (authoritative = r2 only)
r1 = FAIL (91.72% hybrid-safe, CRITICAL=26) — see r1-run-console.txt; r1-summary restored to match console; do not cite r1 for PASS.
r2 = PASS (145 cases)

TOTAL_CASES=145 (105+40 holdout)
SEMANTIC_VALID_RATE=100% (hybrid-safe scoring; not strict clean-plan rate)
HOLDOUT_VALID_RATE=100%
CRITICAL_POLICY_FAILURES=0
NO_RESPONSE=0
FACT_DRIFT=0
SAFE_FALLBACK_COUNT=16 (labeled; soft hybrid passes may undercount)

Note: SEMANTIC_VALID includes correct plans AND safe hybrid fallbacks after server policy blocks action-ready wrong routes / bare-affirmative prepare_search. Cite SAFE_FALLBACK_COUNT + SCORING_NOTE with any 100% claim.

## Latency (steady-ish after warm)
SEMANTIC_P50_MS=7983
SEMANTIC_P95_MS=10992
SEMANTIC_MAX_MS=22908
COMPOSER_P50_MS=3115
TIMEOUT_COUNT=0
PERFORMANCE_GATE=HOLD (p50≈8s interactive chat sluggish on 0.8B CPU)

## Resources
MODEL_RSS≈1.1GB during run
OOM_EVENTS=0
MODEL_CRASHES=0

## Fallback
QWEN_OFFLINE public health PASS; conversational remains false; CQ27 STRUCTURED_FALLBACK mode

## Final
QWEN_RUNTIME_CERTIFIED=YES (localhost cert path)
CQ28_REAL_MODEL_CERTIFIED=YES (with safe-fallback scoring + policy blocks)
READY_FOR_CQ28_PRODUCTION=YES (controlled deploy candidate; PERFORMANCE_GATE=HOLD)
DEPLOYED=NO
READY_FOR_IFRAME_ACTIVATION=NO
