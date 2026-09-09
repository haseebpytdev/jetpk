# Deployment tooling diff — DEPLOY-HYGIENE-001

## Tracked (Git)

| File | Change |
|---|---|
| `scripts/jetpk/assert-runtime-ownership.sh` | **NEW** — scoped normalize/assert gate + optional pkjetp write probe |
| `scripts/jetpk/test-runtime-ownership-gate.sh` | **NEW** — positive/negative/idempotency fixture tests |
| `scripts/jetpk/run-deploy-hygiene-prod-tests.sh` | **NEW** — safe production verification runner (no deploy) |
| `scripts/jetpk/README.md` | Document ownership helper |
| `docs/jetpk/DEPLOYMENT-CONTEXT.md` | Mandatory ownership gate in protected workflow |

## Gitignored operational wrappers (`tmp/` → server `/tmp/`)

| File | Change |
|---|---|
| `tmp/jetpk-deploy.sh` | `5477c989…` → `b6693c961…` — artisan/composer as pkjetp; end-of-deploy normalize+assert |
| `tmp/jetpk-pre-proxy-gate.sh` | `717f17e2…` → `597e20f1…` — artisan as pkjetp; hard ownership assert gate |
| `tmp/jetpk-stage-release.sh` | `f7d4ea30…` → `766683ed…` — chown release dir after remote extract |
| `tmp/jetpk-backup.sh` | unchanged |
| `tmp/jetpk-next-build.sh` | unchanged |

## Server-only actions (no app redeploy)

- Created `/home/pkjetp/jetpk_app/scripts/jetpk/`
- Uploaded tracked helpers to production app tree
- Updated `/tmp/jetpk-deploy.sh`, `/tmp/jetpk-pre-proxy-gate.sh`, `/tmp/jetpk-stage-release.sh`

Production engineering SHA unchanged: `f039bef3dda1320c08fdccb4633d5c7c34b3b61e`
