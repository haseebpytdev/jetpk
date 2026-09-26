# CQ28-36 Runtime Inventory (read-only)

Host: vmi3400777 / 185.215.166.176
When: 2026-09-25 UTC

## Resources
AVAILABLE_RAM_MB≈10474 (total 11960)
AVAILABLE_DISK_GB≈33 (of 96)
CPU=AMD EPYC 6 cores
SWAP=2GB (≈200MB used)

## Listeners
127.0.0.1:11434 — Ollama API (llama3.1:8b-instruct-q4_0 present)
127.0.0.1:8765 — AI Lab gateway python (inactive systemd unit)
127.0.0.1:3921 — was DOWN; brought up for cert only

## Prior Qwen artifact (chosen)
PRIOR_QWEN_RUNTIME_FOUND=YES
PRIOR_QWEN_PATH=/home/pkjetp/jetpk_app/ai-assistant/models/Qwen3.5-0.8B-M-TS-Q4_K_M.gguf
QWEN_MODEL_PRESENT=YES
QWEN_MODEL_ID=local (llama-server --alias local)
QWEN_MODEL_SIZE=417MB GGUF / ~752M params
QWEN_MODEL_QUANTIZATION=Q4_K_M
llama-server binary: /home/pkjetp/jetpk_app/ai-assistant/runtime/bin/llama-server

## Decision
MODEL_FAMILY=Qwen
MODEL_NAME=Qwen3.5-0.8B-M-TS
MODEL_VERSION=3.5-0.8B
MODEL_QUANTIZATION=Q4_K_M
MODEL_SOURCE=existing JetPakistan ai-assistant model (CQ26 prior UAT)
RUNTIME_ENGINE=llama-server (llama.cpp)
ENDPOINT=http://127.0.0.1:3921
WHY=Existing certified GGUF + OpenAI-compatible LocalLlamaProvider contract (incl. chat_template_kwargs). Do not use Ollama llama3.1.

## Public flags (unchanged)
OTA_AI_CONVERSATIONAL_ENABLED=false
AI_EMBED_ENABLED=false
PUBLIC_SEMANTIC=false
