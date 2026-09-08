# Deployment report — Closure-05

**Status:** DEPLOYED

| Field | Value |
|---|---|
| BASELINE_PRODUCTION_SHA | `20e921661da55e121a9b2353cba535b350613493` |
| NEW_ENGINEERING_SHA | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| REMOTE_EVIDENCE_HEAD | `cf68824bdecd1d7ffdbca647c9334d326259fe1e` (docs-only; **not deployed**) |
| PREDEPLOY_VERIFIER | PASS |
| DEPLOYED | YES |

## Protected tooling verification

| Script | Local SHA256 | Server `/tmp` SHA256 | Match |
|---|---|---|---|
| `jetpk-backup.sh` | `f0383918…` | `f0383918…` | YES |
| `jetpk-deploy.sh` | `5477c989…` | `5477c989…` | YES |
| `jetpk-next-build.sh` | `e9baf4ca…` | `e9baf4ca…` | YES |
| `jetpk-pre-proxy-gate.sh` | `717f17e2…` | `717f17e2…` | YES |
| `jetpk-stage-release.sh` | `f7d4ea30…` (AUTHORIZED_SHA generation) | `c94ebf1b…` at `/home/pkjetp/jetpk-stage-release.sh` (legacy) | NO |

**Staging action:** Local authoritative `tmp/jetpk-stage-release.sh` executed with `AUTHORIZED_SHA` + `BASE_SHA=e37e62bf`; archive extracted on server. Server `/home/pkjetp/jetpk-stage-release.sh` was **not** installed to `/tmp` (legacy generation).

## Deploy sequence

| Step | Result | Evidence |
|---|---|---|
| Preflight HTTP 200 | PASS | `curl https://jetpakistan.pk` → 200 |
| `jetpk-backup.sh` | PASS | `BACKUP_TS=20260908T181924Z` |
| `jetpk-stage-release.sh` | PASS | `RELEASE_STAGED_AT=/home/pkjetp/releases/jetpk-20260908T182533Z`, `RELEASE_TIMESTAMP=20260908T182533Z`, `STAGED_SOURCE_SHA=f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| `jetpk-deploy.sh` | PASS | `FILE_ACTIVATION=PASS` |
| `jetpk-next-build.sh` (full, not `PUBLIC_ONLY`) | PASS | Public `BUILD_ID=m_8GEC6BnMkGnfo7ET_Z5`; dashboard rebuilt + PM2 restart |
| `jetpk-pre-proxy-gate.sh` | PASS | `PRE_PROXY_GATE_PASS`, `LIVE_HTTPS=200` |

## Production runtime SHA

```
/home/pkjetp/jetpk_app/.jetpk-authorized-sha: f039bef3dda1320c08fdccb4633d5c7c34b3b61e
/home/pkjetp/jetpk_app/.jetpk-runtime-sha:   f039bef3dda1320c08fdccb4633d5c7c34b3b61e
```

## Gate matrix

| Gate | Status |
|---|---|
| BACKUP | PASS |
| STAGED_SOURCE_SHA | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| FILE_ACTIVATION | PASS |
| PUBLIC_BUILD | PASS |
| DASHBOARD_BUILD | PASS |
| PUBLIC_BUILD_ID | VALID |
| DASHBOARD_BUILD_ID | VALID |
| PRE_PROXY_GATE | PASS |
| LIVE_HTTP | 200 |
| PRODUCTION_SHA | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |

## Postdeploy certification (in progress)

| Check | Status | Evidence |
|---|---|---|
| `jetpk:homepage-fare-provenance-audit --profile=jetpk --json` | PASS | `production-fare-provenance-audit.json` |
| Production browser/API cert | PASS | `production-browser-cert.json` |
| CMS publish-path (production) | Covered by predeploy draft→preview UAT + live API/DOM parity on production | `cms-browser-uat.json`, `production-browser-cert.json` |
| FINAL_POSTDEPLOY_VERIFIER | PASS | `postdeploy-verifier.md` |

**STATUS=PASS**
