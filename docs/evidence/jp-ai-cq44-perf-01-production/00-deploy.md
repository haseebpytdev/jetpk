# CQ44-PERF-01 production deploy

## Merge

| Field | Value |
| --- | --- |
| PR | #45 |
| Reviewed head | `a56122143438117c3c3aa5ec00b5be1e75d14811` |
| Base | `a599dab2392dca561f240c4273fd5e9d85044160` |
| Merge strategy | GitHub squash merge |
| MERGE_SHA / MAIN_SHA | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |
| PR45_MERGED | YES |

## Deploy

| Field | Value |
| --- | --- |
| PREVIOUS_RUNTIME_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` |
| DEPLOYED_RUNTIME_SHA | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |
| DEPLOY_MARKER | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |
| FRONTEND_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` (unchanged; no frontend delta) |
| APPLICATION_CODE_PARITY | PASS (AI runtime) |
| Path | `CURRENT_DEPLOYED_SHA=24dbf524… AUTHORIZED_SHA=a0e616… bash scripts/jetpk/deploy-ai-runtime-protected.sh` |
| Files deployed | `SemanticBrain.php`, `ServerTravelSignals.php`, `AiChatOrchestrator.php` |
| Backup | `/home/pkjetp/backups/jetpk-ai-runtime-20260928T140615Z` |
| Rollback target | `24dbf524c06fc894f84a44b14a224a41b49170f7` |

## Config (unchanged)

```
CONVERSATIONAL_ENABLED=true
SEMANTIC_PLANNER_ENABLED=true
SEMANTIC_COMPOSER_ENABLED=false
AI_EMBED_ENABLED=false
brain_enabled=true
```

No model / quantization / timeout / composer / iframe / rate-limit changes.
PERMANENT_QWEN_OWNER_DECISION=APPROVED
IFRAME_PILOT=HOLD
