# AI capacity + security (sanitized)

```
AI_CAPACITY_CPU=AMD EPYC Processor (with IBPB)
AI_CAPACITY_VCPU=6
AI_CAPACITY_RAM_TOTAL≈11960 MB
AI_CAPACITY_RAM_AVAILABLE≈10434 MB (idle audit)
AI_CAPACITY_SWAP=2048 MB (~90 MB used at audit)
AI_GPU=NONE
AI_BASELINE_LOAD≈0.08 0.05 0.08
AI_BASELINE_APP_MEMORY=PM2 public+dashboard ~230 MB each (approx)
```

## Runtime decision

Same-VPS headroom exists for a sub-1B quantized CPU model in isolation, but this run does **not** install GGUF/llama.cpp until a dedicated soak under MemoryMax/CPUQuota is owner-approved.

```
AI_RUNTIME=BLOCKED_CAPACITY
AI_DIRECTORY=ai-assistant/ (architecture present)
AI_GATEWAY=fail-closed Laravel PublicAiAssistantController
AI_LISTEN_PUBLICLY=NO
AI_SUPPLIER_MUTATION_CALLS=0
AI_PROMPT_INJECTION_TOOL_ESCAPE=NO (no privileged tools exist)
AI_SECRET_DISCLOSURE=NO
```

Tool manifest: `ai-assistant/tools/tool-manifest.json` (read-only shopping tools only).
WhatsApp adapter contract: `ai-assistant/adapters/whatsapp/README.md`.
