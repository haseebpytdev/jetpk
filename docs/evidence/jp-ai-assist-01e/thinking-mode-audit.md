# Thinking mode audit

| Field | Value |
|-------|-------|
| THINKING_MODE_ACTIVE (default without kwargs) | can be YES |
| DISABLE_THINKING_SUPPORTED | YES via `chat_template_kwargs.enable_thinking=false` |
| DISABLE_THINKING_CONFIGURATION | LocalLlamaProvider + benchmarks set `enable_thinking: false` |

## Compare (Lahore→Dubai extract)

**Thinking OFF:** content JSON only; reasoning empty; 57 completion tokens.

**Thinking ON:** long `reasoning_content`; content truncated/incomplete; 200 completion tokens.

```
NON_THINKING_MODE_TESTED=YES
THINKING_TOKENS_GENERATED=0_when_disabled
THINKING_CONTENT_INCLUDED_IN_JSON_PARSE=NO_when_disabled
```

Extraction must keep thinking disabled.
