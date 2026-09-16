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
bash scripts/jetpk/test-seo-activate-ai-runtime-guard.sh
```

## SEO activation (allowlist only)

SEO Phase 2 activation must never bulk-copy `app/` or `bootstrap/`. Use:

```bash
REL=/home/pkjetp/releases/<release-dir> \
SHA=<authorized-sha> \
bash scripts/jetpk/deploy-seo-phase2-activate-allowlist.sh
```

Post-activate gate (fail-closed):

```bash
APP=/home/pkjetp/jetpk_app bash scripts/verify-ai-runtime-after-seo-activate.sh
```

## Protected AI runtime deploy

Protected deploy must run from a working tree checked out at `AUTHORIZED_SHA` first:

```bash
cd /home/pkjetp/jetpk_git && git fetch origin && git checkout -f "${AUTHORIZED_SHA}"
AUTHORIZED_SHA=<full-sha> bash scripts/jetpk/deploy-ai-runtime-protected.sh
```

Includes canonical public frontend build/deploy (`deploy-frontend-canonical.sh`) in order:
backend source → frontend build → Laravel refresh → `jetpk-public-frontend` restart → AI runtime verification.

Standalone frontend deploy:

```bash
AUTHORIZED_SHA=<full-sha> bash scripts/jetpk/deploy-frontend-canonical.sh
```

Use `FRONTEND_SKIP_RESTART=1` when the protected wrapper will restart PM2 after Laravel refresh.
