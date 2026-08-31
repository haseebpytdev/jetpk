# Language / TravelIntent quality

## Local model (0.8B)

With thinking disabled, M quant emits JSON but:

- intent labels often wrong (`travel` vs `flight_search`)
- city names instead of reliable IATA (partially mitigated by app normalizer)
- invents airlines/budgets/dates
- follow-ups hallucinate unrelated routes
- injection probe produced fake “password: 123456” text (not a real secret; still unsafe behavior)
- S quant often returned empty / non-JSON for the same prompts

`AI_08B_QUALITY_GATE=FAIL`

## Structured no-LLM fallback (product path)

| Slice | Intent % | Critical field % |
|-------|----------|------------------|
| English (20) | 100 | 70 |
| Roman Urdu (20) | 100 | 65 |
| Urdu (15) | 86.7 | 53.3 |
| Mixed (5) | 100 | 60 |
| Aggregate intent | **95.6** | |
| Aggregate critical | **62.8** | |

These scores are for the **structured parser** that powers `STRUCTURED_FALLBACK` / hybrid extraction (structured owns routes).

## Decision

`AI_MODEL_DECISION=BLOCKED_LOCAL_MODEL`

Retain: native chat, structured assistant, ranking, short links, knowledge, human handoff, provider abstraction.
Do **not** install permanent llama-server on this VPS in this phase.
