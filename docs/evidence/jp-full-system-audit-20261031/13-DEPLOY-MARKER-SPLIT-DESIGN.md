# Deploy marker split — design + implementation

## Problem

`deploy-frontend-canonical.sh` called `finalize-deploy-sha-marker.sh` → `write-deploy-sha-marker.sh`, and the prior flow overwrote `storage/app/deploy-sha.txt` with the **frontend** SHA. That made the legacy marker ambiguous for Laravel/application provenance.

## Design

| Marker | Owner |
|---|---|
| `storage/app/deploy-application-sha.txt` | Laravel/application deploys |
| `storage/app/deploy-frontend-sha.txt` | Public Next frontend deploys |
| `storage/app/deploy-dashboard-sha.txt` | Dashboard deploys |
| `storage/app/deploy-sha.txt` (legacy) | **application only** compatibility marker |

Also retain `frontend/.jetpk-frontend-sha` and optional runtime-specific markers where already used.

## Implementation

- `write-deploy-sha-marker.sh` accepts `DEPLOY_COMPONENT=application|frontend|dashboard`.
- Frontend finalize uses `DEPLOY_COMPONENT=frontend` and does **not** overwrite legacy/application provenance.
- `test-deploy-sha-marker.sh` includes a guard proving a frontend write cannot overwrite the application marker.
- Canonical source merged in **PR #155** as `c56178a84f4bc3f2124b9d81ed9c890b5382d0f9`.

## Current production state

The source fix is merged. Existing production marker files are not rewritten merely for cosmetic consistency.

The new component-aware marker behavior must be verified during the **next applicable frontend/application/dashboard deploy** using the updated canonical scripts.

```
DEPLOY_MARKER_SPLIT=DESIGNED_AND_IMPLEMENTED
DEPLOY_MARKER_SOURCE_MERGED=YES
DEPLOY_MARKER_PRODUCTION_EFFECT=PENDING_NEXT_APPLICABLE_DEPLOY
```
