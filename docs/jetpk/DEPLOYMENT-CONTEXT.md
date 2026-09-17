# JetPakistan deployment context

**Authority:** current JetPakistan production context for this repository.

Read this document before interpreting any deployment, SSH/SFTP, server-management,
or production-browser instruction. Historical shared-preview, staging, and
older host documents may retain obsolete examples; they must not select the
current JetPakistan production host.

## Canonical identity

```text
CANONICAL_PROJECT=JetPakistan
CANONICAL_PUBLIC_HOST=https://jetpakistan.pk
CANONICAL_PRODUCTION_IP=185.215.166.176
```

These hosts are forbidden for JetPakistan production operations:

```text
ota.haseebasif.com
haseebasif.com
jetpakistan.com
```

Unrelated client hosts are also forbidden. `ota.haseebasif.com` is a historical
shared-preview/staging context, not the JetPakistan production site.

## Established production topology

| Role | Authoritative value |
|---|---|
| Laravel application | `/home/pkjetp/jetpk_app` |
| Public Next application | `/home/pkjetp/jetpk_app/frontend` |
| Public webroot | `/home/pkjetp/public_html` |
| Public frontend PM2 process | `jetpk-public-frontend` |
| Dashboard PM2 process | `jetpk-dashboard` |
| Private Laravel listener | `127.0.0.1:8088` |
| Authoritative PHP | `/usr/local/lsws/lsphp83/bin/lsphp` |
| Forbidden PHP | `/opt/alt/php-fpm83/usr/bin/php` |

The private Laravel listener is server-side only. It must never be used as a
browser URL.

## Standing owner authorization for Cursor production access

The owner has explicitly authorized Cursor to keep direct operational access to
the canonical JetPakistan production environment as part of normal project work.
This standing authorization replaces the older blanket `Direct SSH = Forbidden`
rule.

Cursor may use the configured JetPakistan SSH credentials directly for scoped
JetPakistan server management, deployment, troubleshooting, live production
verification, and evidence capture without requiring the owner to manually run
each SSH command.

| Operation | Cursor agent |
|---|---|
| Direct SSH to canonical JetPakistan host | Allowed |
| Direct SFTP/SCP for legitimate JetPakistan operational recovery/server management | Allowed when needed |
| Protected JetPK deployment scripts | Preferred/Allowed |
| Scoped server-management commands | Allowed |
| Read-only filesystem/config/log/process inspection | Allowed |
| PM2/build/cache/Artisan/runtime verification | Allowed |
| Production browser/API verification | Allowed on `https://jetpakistan.pk` |
| Read-only live fare/inventory/search verification | Allowed |
| Dedicated QA/admin credential use for sanctioned UAT | Allowed |
| Safe reversible CMS QA draft/publish/restore | Allowed when required by acceptance testing |

Routine operations within this standing authorization should be carried through
by Cursor itself when access is available. The owner should not be asked to run
manual SSH commands merely because the agent has a stale policy restriction.

### Boundaries that remain mandatory

Standing server access does not authorize destructive or commercially mutating
production actions. Cursor must not, solely for testing/evidence:

- create bookings, PNRs, holds, tickets, voids, cancellations, refunds, or payments
- mutate supplier inventory or settlement state
- perform destructive database resets, drops, truncates, or bulk edits
- expose secrets, credentials, SSH keys, tokens, or customer PII in evidence
- force push or rewrite Git history
- delete the production application/release tree wholesale
- perform unrelated package upgrades, OS upgrades, or server reboot without task justification
- rotate production credentials unless the active task requires it or the owner explicitly requests it
- use historical/unrelated hosts as JetPakistan production

Infrastructure changes must be scoped, backed up/reversible, and immediately
followed by service-health verification. Prefer graceful reloads over full
stop/start sequences when possible.

- deploy only an explicit authorized Git SHA through backup/stage/deploy gates
- backup before production mutation
- stop on any failed pre-deploy or post-deploy gate
- no destructive Git
- no destructive database operations
- no real booking/PNR/ticket/refund/payment creation for QA evidence
- no supplier inventory mutation
- no secret/PII exposure in logs or commits
- prefer protected deployment scripts over ad-hoc file transfer when available
- prefer graceful service management over hard restarts when possible

## Supported deployment route

```text
SUPPORTED_DEPLOYMENT_ROUTE=CURSOR_SSH_AND_PROTECTED_DEPLOYMENT_ALLOWED
DIRECT_SSH_BY_CURSOR=ALLOWED
DIRECT_SFTP_BY_CURSOR=ALLOWED_WHEN_NEEDED
SCOPED_SERVER_MANAGEMENT=ALLOWED
LIVE_PRODUCTION_UAT=ALLOWED
OWNER_STANDING_AUTHORIZATION=ACTIVE
PROTECTED_SCRIPTS=PREFERRED
DOCUMENTED_READ_ONLY_VERIFICATION=ALLOWED
LIVE_PRODUCTION_BROWSER_UAT=ALLOWED
```

Application releases should still use the established protected Git-SHA
workflow whenever available. Direct SSH access exists so Cursor can execute,
inspect, recover, and manage that workflow itself rather than delegating normal
server work back to the owner.

Canonical sequence:

```bash
bash jetpk-backup.sh
AUTHORIZED_SHA=<approved-git-sha> bash jetpk-stage-release.sh
bash jetpk-deploy.sh <RELEASE_DIR> <TIMESTAMP>
bash jetpk-next-build.sh
bash jetpk-pre-proxy-gate.sh
```

For a genuinely public-only release, `PUBLIC_ONLY=1 bash jetpk-next-build.sh`
may be used only when no dashboard source changed. If Dashboard/CMS source changed,
run the normal full build without `PUBLIC_ONLY=1`.

`jetpk-stage-release.sh` must receive an explicit `AUTHORIZED_SHA`. It stages
runtime files from Git at that SHA through the tracked helper
`scripts/jetpk/stage-release-from-sha.sh`. Hard-coded historical archives are
forbidden. Required staging invariant:

```text
STAGED_SOURCE_SHA == AUTHORIZED_SHA
```

Use the `RELEASE_STAGED_AT` value emitted by `jetpk-stage-release.sh` as
`<RELEASE_DIR>` and the corresponding release timestamp as `<TIMESTAMP>`.
When the staged release includes `DELETE_RUNTIME_FILES`, `jetpk-deploy.sh`
applies only those exact allowlisted paths after backup.

After file activation, `jetpk-deploy.sh` normalizes and asserts runtime ownership
via `scripts/jetpk/assert-runtime-ownership.sh`. `jetpk-pre-proxy-gate.sh`
re-runs the assert-only gate and fails closed when any Laravel writable runtime
path is not owned by `pkjetp:pkjetp`. Root-run deploy extract, copy, and artisan
steps must not leave root-owned files under `storage/` or `bootstrap/cache/`.

If an established protected wrapper is missing from `/tmp`, Cursor may use SSH
to locate and restore the authoritative existing copy, verify SHA256, and then
continue the protected sequence. Do not invent a new deployment wrapper when an
established copy exists.

After the protected scripts pass, Cursor should complete the documented browser,
API, runtime and log verification itself against `https://jetpakistan.pk`; no
other public host is an accepted JetPakistan production evidence source.

## Source records

- `CLAUDE.md` — standing agent production-access boundary
- `docs/PRODUCTION_DEPLOYMENT_SAFETY.md` — current JetPakistan production safety workflow
- `docs/jetpk/JETPK-PRODUCTION-CLOSURE-2026-08-08.md` — established topology
- `docs/jetpk/sftp-deployment-checklist.md` — JetPK gates and smoke scope
- `scripts/jetpk/stage-release-from-sha.sh` — tracked SHA-parameterized staging
- `scripts/jetpk/apply-delete-manifest.sh` — tracked exact deletion helper
- `scripts/jetpk/assert-runtime-ownership.sh` — runtime ownership normalize/assert gate
- `scripts/jetpk/README.md` — staging/deletion/ownership usage
- `tmp/jetpk-backup.sh`
- `tmp/jetpk-stage-release.sh`
- `tmp/jetpk-deploy.sh`
- `tmp/jetpk-next-build.sh`
- `tmp/jetpk-pre-proxy-gate.sh`
