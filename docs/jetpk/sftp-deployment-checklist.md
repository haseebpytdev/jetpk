# JetPK SFTP deployment checklist

Follow [docs/PRODUCTION_DEPLOYMENT_SAFETY.md](../PRODUCTION_DEPLOYMENT_SAFETY.md) and sprint workflow: **changed files only**, no blind folder sync.

## Pre-deploy gate (mandatory)

```bash
php artisan ota:route-page-health-audit --all   # fail=0 required
php artisan test --filter=ClientPreviewRoutingTest
php artisan test --filter=ClientAssetResolverTest
```

No upload when `fail>0`, server errors, or new `production.ERROR` in logs.

## Files to upload (typical JetPK UI sprint)

### Blade / PHP

| Change type | Paths |
|-------------|-------|
| Theme views | `resources/views/themes/frontend/jetpakistan/**` |
| Components | `resources/views/components/jp/**` |
| Controllers (if `client_view()` added) | Specific controller files only |
| Config | `config/client_themes.php` (if registry change) |

### Public assets — dual deploy

Upload to **both** Laravel app `public/` and live web root when split:

| Profile | Paths |
|---------|-------|
| OTA App | `public/themes/frontend/jetpakistan/**` |
| OTA Public Web Root | `themes/frontend/jetpakistan/**` on public_html |
| Client branding | `public/client-assets/jetpk-assets/**` → web root `client-assets/jetpk-assets/` |

Bump `$jpAssetVersion` in `layouts/frontend.blade.php` when theme CSS/JS changes.

## Server SSH (after upload)

```bash
cd /path/to/laravel
php artisan optimize:clear
php artisan view:clear
php artisan config:cache
```

If migrations included:

```bash
php artisan migrate --force
```

## Post-deploy smoke

- [ ] Homepage `/` or `/jetpk/home`
- [ ] About, support, lookup
- [ ] Login shell
- [ ] Flight search → results (no 500)
- [ ] Logo/favicon load
- [ ] Tail `storage/logs/laravel.log` — no new errors

## Master workspace vs JetPK production

| Environment | Root URL | Notes |
|-------------|----------|-------|
| Master QA | `/jetpk/home` | Preview context |
| JetPK live | `/` | `OTA_CLIENT_SLUG=jetpk` |

## Deployment metadata

Update `clients/jetpk/deployment.json`:

- `last_deployed_at`
- `last_deployed_by`

## Do not

- Blind-sync entire `public_html`
- Overwrite `.env` without backup
- Upload `clients/*/env` with secrets
- Skip dual deploy for theme CSS/JS

See [rollback-checklist.md](rollback-checklist.md) if smoke test fails.
