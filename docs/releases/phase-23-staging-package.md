# Phase 23 - Staging Deployment Package

## Release Classification

- Release type: `Staging Candidate`
- Project: `Asif Travels OTA`
- Target domain: `https://ota.haseebasif.com`
- Environment target: `staging`
- Date/time: `2026-05-07 12:42:02 +05:00`
- Branch: `main`
- Commit: `44b6361a9261099ba103d8c64f122c477beddddf`

## Verification Matrix (Local Freeze)

| Check | Command | Result |
| --- | --- | --- |
| Laravel tests | `php artisan test` with `E2E_FORCE_MOCK_SUPPLIER=false` | PASS (`481/481`) |
| Code style | `php vendor/bin/pint --test` | PASS |
| Asset build | `npm run build` | PASS |
| Production readiness | `php artisan ota:production-check` (`APP_ENV=staging`, `APP_DEBUG=false`) | PASS (all critical checks OK) |
| Playwright desktop | `npm run e2e:desktop` | PASS (`3/3`) |
| Playwright mobile | `npm run e2e:mobile` | PASS (`3/3`) |

## Known Limitations / Notes

- Local PHPUnit can fail in this repository when `E2E_FORCE_MOCK_SUPPLIER=true` is left enabled in shell/env; run tests with `E2E_FORCE_MOCK_SUPPLIER=false` for full suite stability.
- `ota:production-check` may print `Storage link exists` as a warning line in some environments even when `public/storage` is correctly present.
- Staging E2E against live Duffel sandbox may be more volatile than local deterministic mock mode; treat Playwright on staging as smoke confidence, not strict determinism.

## Packaging Checklist

### Include

- `app/`
- `bootstrap/`
- `config/`
- `database/` (migrations/seeders; no local sqlite payload unless explicitly intended)
- `public/` (including `public/build` if building off-server)
- `resources/`
- `routes/`
- `storage/` required runtime directories
- `docs/`
- `artisan`
- `composer.json`
- `composer.lock`
- `package.json`
- `package-lock.json`
- `vite.config.js`
- `postcss.config.js`
- `tailwind.config.js`
- `.env.production.example`

### Exclude

- `node_modules/`
- `test-results/`
- `playwright-report/`
- `.env` and any real secret files
- local logs (`storage/logs/*.log`)
- local sqlite (`database/database.sqlite`) unless intentionally used
- screenshots/videos/traces from local runs
- Kaggle credentials

## Deployment Commands (Server)

```bash
cd /path/to/ota
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
php artisan key:generate
# edit .env with real staging values (domain/db/mail)
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan ota:repair-legacy-data
php artisan ota:prepare-duffel-test --agency=asif-travels
php artisan ota:production-check
```

If `npm` cannot run on server:

1. Build on CI/local (`npm run build`)
2. Upload `public/build`
3. Do not upload `node_modules`

## Staging Admin Setup (Duffel)

1. Login to `/admin`
2. Open `Admin -> API Settings`
3. Configure Duffel sandbox:
   - Provider: `Duffel`
   - Environment: `Sandbox`
   - Status: `Active`
   - Base URL: `https://api.duffel.com`
   - Access Token: `duffel_test_...`
   - API Version: `v2`
4. Keep mock supplier disabled unless intentionally testing mock comparison.
5. Confirm token masking after save and no token leakage in source/logs.

## Rollback Notes

Before deploy:

- Backup DB
- Backup `.env`
- Backup `storage/app/private` (if documents exist)
- Keep previous release directory and prior `public/build`

Rollback:

1. Switch back to previous release/symlink
2. Restore DB backup only if migration/data issue requires it
3. Run:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
4. Verify `/` and `/login`

## Deployment Blockers Found

- None critical for staging packaging in local verification.
- Operational caution: ensure staging `.env` keeps `E2E_FORCE_MOCK_SUPPLIER=false`.
