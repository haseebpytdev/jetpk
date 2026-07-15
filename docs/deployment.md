# Deployment Guide (Staging/Production)

This phase prepares the OTA for safe staging/live deployments while keeping supplier booking, ticketing, and payments in mock/readiness mode.

## Server requirements

- PHP `8.3+`
- MySQL `8+` (or compatible)
- Composer `2+`
- Node.js `20+` and npm for asset builds
- Web server: Nginx or Apache
- Process manager for queue workers: Supervisor or systemd

## Required PHP extensions

- `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`

## First-time deployment steps

1. Clone and install:
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci && npm run build`
2. Environment:
   - `cp .env.production.example .env`
   - Set `APP_URL`, database, mail, and OTA values.
3. App key:
   - `php artisan key:generate`
4. Database:
   - `php artisan migrate --force`
   - Seed only when explicitly needed for a staging/demo dataset.
5. Storage:
   - `php artisan storage:link`
6. Cache warm-up:
   - `php artisan optimize`
7. Verification:
   - `php artisan route:list`
   - `php artisan ota:storage-check`
   - `php artisan ota:backup-check`

## Queue worker setup

- Run worker:
  - `php artisan queue:work --tries=3 --timeout=120`
- Restart after deploy:
  - `php artisan queue:restart`

Supervisor example:

```ini
[program:ota-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ota/artisan queue:work --tries=3 --timeout=120 --sleep=3
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/ota/storage/logs/queue-worker.log
stopwaitsecs=3600
```

systemd example command:

`/usr/bin/php /var/www/ota/artisan queue:work --tries=3 --timeout=120 --sleep=3`

## Scheduler setup

Add cron (every minute):

`* * * * * cd /var/www/ota && php artisan schedule:run >> /dev/null 2>&1`

Current scheduled tasks (full list and SMTP/queue checklist): see [production-cron-smtp-notifications.md](production-cron-smtp-notifications.md).

- `ota:cleanup-expired-access` (hourly)
- `ota:send-daily-report`, `ota:send-weekly-report`, `ota:send-monthly-report`, `ota:send-monthly-ledgers`
- `homepage:refresh-featured-fares` (daily)

## Storage model

- Public files (branding/media) use disk `public` and require `storage:link`.
- Booking documents are written under private storage (`storage/app/private/...`) and are policy-gated.
- PDF temp path must be writable (`OTA_PDF_TEMP_DIRECTORY`).

## Security and permissions

- Web root must point to `public/`.
- Ensure writable paths:
  - `storage/`
  - `bootstrap/cache/`
- Typical ownership:
  - `chown -R www-data:www-data storage bootstrap/cache`

## Config/cache commands

- Build caches during deploy:
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`
- Clear during troubleshooting:
  - `php artisan optimize:clear`

No route closures should remain if route caching is desired.

## Testing and validation commands

- `php artisan test`
- `vendor/bin/pint --test`

## Backup readiness

Run checklist command:

- `php artisan ota:backup-check`

Recommended baseline:

- Database backup via `mysqldump` (daily + retention policy)
- Storage backup for:
  - `storage/app/private`
  - `storage/app/public` (if media uploads are critical)

## Rollback notes

- Keep previous release directory available.
- On rollback:
  1. Switch symlink/web root back to previous release.
  2. Run `php artisan optimize`.
  3. Run `php artisan queue:restart`.
- For DB rollbacks, prefer forward-fix migrations over destructive down migrations on production data.

## Hostinger / shared hosting notes

- `public/` path mapping may require copying or symlinking contents depending on host panel constraints.
- Long-running `queue:work` may not be supported; if so, prefer:
  - database queue with short cron-driven `queue:work --stop-when-empty`, or
  - keep communication/document workflows sync-compatible for low-volume setups.
- Cron setup is usually available from control panel; configure scheduler there.
- `storage:link` may be restricted on some plans; if symlinks are blocked, serve media through an alternate configured path and keep private documents outside public web root.
