# JETPK-RELEASE-01 — Rollback Plan

**Baseline deployed:** `8d62db8c2a37038e52e3130d45b9ad284510bfee`
**Rollback target:** Pre-deploy backup (not a specific git SHA on server — SFTP deploy)
**Database rollback:** **Not safe** as default — restore from mysqldump backup

---

## 1. Rollback prerequisites

Before any deploy, confirm these exist (see deployment plan Step 2):

| Artifact | Path |
|----------|------|
| Backup pointer | `/home/pkjetp/jetpk_app/storage/app/jetpk-release-01-last-backup-path.txt` |
| Application archive | `$BACKUP/jetpk_app.tar.gz` + `.sha256` |
| Public webroot archive | `$BACKUP/public_html.tar.gz` |
| Environment backup | `$BACKUP/.env.backup` |
| Database dump | `$BACKUP/database.sql` (operator-created) |
| Pre-migrate status | `storage/logs/pre-migrate-*.txt` |
| PM2 dump | `$PM2_HOME/dump.pm2` (optional pre-change copy) |

**Abort rollback** if backup checksum fails or `.env.backup` is empty.

---

## 2. Rollback trigger criteria

| Severity | Condition |
|----------|-----------|
| Critical | Public homepage or login 500 |
| Critical | `ota:route-page-health-audit --all` fail > 0 after deploy |
| Critical | Migration partially applied then failed |
| High | Next public frontend unreachable; proxy serves broken site |
| High | New `production.ERROR` on core paths |
| High | Visible legacy branding (Parwaaz/Master) |
| High | CMS fixture/demo content in production |
| Medium | Dashboard PM2 offline (if dashboard was touched) |
| Medium | Queue worker crash loop |

---

## 3. Rollback sequence

### Step 1 — Enable maintenance

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app
cd "$APP"
$PHP artisan down --retry=60
```

### Step 2 — Load backup path

```bash
BACKUP=$(cat "$APP/storage/app/jetpk-release-01-last-backup-path.txt")
test -d "$BACKUP" || { echo "ROLLBACK_ABORT: backup missing"; exit 1; }
sha256sum -c "$BACKUP/jetpk_app.tar.gz.sha256" || { echo "ROLLBACK_ABORT: checksum fail"; exit 1; }
```

### Step 3 — Stop Node processes

```bash
export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
PM2=/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2

$PM2 stop jetpk-public-frontend 2>/dev/null || true
$PM2 stop jetpk-dashboard 2>/dev/null || true
```

### Step 4 — Restore environment

```bash
cp -a "$BACKUP/.env.backup" "$APP/.env"
test -s "$APP/.env" || { echo "ROLLBACK_ABORT: .env restore failed"; exit 1; }
```

### Step 5 — Restore application files

**Preserves:** `vendor/` on disk (faster rollback — same as canonical UI runbook).

```bash
cd /home/pkjetp

# Extract over jetpk_app excluding vendor if backup was created with exclusions
tar -xzf "$BACKUP/jetpk_app.tar.gz" -C /home/pkjetp

if [ $? -ne 0 ]; then echo "ROLLBACK_ABORT: app extract failed"; exit 1; fi
```

If backup used selective file copy (incremental style), restore from `MANIFEST.tsv` per `docs/dashboard/DASHBOARD-ROLLBACK.md` pattern.

### Step 6 — Restore public_html

```bash
tar -xzf "$BACKUP/public_html.tar.gz" -C /home/pkjetp
if [ $? -ne 0 ]; then echo "ROLLBACK_ABORT: public_html extract failed"; exit 1; fi
```

Verify theme CSS SHA matches pre-deploy record if captured.

### Step 7 — Database rollback limitations

**Do not run `php artisan migrate:rollback`** across the full JP-FULLSTACK migration set on production data.

| Scenario | Action |
|----------|--------|
| Migrations never ran | No DB action |
| Migrations ran successfully but app broken | **Forward-fix** preferred; DB restore only if data corruption |
| Migration failed mid-batch | Restore `database.sql` from backup; verify `migrations` table consistency |
| Data backfill ran (`2026_07_13_000001_*`) | DB restore only safe reversal |

```bash
# Only when explicit DB restore authorized:
# mysql ... < "$BACKUP/database.sql"
```

### Step 8 — Rebuild caches (restored code)

```bash
cd "$APP"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
```

### Step 9 — Restart queue

```bash
$PHP artisan queue:restart
```

### Step 10 — Restart Node / PM2

Restore pre-deploy PM2 state:

```bash
# If public Next did not exist before deploy:
$PM2 delete jetpk-public-frontend 2>/dev/null || true

# Restart dashboard if it existed
cd "$APP/dashboard"
$PM2 restart jetpk-dashboard 2>/dev/null || true
$PM2 save
```

If vhost was reconfigured to proxy :3000, **revert proxy** to pre-deploy Blade/Laravel routing.

### Step 11 — Disable maintenance

```bash
cd "$APP"
$PHP artisan up
```

### Step 12 — Post-rollback verification

```bash
curl -sS -o /dev/null -w "home %{http_code}\n" https://www.jetpakistan.com/
$PHP artisan ota:route-page-health-audit --all
tail -n 80 "$APP/storage/logs/laravel.log"
```

Expected: pre-deploy behavior restored; `fail=0` on route health audit.

---

## 4. Partial rollback (hotfix path)

For single-file regressions without full archive restore:

1. Restore specific paths from `$BACKUP/jetpk_app/` mirror (if selective backup)
2. Mirror matching `public_html` paths
3. `php artisan optimize:clear` + relevant cache rebuild
4. `queue:restart`

Document restored paths in operator log.

---

## 5. CMS / content rollback

JP-FULLSTACK-01G hardened CMS fixtures — rollback must not re-enable:

- `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=true`
- `OTA_ALLOW_SESSION_FIXTURE=true` for CMS

CMS database content is **not** rolled back by file restore alone. Use CMS admin or DB backup if content migrations ran.

---

## 6. Sabre / payment safety on rollback

Rollback **must not** automatically change:

- `SABRE_BOOKING_ENABLED`
- `SABRE_TICKETING_ENABLED`
- `*_LIVE_CALL_ENABLED` flags

`.env.backup` restores prior values — verify grep after restore:

```bash
grep -E '^SABRE_(BOOKING|TICKETING)_ENABLED=' "$APP/.env"
```

---

## 7. OTP demo preservation

`.env.backup` includes `OTP_DEMO_*` as deployed. Do not strip demo patch during rollback.

---

## 8. Maintenance-mode recovery

If `artisan up` fails:

```bash
rm -f "$APP/storage/framework/down"
$PHP artisan up
```

---

## 9. Rollback time estimate

| Phase | Estimate |
|-------|----------|
| Maintenance + stop PM2 | 2 min |
| Extract app + public_html archives | 5–15 min |
| Cache rebuild + queue restart | 3 min |
| DB restore (if required) | 10–30 min |
| Smoke verification | 10 min |

---

## 10. Post-rollback reporting

Record:

- Rollback timestamp (UTC)
- Backup path used
- Whether DB was restored
- PM2 / proxy state
- `ota:route-page-health-audit` result
- Operator sign-off

Do not delete `$BACKUP` until post-rollback stability confirmed (retain ≥ 7 days).

---

## 11. JETPK-RELEASE-01A / ACCESS-01-R2 — Rollback implications

**Authoritative production host:** `185.215.166.176`
**Production hostname:** `vmi3400777`
**Inspection:** 2026-08-06T18:18Z (read-only; no mutations)

### Verified pre-deploy production state

| Surface | Current state | Post-deploy (`8d62db8`) | Rollback action |
|---------|---------------|-------------------------|-----------------|
| Public renderer | Laravel Blade (`public_html/index.php`) | Next.js :3000 + proxy | Revert vhost; stop/remove PM2 public process |
| `frontend/` | **Absent** | Required | N/A — restore from backup only if created |
| PM2 | **Not installed** | `jetpk-public-frontend`, `jetpk-dashboard` | Delete PM2 processes if created |
| Migrations | **104/104 Ran** (0 pending) | No new migrations expected | **Do not** `migrate:rollback`; DB restore if data migration issues |
| Dual-root assets | Drift in `themes`/`client-assets`/`js` | Full mirror | Restore `public_html` from backup |
| PHP CLI | `/usr/local/lsws/lsphp83/bin/php` | Same | — |
| Queue | `sync` driver | May change to `database` | Restore `.env.backup` |
| Session/cache | `file` drivers | May change | Restore `.env.backup` |

### SSH and backup prerequisites (now verifiable)

- SSH to `185.215.166.176` **works** with agent-unlocked key
- `public_html/.htaccess` exists (837 bytes, Laravel rewrite only — **back up before proxy changes**)
- PM2 dump likely **N/A** (PM2 not installed pre-deploy)
- `jetpk-dashboard` / `jetpk-public-frontend` **do not exist** pre-deploy

### Database rollback

All JP-FULLSTACK candidate migrations **already applied** including data backfill `2026_07_13_000001`. Deploy should **skip** `php artisan migrate` unless new migrations added after `8d62db8`. Rollback of file changes does **not** reverse CMS backfill data — DB restore required.
