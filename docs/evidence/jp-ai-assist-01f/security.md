# Security

Hostile inputs (script/SQL/shell/prompt-injection/secrets) do not become searchable intents or tool escapes.  
Orchestrator strip_tags + injection gate; hybrid `looksHostile` clarify path.

| Check | Result |
|-------|--------|
| LANGUAGE_PIPELINE_TOOL_ESCAPE | 0 |
| SECRET_DISCLOSURE | 0 |
| SQL_EXECUTION | 0 |
| SHELL_EXECUTION | 0 |
| OTHER_CUSTOMER_DATA_DISCLOSURE | 0 |
| CHAT_RATE_LIMIT | PASS (existing RateLimiter preserved) |
