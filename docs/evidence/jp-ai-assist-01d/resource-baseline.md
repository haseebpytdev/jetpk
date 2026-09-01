# Resource baseline (before / during / after)

## Host

- AMD EPYC, 6 vCPU, 11 GiB RAM, 2 GiB swap
- Disk ~23% used / ~75G free before download; model +0.8B prior cache

## Before load

| Metric | Value |
|--------|-------|
| Available RAM | ~10470 MB |
| Swap used | 89 MB |
| Load | ~0.11 |
| OOM history | none observed |

## Model load

| Metric | Value |
|--------|-------|
| MODEL_LOAD_SECONDS | 3 |
| MODEL_IDLE_RSS | 2039 MB |
| Bind | `127.0.0.1:3922` only |
| AI_LISTENS_PUBLICLY | NO |
| llama.cpp | 0.3.0-dev build 10726 (`85c55223c`) |

## Active (quality corpus)

| Metric | Value |
|--------|-------|
| MODEL_ACTIVE_RSS_P50 | 2491 MB |
| MODEL_ACTIVE_RSS_MAX | 3174 MB |
| AI_CPU_P50 | 198% (2 threads saturated) |
| AI_CPU_MAX | 199% |
| SYSTEM_RAM_AVAILABLE_MIN | ~9291 MB |
| SWAP_DELTA | 0 |
| OOM_EVENTS | 0 |
| PM2_RESTARTS_DUE_TO_AI | 0 |
| SUSTAINED_SWAP_THRASH | NO |

## Gate

```
AI_17B_RESOURCE_GATE=PASS
```

Headroom remained multi-GB; no swap growth; no OOM; listener localhost-only.
