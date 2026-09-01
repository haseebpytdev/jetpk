# Next model selection

## Candidates considered

1. **Qwen3-4B-GGUF** (official, Apache-2.0, Q4_K_M published)
2. Qwen2.5-3B-Instruct GGUF (older family)
3. Llama-3.2-3B-Instruct (weaker Urdu expectation)
4. Gemma-3-4B (license check heavier)

## Selected

```
NEXT_MODEL_SELECTED=Qwen/Qwen3-4B-GGUF Qwen3-4B-Q4_K_M.gguf
NEXT_MODEL_SELECTION_REASON=Official Qwen lineage + Apache-2.0 + published Q4_K_M (~2.5GB) + same chat-template family as certified 1.7B harness + multilingual focus
NEXT_MODEL_LICENSE=Apache-2.0
NEXT_MODEL_LICENSE_COMMERCIAL_USE_ALLOWED=YES
NEXT_MODEL_LINEAGE_VERIFIED=YES
NEXT_MODEL_QUANTIZATION=Q4_K_M
NEXT_MODEL_DISK_SIZE=2497280256
NEXT_MODEL_SHA256=7485fe6f11af29433bc51cab58009521f205840f5b4ae3a32fa7f92e8534fdf5
```
