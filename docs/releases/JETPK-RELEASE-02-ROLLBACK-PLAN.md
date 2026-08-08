# JETPK-RELEASE-02 — Rollback Plan

**Engineering SHA:** `b95efd47cd2fb531c90743cf2e1d4a3de1ebc79a`  
**Status:** LOCKED — backup tooling proven on host

---

## 1. Rollback triggers

- Public proxy cutover causes sitewide 500 or client-side exception
- Laravel API/session routes broken after cutover
- Migration failure with data impact
- Dashboard/admin inaccessible
- Next process crash loop or OOM
- `public_html/storage` symlink broken

---

## 2. Rollback sequence

### R1 — Stop Next processes

```bash
export PATH=$HOME/.npm-global/bin:$PATH
pm2 stop jetpk-public-frontend jetpk-dashboard 2>/dev/null || true
pm2 delete jetpk-public-frontend jetpk-dashboard 2>/dev/null || true
```

### R2 — Restore proxy / vhost

1. Restore CyberPanel vhost config from deployment-window backup.
2. Restore `public_html/.htaccess` from `public_html-<TIMESTAMP>.tar.gz` if modified.
3. Confirm `https://jetpakistan.pk/` returns Blade/Laravel baseline (200).

### R3 — Restore filesystem (if code deploy failed)

```bash
tar xzf /home/pkjetp/backups/jetpk_app-<TIMESTAMP>.tar.gz -C /home/pkjetp
tar xzf /home/pkjetp/backups/public_html-<TIMESTAMP>.tar.gz -C /home/pkjetp
```

**Preserve:** `storage/app/private/` booking documents unless corruption proven.

### R4 — Database restore (only if migrations caused data issue)

```bash
gunzip -c /home/pkjetp/backups/jetpk-db-<TIMESTAMP>.sql.gz | mysql -u ... <database>
```

Forward-fix preferred over rollback for non-migration issues.

### R5 — Cache clear (only if restored code requires)

```bash
PHP=/usr/local/lsws/lsphp83/bin/php
cd /home/pkjetp/jetpk_app
$PHP artisan config:clear
$PHP artisan route:clear
$PHP artisan view:clear
```

### R6 — Post-rollback validation

| Check | Expected |
|-------|----------|
| Homepage | Blade 200 |
| `/login` | 200 |
| Admin legacy/Blade | Accessible per pre-cutover |
| `public_html/storage` | Symlink valid |
| nghttpx on 3000 | Unchanged |

---

## 3. Backup prerequisites (proven)

| Backup | Command output verified |
|--------|-------------------------|
| `mysqldump` | `/usr/bin/mysqldump` MariaDB 10.11.18 |
| Disk headroom | 80G available on `/` |
| Target path | `/home/pkjetp/backups/` (create at deploy) |

**Rollback readiness:** **READY** (contingent on deployment-window backups being created).

---

## 4. Exclusions

- No `git reset --hard` on production
- No force-push as rollback mechanism
- Do not stop nghttpx on port 3000 during rollback
