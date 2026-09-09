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

## Runtime ownership assert / normalize

Protected deploy wrappers must finish with Laravel writable runtime trees owned by
the application account (`pkjetp:pkjetp` on production). Root-run deploy extract,
`rsync`/`cp`, and `artisan` commands can otherwise leave root-owned cache files
that break flight search and other cache writes.

```bash
APP_ROOT=/home/pkjetp/jetpk_app \
RUNTIME_USER=pkjetp \
RUNTIME_GROUP=pkjetp \
MODE=assert \
bash scripts/jetpk/assert-runtime-ownership.sh
```

Modes:

- `MODE=assert` — fail when any scanned runtime path is not `pkjetp:pkjetp`
- `MODE=normalize` — scoped `chown -R pkjetp:pkjetp` on writable runtime trees, then assert

Optional write probe (safe temp file under cache data):

```bash
RUNTIME_WRITE_TEST=1 APP_ROOT=/home/pkjetp/jetpk_app bash scripts/jetpk/assert-runtime-ownership.sh
```

Gate outputs include:

```text
RUNTIME_OWNER_EXPECTED=pkjetp:pkjetp
ROOT_OWNED_RUNTIME_FILES=<n>
NON_PKJETP_RUNTIME_FILES=<n>
RUNTIME_WRITABLE_AS_PKJETP=PASS|FAIL
RUNTIME_OWNERSHIP_GATE=PASS|FAIL
```

Local fixture tests (no production mutation):

```bash
bash scripts/jetpk/test-runtime-ownership-gate.sh
```
