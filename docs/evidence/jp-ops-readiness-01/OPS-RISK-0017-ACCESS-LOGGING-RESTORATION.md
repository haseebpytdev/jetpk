# OPS-RISK-0017 — Access logging restoration (URI-free)

**Date (UTC):** 2026-10-17  
**Mode:** Production server operations + observability hardening  
**Protected main (evidence base):** `2445b7da336f7d591c3155479030b2a9bf368c86`  
**Production runtime base (unchanged):** `922f15774ece177d8b3cc23207e98379b8a2755b`  
**App code changed:** NO  
**Supplier/payment mutations:** 0

## Summary

Restored **writing** JetPakistan vhost access logging at `/home/pkjetp/logs/access.log` using **Strategy A** (URI-free `logFormat`) so legacy `/guest/bookings/{id}/access/{token}` bearer paths cannot be archived in the access log.

```text
OPS_RISK_0017_STATUS=CLOSED_GENERAL_LOGGING_RESTORED
SAFE_ACCESS_LOG_STRATEGY=A
RAW_GUEST_TOKEN_LOGGING_POSSIBLE_AFTER_CHANGE=NO
SAFE_LEGACY_ROUTE_USAGE_MEASUREMENT_AVAILABLE_FROM_ACCESS_LOG=NO
SANITIZED_LEGACY_ROUTE_COUNTER_REQUIRED=YES
OPS_DEF_0011_SANITIZED_TELEMETRY_REQUIRED=YES
```

## Phase 0 — Baseline health

| Probe | Result |
|-------|--------|
| `CANONICAL_APP_ROOT` | `/home/pkjetp/jetpk_app` |
| HTTP `/up` `/` `/login` `/lookup-booking` `/agent` | 200 |
| HTTP `/admin` | 302 |
| Public BUILD_ID | `HNDspC9CoB4Bat_x0DwZ6` |
| Dashboard BUILD_ID | `7-iZf4RbaJmGpuDjO1tsh` |
| `PENDING_DEFAULT_PRE` | 0 |
| Failed jobs | not touched |

## Phase 1 — Active web server

| Field | Value |
|-------|-------|
| `WEB_SERVER` | OpenLiteSpeed |
| `WEB_SERVER_VERSION` | LiteSpeed/1.9.0 Open (build 2026-05-07) |
| `WEB_SERVER_CONFIG_ROOT` | `/usr/local/lsws/conf` |
| Main PID | `lshttpd` main as root; workers as `nobody` |
| `ACTIVE_VHOST_NAME` | `jetpakistan.pk` |
| `ACTIVE_VHOST_CONFIG` | `/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf` |
| `ACTIVE_LISTENER` | `Default` + `SSL` map `jetpakistan.pk,www.jetpakistan.pk` |
| `VHOST_DOCROOT` | `$VH_ROOT/public_html/` → `/home/pkjetp/public_html/` |
| `VH_ROOT` | `/home/pkjetp/` |

## Phase 2 — Effective access log config (before)

| Field | Value |
|-------|-------|
| Server `accessLog` | `logs/access.log` → `/usr/local/lsws/logs/access.log` (was writing) |
| Vhost `accessLog` | `$VH_ROOT/logs/access.log` → `/home/pkjetp/logs/access.log` |
| `VHOST_USES_SERVER_LOG` | NO (`useServer 0`) |
| Prior format | OLS default combined-style (`logReferer 1`, `logUserAgent 1`) → includes full request target |
| Domain log before | size 153B; mtime 2026-07-29; single stale line |
| Rotation | `rollingSize 10M`, `keepDays 30`, `compressArchive 0` |

## Phase 3 — Root cause

```text
OPS_RISK_0017_ROOT_CAUSE=
DIRECTORY_PERMISSIONS_BLOCK_WRITER:
/home/pkjetp/logs was mode 750 owner pkjetp:pkjetp;
OLS workers run as nobody:nogroup and could not traverse/write the directory,
so vhost accessLog stopped updating after mid-2026 while config remained enabled.
```

Server-level `/usr/local/lsws/logs/access.log` continued writing (unrelated noise; not used as JetPakistan domain telemetry).

## Phase 4 — Token exposure assessment (before change)

| Field | Value |
|-------|-------|
| `RAW_REQUEST_TARGET_WOULD_BE_LOGGED` | YES (default `%r`) |
| `QUERY_STRING_WOULD_BE_LOGGED` | YES (via `%r`) |
| `REFERER_WOULD_BE_LOGGED` | YES (`logReferer 1`) |
| `COOKIE_WOULD_BE_LOGGED` | NO |
| `AUTHORIZATION_WOULD_BE_LOGGED` | NO |

Enabling writes **without** format change would have created a raw guest-token archive risk while Phase-1 legacy exchange routes remain.

## Phase 5 — Safe design applied

```text
SAFE_ACCESS_LOG_STRATEGY=A
SAFE_ACCESS_LOG_FORMAT=%h %l %u %t "%m" %>s %b %T "%{User-Agent}i"
```

Explicitly omitted: `%r`, `%U`, `%q`, `%f`, `%{Referer}i`; `logReferer 0`; `logUserAgent 0` (UA only via format field).

```text
RAW_GUEST_TOKEN_LOGGING_POSSIBLE_AFTER_CHANGE=NO
```

Strategy B (in-native path redaction) not available in OLS `logFormat`. Strategy C (default combined) rejected while legacy `/access/{token}` exists.

## Phase 6 — Backup

| Field | Value |
|-------|-------|
| `BACKUP_PATH` | `/home/pkjetp/backups/ops-risk-0017-access-log-20261017T111307Z` |
| `CONFIG_FILES_BACKED_UP` | `vhconf.conf` |
| `CONFIG_BACKUP_SHA256` | `1aa4df37ae120069ee3854b76fa4b3de298f6b9af6b325813e3b663ef4df8b67` |
| `ROLLBACK_READY` | YES |

Also retained: `access.log.stat.before`, `logs.dir.stat.before`, `access.log.pre-rotate`.

## Phase 7–9 — Apply + reload

| Field | Value |
|-------|-------|
| `CONFIG_VALIDATION_PRE_APPLY` | PASS (config parse via `openlitespeed`/`lshttpd`; WARN only on unrelated Example/ota.iati.pk UID notes) |
| Directory fix | `chown pkjetp:nogroup /home/pkjetp/logs`; `chmod 770`; log files `nobody:nogroup` mode `640` |
| `RELOAD_REQUIRED` | YES |
| `RELOAD_METHOD` | `/usr/local/lsws/bin/lswsctrl reload` (SIGUSR1) |
| Health during reload | `/up` `/` `/login` `/lookup-booking` `/agent` 200; `/admin` 302 |

## Phase 10–12 — Post health + write + privacy

| Field | Value |
|-------|--------|
| HTTP post probes | same as pre (healthy) |
| BUILD IDs | unchanged |
| `ACCESS_LOG_WRITING` | YES (size grew; new lines after reload) |
| `LATEST_LOG_TIMESTAMP` | `17/Oct/2026:13:14:14 +0200` |
| `COOKIE_DATA_LOGGED` | NO |
| `AUTH_DATA_LOGGED` | NO |
| `SESSION_DATA_LOGGED` | NO |
| `RAW_GUEST_TOKEN_LOGGED` | NO |
| `QUERY_SECRET_LOGGED` | NO |

Safe probes only: `GET /up`, `/`, `/login`, `/lookup-booking`. **Zero** `/access/{token}` probes.

Old July combined-format line removed from the live file after backup (`access.log.pre-rotate`).

## Phase 13 — Rotation / disk

| Field | Value |
|-------|--------|
| `LOG_ROTATION_ENABLED` | YES (`rollingSize 10M`) |
| `LOG_RETENTION_DAYS` | 30 |
| `LOG_COMPRESSION` | 0 (`compressArchive 0`) |
| Disk after | `/home` ~48% used (healthy) |

## Phase 14 — Legacy route telemetry

URI-free Strategy A **cannot** count `/access/{token}` hits.

```text
SAFE_LEGACY_ROUTE_USAGE_MEASUREMENT_AVAILABLE_FROM_ACCESS_LOG=NO
SANITIZED_LEGACY_ROUTE_COUNTER_REQUIRED=YES
```

**Next code task (not this loop):** implement sanitized application counter for legacy guest exchange/mutation routes (route type + outcome + timestamp only; never token/path-with-token). Required before evidence-based OPS-DEF-0011 Phase-2 hard remove.

## Phase 15 — Status

```text
OPS_RISK_0017_STATUS=CLOSED_GENERAL_LOGGING_RESTORED
```

Not `CLOSED_ACCESS_LOGGING_RESTORED` with path-level guest telemetry — that remains intentionally unavailable under Strategy A.

## Phase 16 — Mutation assertions

```text
LARAVEL_RUNTIME_FILES_CHANGED=0
NEXT_RUNTIME_FILES_CHANGED=0
DATABASE_MUTATIONS=0
SABRE_PNR_CREATE_CALLS=0
AIRBLUE_LIVE_BOOKING_CALLS=0
SUPPLIER_BOOKING_CALLS=0
TICKETING_CALLS=0
LIVE_PAYMENT_CALLS=0
WALLET_MUTATIONS=0
LEDGER_MUTATIONS=0
```

## Rollback

Restore `$BACKUP_PATH/vhconf.conf` over  
`/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf`,  
restore prior logs directory ownership/mode if required, then:

```bash
/usr/local/lsws/bin/lswsctrl reload
```

## Next required action

1. Merge this evidence PR.
2. Implement OPS-DEF-0011 sanitized legacy-route counter (separate code loop).
3. After telemetry + timebox, proceed with Phase-2 legacy route removal.
