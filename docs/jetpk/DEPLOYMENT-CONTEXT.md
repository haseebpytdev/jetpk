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

Direct or ad-hoc production access by Cursor remains forbidden. After explicit
owner authorization in the active task, Cursor may execute only the
established protected JetPakistan deployment scripts and their documented
read-only verification operations.

| Operation | Cursor agent |
|---|---|
| Direct SSH | Forbidden |
| Direct SFTP/SCP | Forbidden |
| Ad-hoc production commands | Forbidden |
| Protected JetPK scripts after explicit owner authorization | Allowed |
| Documented read-only verification | Allowed |
| Production browser verification | Allowed on `https://jetpakistan.pk` only |

The protected-script exception does not enable unrestricted SSH/SFTP, ad-hoc
transfer, supplier action, booking, ticketing, refund, payment, credential
change, markup mutation, or production-data mutation.

## Supported deployment route

```text
SUPPORTED_DEPLOYMENT_ROUTE=CURSOR_PROTECTED_DEPLOYMENT_ALLOWED
DIRECT_SSH_BY_CURSOR=FORBIDDEN
DIRECT_SFTP_BY_CURSOR=FORBIDDEN
AD_HOC_PRODUCTION_COMMANDS=FORBIDDEN
OWNER_AUTHORIZED_PROTECTED_SCRIPTS=ALLOWED
DOCUMENTED_READ_ONLY_VERIFICATION=ALLOWED
```

The sequence below is copied from the established protected scripts; execute
it only after explicit owner authorization in the active task, stop on any
non-zero result or failed gate, and do not replace it with ad-hoc SFTP or SSH
commands:

```bash
bash jetpk-backup.sh
bash jetpk-stage-release.sh
bash jetpk-deploy.sh <RELEASE_DIR> <TIMESTAMP>
bash jetpk-next-build.sh
bash jetpk-pre-proxy-gate.sh
```

Use the `RELEASE_STAGED_AT` value emitted by `jetpk-stage-release.sh` as
`<RELEASE_DIR>` and the corresponding release timestamp as `<TIMESTAMP>`.

After the protected scripts pass, perform the documented browser smoke test
and log verification against `https://jetpakistan.pk`; no other public host is
an accepted JetPakistan production evidence source.

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
