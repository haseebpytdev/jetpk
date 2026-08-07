# JETPK-RELEASE-02 — Rollback Plan

**Engineering SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Status:** DRAFT — requires proven backups from deployment window

Rollback **must not** rely on `git reset --hard` on production or force-push. Filesystem + database restore is authoritative.

---

## 1. Rollback trigger conditions

| Trigger | Severity |
|---------|----------|
| Public proxy cutover causes sitewide 500 or client-side exception | **Immediate** |
| Laravel API/session routes broken after cutover | **Immediate** |
| Migration caused data corruption or schema mismatch | **Immediate** |
| Dashboard cutover breaks admin/staff operations | **High** |
| Next processes unstable (crash loop, OOM) | **High** |
| Storage symlink broken or uploads inaccessible | **Immediate** |

---

## 2. Rollback sequence

### Step R1 — Stop new processes

```bash
PM2=$(command -v pm2)
if [ -n "$PM2" ]; then
  $PM2 stop jetpk-public-frontend 2>/dev/null || true
  $PM2 stop jetpk-dashboard 2>/dev/null || true
  $PM2 delete jetpk-public-frontend 2>/dev/null || true
  $PM2 delete jetpk-dashboard 2>/dev/null || true
fi
```

### Step R2 — Restore reverse proxy / `.htaccess`

1. Restore `public_html/.htaccess` from deployment-window backup (pre-cutover).
2. Disable LiteSpeed proxy rules pointing to Next ports.
3. Confirm `https://jetpakistan.pk/` serves **Blade/Laravel** baseline.

### Step R3 — Restore filesystem

1. Restore `jetpk_app` from tar backup if code deploy caused failure.
2. Restore `public_html` critical paths from tar backup.
3. **Preserve** `public_html/storage` symlink target unless backup proves corruption.
4. Do not delete `storage/app/private/` booking documents.

### Step R4 — Database decision

| Situation | Action |
|-----------|--------|
| Migrations ran successfully, no data issue | **No DB restore** |
| Migration failure mid-run | Restore DB from `jetpk-db-<TIMESTAMP>.sql.gz` |
| Data corruption after cutover | Restore DB from pre-cutover backup |

```bash
# Example restore (adjust credentials via secure ops channel — never commit)
gunzip -c /home/pkjetp/backups/jetpk-db-<TIMESTAMP>.sql.gz | mysql -u ... <database>
```

### Step R5 — Cache handling (only if required)

If restored code predates config cache:

```bash
PHP=/usr/local/lsws/lsphp83/bin/php
cd /home/pkjetp/jetpk_app
$PHP artisan config:clear
$PHP artisan route:clear
$PHP artisan view:clear
```

Do **not** run `migrate:rollback` across data-bearing migrations without explicit ops authorization.

### Step R6 — Validation after rollback

| Check | Expected |
|-------|----------|
| `https://jetpakistan.pk/` | Blade homepage loads |
| `/login` | Auth form loads |
| Admin Blade/legacy dashboard | Accessible per pre-cutover behavior |
| `public_html/storage` | Symlink valid |
| Booking documents | Still present under `storage/app/private/` |

### Step R7 — Post-rollback

1. Record incident, SHA deployed, backup IDs used.
2. Do not re-attempt cutover until root cause resolved and Loop B re-validated.

---

## 3. What rollback does NOT do

- Does not revert GitHub `main` (repository rollback is separate).
- Does not force-push any branch.
- Does not automatically disable `OTA_DEVELOPER_CP_ENABLED` (verify separately if related).

---

## 4. Backup prerequisites (must exist before any cutover)

| Backup | Path pattern | Verified |
|--------|--------------|----------|
| Database | `/home/pkjetp/backups/jetpk-db-<TIMESTAMP>.sql.gz` | **Pending SSH** |
| Laravel tree | `/home/pkjetp/backups/jetpk_app-<TIMESTAMP>.tar.gz` | **Pending SSH** |
| public_html | `/home/pkjetp/backups/public_html-<TIMESTAMP>.tar.gz` | **Pending SSH** |
| `.htaccess` | Included in public_html archive | **Pending SSH** |

**Rollback readiness:** **NOT READY** until backups are proven in deployment window.
