# JP-FINAL-CLOSURE-01-R5 — Deployment report

## Final deployed object (R5C)

- AUTHORIZED_SHA / DEPLOYED_RUNTIME_SHA=`0221a3f9ff26621289eb3ad61b43e3af00b3ebb3`
- PUBLIC_BUILD_ID=`zxhTMPV_izXxL129p_rnD`
- BACKUP_ID=`jp-final-closure-01-r2-20260830T114948Z`
- MANIFEST_COUNT=`13`
- RELEASE=`jetpk-jp-final-closure-01-r5-20260830T114927Z`

## Intermediate objects

| SHA | Build | Note |
|---|---|---|
| `3c0def3a…` | `PGOVQaS2ow-r7q2OHoNdo` | R5A — best measured Book Now sample (13/14, usable p50≈3.7s) |
| `cf03d5cc…` | `3JiCRsBEwJwCFd3-b-GvE` | R5B — layout Promise.race timeout **regressed**; superseded |
| `0221a3f9…` | `zxhTMPV_izXxL129p_rnD` | R5C — revert race timeout; keep AbortSignal + travelers GET fixes |

## Gates (deploy3.out)

| Gate | Result |
|---|---|
| BACKUP | PASS |
| PHP_SYNTAX | PASS |
| LARAVEL_BOOT | PASS |
| MIGRATIONS | 0 |
| PUBLIC_BUILD | PASS |
| OLS_HASH | PASS |
| FULL_RUNTIME_SOURCE_DRIFT | 0 |
| FULL_GIT_OBJECT_PARITY | PASS |
| PUBLIC_PM2 | online |
| DASHBOARD_PM2 | online |
| **ACTIVATE** | **PASS** |

## Rollback

Restore from backup `jp-final-closure-01-r2-20260830T114948Z` / prior SHA `cf03d5cc` or `3c0def3a`.
