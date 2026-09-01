# Performance public

From `suite.json` (model-free hybrid):

| Metric | Value |
|--------|------:|
| TOTAL_LANGUAGE_PIPELINE_P50_MS | 0.076 |
| TOTAL_LANGUAGE_PIPELINE_P95_MS | 0.509 |
| CHAT_MESSAGE_ACK_P50_MS | 27.98 |
| CHAT_MESSAGE_ACK_P95_MS | 40.152 |
| CHAT_KNOWLEDGE_RESPONSE_P50_MS | 33.32 |
| CHAT_KNOWLEDGE_RESPONSE_P95_MS | 45.89 |

CHAT_PUBLIC_LOAD_REGRESSION=**NO** (modest concurrency, no mass supplier fan-out)
