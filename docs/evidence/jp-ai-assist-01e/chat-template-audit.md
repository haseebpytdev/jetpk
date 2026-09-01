# Chat template audit

| Field | Value |
|-------|-------|
| MODEL_CHAT_TEMPLATE | Qwen3 ChatML (`<\|im_start\|>` / `<\|im_end\|>`) with thinking branches |
| LLAMA_CPP_CHAT_TEMPLATE_USED | Auto from GGUF (llama-server `/props`) |
| MODEL_TEMPLATE_AUTO_DETECTED | YES |
| SYSTEM_MESSAGE_PLACEMENT | `<\|im_start\|>system` |
| USER_MESSAGE_PLACEMENT | `<\|im_start\|>user` |
| ASSISTANT_PREFIX | `<\|im_start\|>assistant` (+ optional think block) |
| EOS_HANDLING | `<\|im_end\|>` |
| BOS_HANDLING | model bos from props |

```
CHAT_TEMPLATE_CORRECT=YES
```

Official Qwen3 template includes `enable_thinking` — not a generic wrong ChatML.
