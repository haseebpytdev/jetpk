# Model selection (AI-01D-R2)

## Primary candidate class

Qwen3-1.7B

## Official upstream (independently verified)

| Field | Value |
|-------|-------|
| MODEL_OFFICIAL_UPSTREAM | https://huggingface.co/Qwen/Qwen3-1.7B |
| MODEL_MODEL_CARD | https://huggingface.co/Qwen/Qwen3-1.7B |
| MODEL_PARAMETER_COUNT | 1.7B (non-embedding ~1.4B) |
| MODEL_ARCHITECTURE | Qwen3 (GQA, 28 layers) |
| MODEL_LICENSE | Apache-2.0 |
| MODEL_LICENSE_COMMERCIAL_USE_ALLOWED | YES |

## GGUF lineage

| Field | Value |
|-------|-------|
| MODEL_GGUF_SOURCE | https://huggingface.co/Qwen/Qwen3-1.7B-GGUF |
| MODEL_GGUF_REVISION | `90862c4b9d2787eaed51d12237eafdfe7c5f6077` (HF model sha at fetch time) |
| Artifact | `Qwen3-1.7B-Q8_0.gguf` (official siblings list) |

## Quantization decision

Preferred prompt quant **Q4_K_M** is listed in the GGUF README feature list but **not published** in the official repo `siblings` at verification time (only `Qwen3-1.7B-Q8_0.gguf`).

Community anonymous Q4_K_M uploads were **rejected** for supply-chain uncertainty.

**Selected:** official **Q8_0** (technically stronger than Q4_K_M; larger RSS; still within VPS headroom).

```
MODEL_QUANTIZATION=Q8_0
MODEL_LINEAGE_VERIFIED=YES
```
