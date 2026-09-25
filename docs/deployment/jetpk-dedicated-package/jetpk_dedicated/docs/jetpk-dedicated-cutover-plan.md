# JetPK dedicated server — cutover plan

**Phase:** 7I — Mode B (`jetpakistan.com`)  
**No tar.gz, no destructive rsync, no rm -rf**

---

## 1. Pre-upload

- [ ] DNS ready
- [ ] MySQL database created
- [ ] `.env` from `.env.example.jetpk` (on server only)
- [ ] Local `ota:jetpk-dedicated-package-audit` passes
- [ ] Backup shared-server JetPK public assets

---

## 2. Laravel app → `ota_app/`

Upload: `app/`, `bootstrap/`, `config/`, `database/migrations/`, `routes/`, `resources/`, `public/index.php`, `composer.*`, `artisan`, `clients/jetpk/`

Exclude: `.env`, `vendor/`, `storage/logs/`, baselines.

---

## 3. Public webroot → `public_html/`

| Source (shared Hostinger) | Target (dedicated) |
|---------------------------|-------------------|
| `.../ota.haseebasif.com/themes/frontend/jetpakistan/` | `public_html/themes/frontend/jetpakistan/` |
| `.../ota.haseebasif.com/themes/admin/jetpakistan/` | `public_html/themes/admin/jetpakistan/` |
| `.../ota.haseebasif.com/client-assets/jetpk-assets/` | `public_html/client-assets/jetpk-assets/` |
| `.../ota.haseebasif.com/storage/` | `public_html/storage/` |
| `public/css/`, `public/js/`, `public/images/` | `public_html/css/`, `js/`, `images/` |

---

## 4. `.env` setup

```bash
cd /home/<user>/domains/jetpakistan.com/ota_app
cp .env.example.jetpk .env
php artisan key:generate
```

Set: `APP_URL=https://jetpakistan.com`, `OTA_CLIENT_SLUG=jetpk`, `DB_*`, `MAIL_*`, `OTA_PUBLIC_WEBROOT_PATH`.

---

## 5–8. Composer, storage, migrate, cache

```bash
composer install --no-dev --optimize-autoloader
php artisan storage:link
php artisan migrate:status
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan cache:clear
```

---

## 9. Scheduler & queue

Cron: `* * * * * cd .../ota_app && php artisan schedule:run`

Queue (if database): `php artisan queue:work --sleep=3 --tries=3`

---

## 10. OAuth

Google callback: `https://jetpakistan.com/auth/google/callback`

---

## 11–16. Tests

Mail test → login → `/admin` → public routes → supplier settings (masked) → Page Settings → branded fares → groups.

---

## 17. Verification

```bash
php artisan ota:jetpk-dedicated-package-audit --client=jetpk
php artisan ota:jetpk-dedicated-server-readiness --client=jetpk
php artisan ota:jetpk-theme-isolation-audit --client=jetpk
php artisan ota:route-page-health-audit --all
date '+SERVER_MARKER %Y-%m-%d %H:%M:%S'
grep -n "production.ERROR" storage/logs/laravel.log | tail -n 40
```

---

## 18. Rollback

1. Stop uploads
2. Restore `ota_app` + `public_html` from backup
3. Restore DB snapshot
4. Revert DNS if switched
5. Verify shared Master: `ota:client-context-flow-audit --client=jetpk`

Mode A (`/jetpk/*` on shared server) remains unchanged throughout.
