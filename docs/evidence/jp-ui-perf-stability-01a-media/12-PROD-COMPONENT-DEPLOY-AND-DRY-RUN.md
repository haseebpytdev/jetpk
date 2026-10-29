# JP-UI-PERF-STABILITY-01A — Component production deploy + dry-run

```text
LARAVEL_CANDIDATE_SHA=321be5673036912868f2846a1f51322854bd157e
FRONTEND_CANDIDATE_SHA=43f5a3beb9a8e88567cf49bef76853f49b55e0c6
PREDEPLOY_APPLICATION_SHA=922f15774ece177d8b3cc23207e98379b8a2755b
PREDEPLOY_FRONTEND_SHA=24dbf524c06fc894f84a44b14a224a41b49170f7
BACKUP_TS=20261029T134124Z
BACKUP_PATH=/home/pkjetp/backups/jetpk_app-20261029T134124Z.tar.gz
  (+ jetpk-db-20261029T134124Z.sql.gz, public_html-20261029T134124Z.tar.gz)
```

## Laravel activation

Staging dry-run `RELEASE_SCOPE=laravel` BASE=`922f1577` AUTH=`321be567`:

```text
MANIFEST_UPLOADABLE_COUNT=7
MANIFEST_DELETION_COUNT=0
UNEXPECTED_LARAVEL_PATHS=0
```

Real stage: `RELEASE_STAGED_AT=/home/pkjetp/jetpk_git/tmp/releases/jetpk-20261029T134526Z`

`jetpk-deploy.sh` failed on `rsync` `chgrp` against root-owned `app/Services`.
Recovery: exact allowlisted `install` of the same 7 staged files +
`write-deploy-sha-marker.sh` → `deploy-sha.txt=321be567…`.

Git blob parity: MATCH ×7. Ownership normalize+assert: PASS.
Artisan boot PASS; `jetpk:homepage-media-optimize` registered.

## Frontend activation

Isolated stage APP: `/home/pkjetp/tmp/pr139-frontend-deploy-20261029T135016Z`  
`deploy-frontend-canonical.sh` with `FRONTEND_SKIP_RESTART=1`  
`BUILD_ID=kqYKY3eqwChjGrmi79IKh`  
Prod `deploy-sha.txt` preserved as `321be567` during stage finalize (staging-only marker).

Activated exactly 10 certified source files from candidate SHA.
Atomic `.next` swap; previous retained as
`.next.bak-pr139-20261029T135510Z`.

```text
.jetpk-frontend-sha=43f5a3beb9a8e88567cf49bef76853f49b55e0c6
BUILD_BEFORE_RESTART_GUARD=PASS
PM2 restart jetpk-public-frontend only
DASHBOARD_RESTARTED=NO
```

## Smoke

```text
HOMEPAGE_HTTP=200
PUBLIC_CONTENT_API=/api/public/content/homepage → 200
NEW_LARAVEL_FATAL_GREP=0
```

## Media dry-run (writes=no)

```text
dry_run=yes processed=14 skipped=1 writes=no
```

See inventory in phase return. Write-mode **not** executed.

## Markers final

```text
APPLICATION_DEPLOY_SHA=321be5673036912868f2846a1f51322854bd157e
PUBLIC_FRONTEND_SOURCE_SHA=43f5a3beb9a8e88567cf49bef76853f49b55e0c6
FULL_MAIN_998_MARKER_PRESENT=NO
```
