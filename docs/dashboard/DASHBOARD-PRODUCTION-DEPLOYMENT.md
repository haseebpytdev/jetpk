# Dashboard Production Deployment — DASH-13

## Server paths

| Purpose | Path |
|---------|------|
| Laravel app | `/home/pkjetp/jetpk_app` |
| Public webroot | `/home/pkjetp/public_html` |
| Dashboard app | `/home/pkjetp/jetpk_app/dashboard` |
| Optional static HTML | `/home/pkjetp/jetpk_app/storage/app/back-office-dashboard/{admin\|staff}/dashboard` |

## Pre-deploy local build

```bash
cd dashboard
npm ci
npm run typecheck
npm run lint
npm run build:production
```

## Server environment (`.env` on jetpk_app)

```env
DASHBOARD_NEXT_SERVER_URL=http://127.0.0.1:3001
DASHBOARD_NEXT_PROXY_ENABLED=true
```

Run Next.js on the server (PM2 or equivalent):

```bash
cd /home/pkjetp/jetpk_app/dashboard
npm ci
npm run build
npm run start
```

Laravel proxies authenticated `/admin/dashboard/*` and `/staff/dashboard/*` to this process after middleware passes.

## Post-upload server commands

```bash
cd /home/pkjetp/jetpk_app
/opt/alt/php-fpm83/usr/bin/php artisan down
/opt/alt/php-fpm83/usr/bin/php artisan route:clear
/opt/alt/php-fpm83/usr/bin/php artisan config:clear
/opt/alt/php-fpm83/usr/bin/php artisan cache:clear
/opt/alt/php-fpm83/usr/bin/php artisan view:clear
/opt/alt/php-fpm83/usr/bin/php artisan optimize
/opt/alt/php-fpm83/usr/bin/php artisan route:list | grep -E "admin/dashboard|staff/dashboard|api/dashboard|testdash"
/opt/alt/php-fpm83/usr/bin/php artisan up
```

## Verification

- `/dashboard` role redirects
- `/admin/dashboard`, `/staff/dashboard` authenticated
- `/testdash` redirects (not previewable)
- `/api/dashboard/session` 401 unauthenticated
- Public site smoke (homepage, login, flights search)
- Agent/Customer dashboards unchanged

See `docs/dashboard/DASHBOARD-ROLLBACK.md` for rollback.
