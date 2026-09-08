# Production Deployment Safety — JetPakistan

Stable project standard for JetPakistan live operations. Read this together with
`docs/jetpk/DEPLOYMENT-CONTEXT.md` before production deployment or server work.

## Canonical production target

```text
PUBLIC_HOST=https://jetpakistan.pk
PRODUCTION_IP=185.215.166.176
APP=/home/pkjetp/jetpk_app
PUBLIC_FRONTEND=/home/pkjetp/jetpk_app/frontend
PUBLIC_PM2=jetpk-public-frontend
DASHBOARD_PM2=jetpk-dashboard
PHP=/usr/local/lsws/lsphp83/bin/lsphp
```

Historical Hostinger/shared-preview/SFTP-only instructions are not authoritative
for current JetPakistan production.

## Standing Cursor production access

The owner has granted Cursor standing operational access to the canonical
JetPakistan server for normal project work.

Cursor may directly use SSH for:

- server inspection and management
- protected deployments
- deployment-wrapper recovery/verification
- service/process/build/cache/runtime checks
- Laravel/Artisan diagnostics
- log inspection
- production API/browser UAT
- read-only live fare/inventory/search verification
- incident investigation and scoped reversible fixes

Cursor may also use SFTP/SCP when legitimately needed for operational recovery
or server management, but normal application releases should use the established
protected Git-SHA deployment path.

The owner should not need to manually run routine SSH commands when Cursor has
working access.

## Mandatory safety boundaries

Direct SSH access does not remove production safeguards.

Do not solely for testing/evidence:

- create bookings, PNRs, supplier holds, tickets, cancellations, voids or refunds
- process real payments
- mutate supplier inventory or settlement data
- perform destructive database reset/drop/truncate operations
- bulk-edit production customer/business records
- expose secrets, credentials, private keys, tokens or PII
- force push or rewrite Git history
- delete the application/release tree wholesale
- reboot/upgrade the server or perform broad infrastructure changes without task justification

Dedicated QA/admin credentials and safe reversible CMS QA draft/publish/restore
actions are permitted when required by an acceptance test.

Read-only live supplier/search calls are permitted when required to verify fares,
availability or inventory. Do not cross into commercial mutations.

## Mandatory deployment workflow

For a normal application release, prefer the established protected scripts:

```bash
bash jetpk-backup.sh
AUTHORIZED_SHA=<approved-engineering-sha> bash jetpk-stage-release.sh
bash jetpk-deploy.sh <RELEASE_DIR> <TIMESTAMP>
bash jetpk-next-build.sh
bash jetpk-pre-proxy-gate.sh
```

A public-only build may use:

```bash
PUBLIC_ONLY=1 bash jetpk-next-build.sh
```

only when Dashboard/CMS source did not change.

Required invariant:

```text
STAGED_SOURCE_SHA == AUTHORIZED_SHA
```

Do not deploy an evidence-only commit when a distinct engineering SHA is the
authorized runtime SHA.

## Preflight

Before activation verify:

1. exact engineering SHA authorized for deployment
2. current production HTTP health
3. `pkjetp` can write required application/release paths
4. backup destination is writable
5. protected wrappers are present and verified
6. current runtime/build IDs are known
7. database/app backup succeeds

If a protected wrapper is missing from `/tmp`, Cursor may use SSH to locate the
established copy, compare SHA256 where possible, restore it, and continue. Do
not recreate a wrapper casually when an authoritative copy exists.

## Build/runtime safety

- Do not restart/switch PM2 to a frontend whose `.next/BUILD_ID` is missing or invalid.
- If Dashboard/CMS code changed, require a dashboard build as well as public build.
- Treat runtime SHA markers as evidence only after activation/build/pre-proxy gates pass.
- Prefer graceful service reloads to full stop/start operations where supported.
- After any infrastructure/configuration change, verify ports/services/HTTP immediately.

## Defensive coding rules

### No blind namespace

Cursor must never use an unqualified class name in a namespaced PHP file unless:

1. the class exists in the same namespace, or
2. the exact `use ...;` import is present, or
3. the class is referenced with a fully-qualified name.

Search for the real class and verify its namespace before adding references.

### Non-critical paths must not crash critical pages

Login, registration, checkout, booking detail, dashboard and admin pages must
not fail because of non-critical email, notification, external API or file-generation
work. Use guarded error handling where the page must remain available.

### Incident fixes

During a production incident:

- identify root cause first
- apply the smallest reversible fix
- preserve/verify backups when changing server configuration
- avoid broad refactors
- verify live HTTP/process/log state immediately afterward

## Postdeploy verification

A deployment is not complete until applicable gates pass:

- production SHA equals authorized engineering SHA
- public build PASS
- dashboard build PASS when applicable
- pre-proxy/live HTTP PASS
- affected browser workflows PASS
- network/API evidence PASS
- fresh Laravel/server logs show no related ERROR/CRITICAL entries
- independent verifier PASS when required by the active closure

Production browser evidence must use `https://jetpakistan.pk`.

## Completion report

Record at minimum:

- engineering SHA deployed
- remote evidence head separately, if different
- backup/release identifier
- public/dashboard build IDs
- activation/build/pre-proxy results
- production HTTP/runtime state
- browser/API/log UAT
- rollback reference
- known limitations
- final verifier result

Never report PASS while a mandatory acceptance gate remains FAIL, PARTIAL or
BLOCKED unless the owner explicitly approves that gate as a documented exception.

## Related authority

- `CLAUDE.md`
- `docs/jetpk/DEPLOYMENT-CONTEXT.md`
- `scripts/jetpk/stage-release-from-sha.sh`
- `scripts/jetpk/apply-delete-manifest.sh`
- `scripts/jetpk/README.md`
