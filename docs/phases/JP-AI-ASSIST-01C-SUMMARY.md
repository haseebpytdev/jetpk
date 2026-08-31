# JP-AI-ASSIST-01C — SUMMARY

## Phase name
JP-AI-ASSIST-01C

## Branch
phase/jp-flight-perf-01

## Objective
Empirical tiny-model benchmark on same VPS + native JetPakistan chat + TravelIntent → app tools + ranking + no-LLM fallback + human handoff + Chatwoot/WhatsApp contracts.

## Included
- Qwen3.5-0.8B GGUF empirical load/quality (M + S)
- InferenceProvider abstraction (LocalLlama / Null / OpenAICompatible stub)
- TravelIntent + structured fallback + hybrid extractor
- Native public chat + FAB Ask JetPakistan
- Staff AI support queue / takeover
- Read-only flight deep-links + group search + short links
- Deterministic ranking, knowledge md, contracts

## Excluded
- Permanent llama-server (quality gate FAIL)
- 2B model test (not executed; architecture safe without it)
- Chatwoot install / WhatsApp API
- Live supplier mutation / payments
- Push

## Investigation findings
- Prior BLOCKED_CAPACITY superseded: 0.8B ~693–780 MB RSS, ~80% RAM headroom, swap Δ0
- llama.cpp b6770 cannot load qwen35; b10726 required
- Model fills reasoning_content unless enable_thinking=false; even then schema fidelity poor

## Root causes
- Capacity was not the blocker; **intent quality** of 0.8B is insufficient for autonomous TravelIntent
- Product path must be structured/deterministic with optional model assist

## Files changed
See git commits for exact paths under app/Services/Ai, Controllers, Models, migration, frontend ai-assistant, ai-assistant/*, routes, config/ota.php, tests/Feature/Ai, docs/evidence/jp-ai-assist-01c.

## Tests
`php artisan test --filter=PublicAiAssistantTest` → 6 passed, 33 assertions.
Structured language bench: intent 95.6%, critical 62.8%.

## Final status
AI_MODEL_DECISION=BLOCKED_LOCAL_MODEL  
Native chat/support architecture retained.  
SAFE_TO_PUSH=NO
