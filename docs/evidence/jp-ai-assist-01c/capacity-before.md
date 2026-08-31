# Capacity before model install (fresh audit)

Host: Contabo VMI, AMD EPYC, 6 vCPU, AVX2, no GPU.

| Metric | Idle baseline |
|--------|----------------|
| RAM total | 11960 MB |
| RAM available | ~10278–10447 MB |
| Swap total | 2047 MB |
| Swap used | ~89 MB |
| Load average | ~0.01–0.42 |
| Root disk | ~20% used / ~78 GB free |

## Labels

```
PRE_AI_RAM_AVAILABLE≈10447MB
PRE_AI_SWAP_USED≈89MB
PRE_AI_LOAD=0.01 0.11 0.12
```

Public/Dashboard PM2 remained online during audits. No OOM observed pre-AI.

Prior R4 `AI_RUNTIME=BLOCKED_CAPACITY` is **superseded** by empirical 0.8B load (see resource-results.md).
