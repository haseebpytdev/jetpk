# CQ46 production deploy

## Pre-merge verify

| Field | Value |
| --- | --- |
| PR53_STATE | OPEN → merged |
| PR53_HEAD | `b83099c937265a06b5cd4c8d56b55b8605d2f87e` |
| PR53_BASE | `699832fdf5199ad52d2603c89734b0dd7bc7d680` |
| PR53_MERGEABLE | true |
| RELEASE_GUARDS | PASS |
| UNRESOLVED_REVIEW_THREADS | 0 |
| Application files vs base | `AiChatOrchestrator.php`, `ServerTravelSignals.php` |
| TravelConstraintResolver in final PR diff | NO (NET_CHANGE_VS_BASE=0) |

## Merge

| Field | Value |
| --- | --- |
| PR | #53 |
| Strategy | GitHub squash merge (`expectedHeadSha=b83099c…`) |
| PR53_MERGED | YES |
| MERGE_SHA / MAIN_SHA_AFTER_MERGE | `3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492` |

## Deploy

| Field | Value |
| --- | --- |
| PREVIOUS_RUNTIME_SHA | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| DEPLOYED_RUNTIME_SHA | `3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492` |
| DEPLOY_MARKER | `3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492` |
| FRONTEND_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` (unchanged) |
| APPLICATION_CODE_PARITY | PASS (sha256 match for both AI files; see `00b-deploy-parity.txt`) |
| Path | `CURRENT_DEPLOYED_SHA=c115cc57… AUTHORIZED_SHA=3de07cdb… bash scripts/jetpk/deploy-ai-runtime-protected.sh` |
| Files deployed | `app/Services/Ai/AiChatOrchestrator.php`, `app/Services/Ai/Hybrid/ServerTravelSignals.php` (2) |
| Backup | `/home/pkjetp/backups/jetpk-ai-runtime-20260929T125701Z` |
| Release | `/home/pkjetp/releases/jetpk-ai-runtime-3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492-20260929T125701Z` |
| Rollback target | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| PROTECTED_AI_DEPLOY | PASS |

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
CQ44_PERFORMANCE_PROGRAM=CLOSED  
PERFORMANCE_OPERATIONAL_ACCEPTANCE=PASS
