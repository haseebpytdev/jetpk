# New client deployment checklist

Use this checklist when deploying the shared OTA codebase to a **new** client server.
Complete every step before handing off to the client.

## 1. Prepare client folder

- [ ] Copy `clients/_template/` → `clients/{client-slug}/`
- [ ] Update `client.json` (name, slug, domain, theme, locale, timezone, currency)
- [ ] Update `branding.json` (logo/favicon paths, colors, contact)
- [ ] Update `modules.json` (enable only required modules)
- [ ] Update `deployment.json` (hosting panel, SSH, paths — no secrets in git)
- [ ] Review `notes.md` for client-specific ops notes

## 2. Configure production environment

- [ ] Copy `clients/{slug}/env.production.example` → server `.env`
- [ ] Set `APP_KEY` (`php artisan key:generate` on server if empty)
- [ ] Set `APP_URL` to production domain
- [ ] Set database credentials
- [ ] Set mail credentials
- [ ] Set `OTA_CLIENT_SLUG`, `OTA_ACTIVE_THEME`, `OTA_PUBLIC_ASSET_PROFILE`
- [ ] Set `OTA_MODULE_*` flags to match `modules.json`
- [ ] Set supplier credentials via Admin → API Settings (not in git)
- [ ] Confirm `SABRE_*` and `ALHAIDER_*` flags match intended modules (off unless approved)

## 3. Backup existing server (if redeploy)

- [ ] Database dump
- [ ] `.env` backup
- [ ] `storage/` backup (if existing install)
- [ ] `public/client-assets/{slug}/` backup

## 4. Upload application code

- [ ] Upload changed app files only (SFTP single-file; no folder sync)
- [ ] Do **not** blind-overwrite entire `public_html`
- [ ] Upload `public/client-assets/{slug}/` assets (logo, favicon, banners)

## 5. Server setup commands (SSH)

```bash
cd /path/to/laravel
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Additional clears if needed:

```bash
php artisan view:clear    # after Blade changes
php artisan route:clear   # after route changes
php artisan storage:link  # if public/storage missing
```

## 6. Verification

- [ ] **Homepage** loads without 500; branding/assets correct
- [ ] **Login** works (customer and/or agent as applicable)
- [ ] **Admin** panel accessible for platform admin
- [ ] **Dev CP** — only if `OTA_MODULE_DEV_CP=true` and `OTA_DEVELOPER_CP_ENABLED=true`
- [ ] **Enabled modules only** — disabled modules not visible in nav / return 404 or gate message
- [ ] **Public assets** — logo, favicon, banners resolve under `/client-assets/{profile}/`
- [ ] **Storage symlink** — `public/storage` → `storage/app/public` exists
- [ ] Tail `storage/logs/laravel.log` — no new errors after smoke test

## 7. Post-deploy

- [ ] Update `deployment.json` → `last_deployed_at`, `last_deployed_by`
- [ ] Document rollback commit/tag
- [ ] Defer full manual QA (desktop/tablet/mobile) until all sprints complete, per sprint workflow

## Rollback

1. Restore prior file versions from git or backup.
2. Run `php artisan optimize:clear` and `php artisan config:cache`.
3. Restore database from dump if migrations ran.
4. Re-verify homepage and login.
