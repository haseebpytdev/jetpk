# CQ44-PERF-02 production deploy

## Merge

| Field | Value |
| --- | --- |
| PR | #48 |
| Reviewed head | `57e7ec1ab6269c6e46f7f6bacfc10a78ca3e8bd9` |
| Base | `d13411923ea75ba3a359d89946a56d45c550fbb2` |
| Merge strategy | GitHub squash merge |
| MERGE_SHA / MAIN_SHA | `9901f285e06c85f550191307ef29c9298904a32c` |
| PR48_MERGED | YES |

## Deploy

| Field | Value |
| --- | --- |
| PREVIOUS_RUNTIME_SHA | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |
| DEPLOYED_RUNTIME_SHA | `9901f285e06c85f550191307ef29c9298904a32c` |
| DEPLOY_MARKER | `9901f285e06c85f550191307ef29c9298904a32c` |
| FRONTEND_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` (unchanged; no frontend delta) |
| APPLICATION_CODE_PARITY | PASS (AI runtime) |
| Path | `CURRENT_DEPLOYED_SHA=a0e6167… AUTHORIZED_SHA=9901f285… bash scripts/jetpk/deploy-ai-runtime-protected.sh` |
| Files deployed | `SemanticBrain.php`, `ServerTravelSignals.php`, `LocationResolver.php` |
| Backup | `/home/pkjetp/backups/jetpk-ai-runtime-20260928T194153Z` |
| Release | `/home/pkjetp/releases/jetpk-ai-runtime-9901f285e06c85f550191307ef29c9298904a32c-20260928T194153Z` |
| Rollback target | `a0e616747a95d632f270a6edafc3f6e8d9230fca` |

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
