# CQ28 runtime probe (read-only)

Probed: 2026-09-25 (production host 185.215.166.176)

## Config (sanitized)

OTA_AI_ASSISTANT_MODE=public
OTA_AI_ASSISTANT_ENABLED=true
OTA_AI_CONVERSATIONAL_ENABLED=false
OTA_AI_OPTIONAL_LLM_ASSIST=false
OTA_AI_GATEWAY_URL=(empty → default http://127.0.0.1:3921)
OTA_AI_MODEL_ID=(empty → default local)
AI_EMBED_ENABLED=false

## Gateway

PROBE_GATEWAY=http://127.0.0.1:3921
/health = FAIL (connection refused)
/v1/models = FAIL (connection refused)

## Other local inference

Ollama listens on 127.0.0.1:11434 (observed llama3.1:8b-instruct-q4_0).
Configured Ask JetPakistan path does **not** use Ollama; LocalLlamaProvider requires
localhost OpenAI-compatible gateway at OTA_AI_GATEWAY_URL (default :3921).

## Verdict

INFERENCE_PROVIDER=null (conversational disabled → NullInferenceProvider)
GATEWAY=http://127.0.0.1:3921 (down)
MODEL_ID_CONFIG=(empty/local)
MODEL_LOADED=NO
MODEL_HEALTH=FAIL
MODEL_API=FAIL
QWEN_RUNTIME_ACTIVE=NO

CQ28 implements semantic planner + stubbed tests + hybrid fallback.
Real-model ≥100-turn eval = TEMPORARILY_BLOCKED until Qwen gateway is brought up
and OTA_AI_CONVERSATIONAL_ENABLED + OTA_AI_MODEL_ID are configured (no deploy in this phase).
