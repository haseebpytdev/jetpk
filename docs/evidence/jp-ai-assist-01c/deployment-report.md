# Deployment report

| Item | Status |
|------|--------|
| Permanent llama-server | NOT DEPLOYED (quality gate FAIL) |
| Model files on disk (runtime only) | Present under ai-assistant/models (gitignored) |
| Application AI chat / structured / staff queue | Engineering commits local; production app deploy gated by owner review |
| OTA_AI_ASSISTANT_ENABLED | default false until explicit enable |
| OLS / PM2 | unchanged by AI teardown |
| Rollback | no AI systemd unit to roll back; remove model files if desired |

SAFE_TO_PUSH=NO — do not push.
