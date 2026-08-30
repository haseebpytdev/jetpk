# JP-FINAL-CLOSURE-01 — Deployment report (R6F)

## Deployed object (R6F soft-nav)

- AUTHORIZED_SHA / DEPLOYED_RUNTIME_SHA=`6a6c3b35227d9aa29e88a2c9d83e81d7812e9cb2`
- PUBLIC_BUILD_ID=`abYe4XmYEs6wOjNqRDNGX`
- PRIOR_RUNTIME (R6 hard-nav)=`a603211f0b5cebf73c1770532cfed649030b7a1f` / build `38WrCuLnbbv8LChWWw4_M`
- BACKUP_ID=`jp-final-closure-01-r2-20260830T190750Z`
- Orchestrator: `/usr/local/sbin/jetpk-production-run` (single outer lock)
- Transcript: `tmp/jp-final-closure-01-r6/deploy-r6f.out`

## Gates

| Gate | Result |
|---|---|
| JETPK_PRODUCTION_LOCK_ACQUIRED | YES |
| ROOT_DISK (~20% / ~77.3G free) | PASS |
| OLS | PASS |
| BACKUP / ROLLBACK_PACKAGE | PASS |
| ACTIVATE | PASS |
| FULL_RUNTIME_SOURCE_DRIFT | 0 |
| AUTHORIZED_RUNTIME_PARITY / FULL_GIT_OBJECT_PARITY | PASS |
| PUBLIC_BUILD | PASS |
| FINAL_VERIFIED_ROLLBACK_COUNT | 2 |
| PRODUCTION_LOCK_RELEASED | YES (orchestrator complete RC=0) |

## Retention

Post-activate temporarily had 3 rollback candidates; retention converged to latest **2** verified checkpoints (`FINAL_VERIFIED_ROLLBACK_COUNT=2`).
