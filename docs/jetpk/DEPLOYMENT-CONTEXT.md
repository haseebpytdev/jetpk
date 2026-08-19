# JetPakistan deployment context

**Authority:** current JetPakistan production context for this repository.

Read this document before interpreting any deployment, SSH/SFTP, or production
browser instruction. Historical shared-preview, staging, and dedicated-domain
documents may retain older host examples; they must not select the current
JetPakistan production host.

## Canonical identity

```text
CANONICAL_PROJECT=JetPakistan
CANONICAL_PUBLIC_HOST=https://jetpakistan.pk
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

## Actor and access boundary

The repository's `CLAUDE.md` prohibits the Cursor agent from deploying to
production and from accessing production SSH or SFTP. Its local-only
development rule also means the agent must not perform production browser
verification.

This is an **AGENT_EXECUTION_PROHIBITED** boundary, not an
**ALL_PRODUCTION_DEPLOYMENT_PROHIBITED** policy. It does not prohibit the owner
from manually running an established, owner-approved protected JetPakistan
procedure.

| Operation | Cursor agent | Owner manual procedure |
|---|---|---|
| Direct SSH | Forbidden | Only through owner-approved access |
| Direct SFTP/SCP | Forbidden | Only through owner-approved access |
| Protected JetPK scripts | Forbidden to execute | Allowed when explicitly approved |
| Production browser verification | Forbidden | Allowed on `https://jetpakistan.pk` only |

No unrestricted SSH/SFTP, ad-hoc transfer, supplier action, booking, ticketing,
refund, payment, or production-data mutation is enabled by this document.

## Supported deployment route

```text
SUPPORTED_DEPLOYMENT_ROUTE=OWNER_MANUAL_PROTECTED_DEPLOYMENT_REQUIRED
```

Cursor may provide read-only guidance and inspect local protected scripts, but
must not execute them. The owner-only sequence below is copied from the
established protected scripts; stop on any non-zero result or failed gate:

```bash
bash jetpk-backup.sh
bash jetpk-stage-release.sh
bash jetpk-deploy.sh <RELEASE_DIR> <TIMESTAMP>
bash jetpk-next-build.sh
bash jetpk-pre-proxy-gate.sh
```

Use the `RELEASE_STAGED_AT` value emitted by `jetpk-stage-release.sh` as
`<RELEASE_DIR>` and the corresponding release timestamp as `<TIMESTAMP>`.
These commands are an owner-manual procedure only. Do not replace them with
ad-hoc SFTP or SSH commands, and do not run them from Cursor.

After the protected scripts pass, the owner performs the documented browser
smoke test and log verification against `https://jetpakistan.pk`; no other
public host is an accepted JetPakistan production evidence source.

## Source records

- `CLAUDE.md` — agent production-access boundary
- `docs/PRODUCTION_DEPLOYMENT_SAFETY.md` — generic safety workflow
- `docs/jetpk/JETPK-PRODUCTION-CLOSURE-2026-08-08.md` — established topology
- `docs/jetpk/sftp-deployment-checklist.md` — JetPK gates and smoke scope
- `tmp/jetpk-backup.sh`
- `tmp/jetpk-stage-release.sh`
- `tmp/jetpk-deploy.sh`
- `tmp/jetpk-next-build.sh`
- `tmp/jetpk-pre-proxy-gate.sh`
