# R4 REAL_MODEL_UAT

HEAD: b40457d51201f8efa8e6ee1f07285e5718da6010
Gateway: LocalLlamaProvider via SSH tunnel → prod llama-server :3921
Model: Qwen3.5-0.8B-M-TS-Q4_K_M.gguf (temp UAT; ctx=4096)
Harness: docs/evidence/.../r4-real-model-uat.php (fresh conversation per prompt)
Browser: http://127.0.0.1:8010/api/public/ai/health → gateway healthy / local_llama

## Results

| Prompt | Mode | Gate |
|---|---|---|
| What is JetPakistan? | LLM_ASSISTED grounded RAG | PASS |
| What is E=mc2? | STRUCTURED_FALLBACK (natural physics answer) | PASS |
| Where can I buy a jet? | LLM_ASSISTED smart redirect | PASS |
| Tell me a joke | STRUCTURED_FALLBACK casual | PASS |
| Apple's stock price right now? | FALLBACK_STRUCTURED limitation; no price/up-down | PASS |
| Lahore to Dubai tomorrow 2 adults | help-first flight assist (no blocking name-only loop) | PASS |

## Variation (3× "Tell me a short joke about cats.")

- Runs 0–1: LLM_ASSISTED natural jokes (temperature=0 → identical wording OK)
- Run 2: misrouted travel shell (non-blocking for variation goal)
- Verdict: real model generated customer-visible wording (LLM_ASSISTED observed)

## CURRENT_UNVERIFIED note

Positive limitation + qualitative live-claim rejection covered by PHPUnit scripted cases A–F.
Live model path returned structured limitation fallback (no fabricated price/direction).

## Cleanup

Temp llama-server stopped after UAT; GGUF retained under ai-assistant/models for future local UAT.
PROD conversational remains disabled (not deployed).
