# CQ45 production deploy

## Pre-merge verify

| Field | Value |
| --- | --- |
| PR50_STATE | OPEN → merged |
| PR50_HEAD | `10f89af13a2fc1335e69c31f422530b3d5764ead` |
| PR50_BASE | `63813d3caeb4c473c0e92adda6cddc2d46655ffa` |
| PR50_MERGEABLE | true |
| RELEASE_GUARDS | PASS |
| UNRESOLVED_REVIEW_THREADS | 0 |

## Merge

| Field | Value |
| --- | --- |
| PR | #50 |
| Strategy | GitHub squash merge |
| PR50_MERGED | YES |
| MERGE_SHA / MAIN_SHA_AFTER_MERGE | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| Application commit (in squash) | `c634424d` (`ServerTravelSignals` stop_refinement) |
| Hardening commit (in squash) | `10f89af1` (tests/evidence only) |

## Deploy

| Field | Value |
| --- | --- |
| PREVIOUS_RUNTIME_SHA | `9901f285e06c85f550191307ef29c9298904a32c` |
| DEPLOYED_RUNTIME_SHA | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| DEPLOY_MARKER | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| FRONTEND_SHA | `24dbf524c06fc894f84a44b14a224a41b49170f7` (unchanged) |
| APPLICATION_CODE_PARITY | PASS (AI runtime) |
| Path | `CURRENT_DEPLOYED_SHA=9901f285… AUTHORIZED_SHA=c115cc57… bash scripts/jetpk/deploy-ai-runtime-protected.sh` |
| Files deployed | `app/Services/Ai/Hybrid/ServerTravelSignals.php` (1) |
| Backup | `/home/pkjetp/backups/jetpk-ai-runtime-20260928T215042Z` |
| Release | `/home/pkjetp/releases/jetpk-ai-runtime-c115cc5712ad0fca0ab9729d6d34907220959a93-20260928T215042Z` |
| Rollback target | `9901f285e06c85f550191307ef29c9298904a32c` |
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
