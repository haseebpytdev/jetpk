# Resource results (0.8B empirical)

## R4 — M quant (Q4_K_M) accurate RSS

| Metric | Value |
|--------|-------|
| MODEL_LOAD_SECONDS | ~4–5 |
| AI_IDLE_RAM | ~693 MB RSS |
| AI_ACTIVE_RAM_MAX | ~780 MB RSS |
| SYSTEM_RAM_AVAILABLE_MIN (during AI) | ~9452–9823 MB |
| AI_RAM_HEADROOM_PCT | ~79–82% |
| AI_SWAP_DELTA | 0 MB |
| AI_LISTENS_PUBLICLY | NO |
| OOM_EVENTS | 0 |
| PM2_RESTARTS_DUE_TO_AI | 0 |

## R5 — S quant

| Metric | Value |
|--------|-------|
| AI_IDLE_RAM | ~852 MB |
| AI_ACTIVE_RAM_MAX | ~1218 MB |
| AI_RAM_HEADROOM_PCT | 80% |
| AI_SWAP_DELTA | 0 MB |

## Gate

`AI_08B_RESOURCE_GATE=PASS` (comfortable headroom; no swap thrash).

`AI_2B_TESTED=NO` — quality of 0.8B M/S failed schema fidelity; product path is structured fallback; 2B not authorized as automatic next step when architecture already safe without permanent local LLM.
