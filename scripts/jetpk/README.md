# JetPakistan protected release tooling (tracked, non-secret)

These scripts are the durable source of truth for SHA-parameterized staging.
Local-only wrappers under `tmp/jetpk-*.sh` may hold host/connection glue, but
must call these tracked helpers for manifest generation and safe deletion.

## Stage from an authorized Git SHA

```bash
AUTHORIZED_SHA=<sha> \
BASE_SHA=<frozen-base-sha> \
AUTHORIZED_BRANCH=<branch> \
bash scripts/jetpk/stage-release-from-sha.sh
```

Invariants:

- `STAGED_SOURCE_SHA` always equals the supplied `AUTHORIZED_SHA`
- contents are taken from Git at that SHA (never the dirty working tree)
- runtime scope is filtered (no tests/docs/tmp/secrets/dashboard)
- deletions are emitted as an exact allowlisted file list

## Apply exact deletions

```bash
APP_ROOT=/home/pkjetp/jetpk_app \
DELETE_MANIFEST=/path/to/DELETE_RUNTIME_FILES \
bash scripts/jetpk/apply-delete-manifest.sh
```

No wildcards. No directory-wide recursive deletion. Frontend runtime paths only.
