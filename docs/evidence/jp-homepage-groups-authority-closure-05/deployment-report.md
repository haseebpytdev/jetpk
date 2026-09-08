# Deployment report — Closure-05

**Status:** DEPLOYED + POSTDEPLOY CERTIFIED

| Field | Value |
|---|---|
| BASELINE_PRODUCTION_SHA | `20e921661da55e121a9b2353cba535b350613493` |
| PRODUCTION_ENGINEERING_SHA | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| REMOTE_EVIDENCE_POLICY_HEAD | `bba0967145cf44a9aecd11453fe267b00c439736` (docs/evidence only; **not deployed**) |
| PREDEPLOY_VERIFIER | PASS |
| DEPLOYED | YES (prior session; reconfirmed 2026-09-09) |

## Protected tooling verification

| Script | Local SHA256 (authoritative) | Server `/tmp` | Match |
|---|---|---|---|
| `jetpk-backup.sh` | `f0383918…` | `f0383918…` | YES |
| `jetpk-deploy.sh` | `5477c989…` | `5477c989…` | YES |
| `jetpk-next-build.sh` | `e9baf4ca…` | `e9baf4ca…` | YES |
| `jetpk-pre-proxy-gate.sh` | `717f17e2…` | `717f17e2…` | YES |
| `jetpk-stage-release.sh` | `f7d4ea30…` (`tmp/jetpk-stage-release.sh`) | `c94ebf1b…` (matches `/home/pkjetp/jetpk-stage-release.sh`) | NO (generation mismatch; deploy used AUTHORIZED_SHA staging successfully) |

## Deploy sequence (recorded)

| Step | Result | Evidence |
|---|---|---|
| Preflight HTTP 200 | PASS | `curl https://jetpakistan.pk` → 200 |
| `jetpk-backup.sh` | PASS | `BACKUP_TS=20260908T181924Z` |
| `jetpk-stage-release.sh` | PASS | `STAGED_SOURCE_SHA=f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| `jetpk-deploy.sh` | PASS | `FILE_ACTIVATION=PASS` |
| `jetpk-next-build.sh` (full) | PASS | Public + dashboard rebuild |
| `jetpk-pre-proxy-gate.sh` | PASS | `PRE_PROXY_GATE_PASS`, `LIVE_HTTPS=200` |

## Production runtime SHA (reconfirmed)

```
/home/pkjetp/jetpk_app/.jetpk-authorized-sha: f039bef3dda1320c08fdccb4633d5c7c34b3b61e
/home/pkjetp/jetpk_app/.jetpk-runtime-sha:   f039bef3dda1320c08fdccb4633d5c7c34b3b61e
```

## Post-deploy operational remediation

Root-owned cache files under `storage/framework/cache/data` caused flight-search cache write failures after deploy. Remediated with `chown -R pkjetp:pkjetp` on cache/views/bootstrap/cache. See `logs/postdeploy-cache-permission-fix.txt`.

## Gate matrix

| Gate | Status |
|---|---|
| BACKUP | PASS |
| STAGED_SOURCE_SHA | `f039bef3…` |
| FILE_ACTIVATION | PASS |
| PUBLIC_BUILD | PASS |
| DASHBOARD_BUILD | PASS |
| PRE_PROXY_GATE | PASS |
| LIVE_HTTP | 200 |
| PRODUCTION_SHA | `f039bef3…` |
| Fare provenance audit | PASS (post refresh) |
| Production browser cert | PASS |
| Production CMS UAT | PASS (draft/preview/restore) |
| Closure-04 regression rerun | PASS (`../jp-final-cms-branding-closure-04/closure-04-prod-gates.json`) |
| FINAL_POSTDEPLOY_VERIFIER | PASS (`postdeploy-verifier.md`) |

**STATUS=PASS**
