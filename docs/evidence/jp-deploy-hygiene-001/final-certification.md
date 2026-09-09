# Final certification — DEPLOY-HYGIENE-001

**Date:** 2026-09-09  
**Status:** DEPLOY-HYGIENE-001=PASS

## Baseline

| Field | Value |
|---|---|
| BASELINE_REMOTE_HEAD | `d17535ad5586fbd3d1d2d53d42f12b200c839d30` |
| PRODUCTION_SHA_BEFORE | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| PRODUCTION_SHA_AFTER | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| LIVE_HTTP_BEFORE | 200 |
| LIVE_HTTP_AFTER | 200 |

## Root cause

| Field | Value |
|---|---|
| ROOT_CAUSE_IDENTIFIED | YES |
| ROOT_CAUSE_SCRIPT | `jetpk-deploy.sh`, `jetpk-pre-proxy-gate.sh`, `jetpk-stage-release.sh` |
| ROOT_CAUSE_COMMAND | Root-run rsync/cp/artisan and remote tar extract without pkjetp ownership |
| PROTECTED_TOOLING_FIXED | YES |

## Ownership

| Field | Value |
|---|---|
| ROOT_OWNED_RUNTIME_FILES_BEFORE | 0 (post emergency chown; historical defect documented) |
| ROOT_OWNED_RUNTIME_FILES_AFTER | 0 |
| NON_PKJETP_RUNTIME_FILES_AFTER | 0 |
| RUNTIME_WRITABLE_AS_PKJETP | PASS |

## Verification

| Field | Value |
|---|---|
| PKJETP_RUNTIME_WRITE | PASS |
| CACHE_PUT_AS_PKJETP | PASS |
| CACHE_READ_AS_PKJETP | PASS |
| CACHE_FORGET_AS_PKJETP | PASS |
| RUNTIME_OWNERSHIP_GATE | PASS |
| OWNERSHIP_GATE_POSITIVE_TEST | PASS |
| OWNERSHIP_GATE_NEGATIVE_TEST | PASS |
| IDEMPOTENCY_TEST | PASS |
| NO_PRODUCTION_SHA_CHANGE | PASS |

## Tooling

| Field | Value |
|---|---|
| TRACKED_HELPER | `scripts/jetpk/assert-runtime-ownership.sh` |
| LOCAL_WRAPPERS_UPDATED | deploy, pre-proxy-gate, stage-release |
| SERVER_WRAPPERS_UPDATED | `/tmp/jetpk-deploy.sh`, `/tmp/jetpk-pre-proxy-gate.sh`, `/tmp/jetpk-stage-release.sh` |

No application release/deploy performed during this hygiene closure.
