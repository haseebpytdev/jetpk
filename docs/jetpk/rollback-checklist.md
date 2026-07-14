# JetPK rollback checklist

Use when a deploy causes 500s, broken search, or branding regression.

## 1. Stop further uploads

Do not upload additional files until root cause is identified.

## 2. Restore files

| Source | Action |
|--------|--------|
| Git | Checkout prior version of changed files |
| SFTP backup | Restore file-level backups from pre-deploy |
| `deployment.json` | Note `last_deployed_at` for rollback target |

Priority restore order:

1. Controllers / routes (if broken)
2. Layout `frontend.blade.php` / `auth.blade.php`
3. Theme CSS/JS
4. Client assets

## 3. Server cache clear

```bash
cd /path/to/laravel
php artisan optimize:clear
php artisan view:clear
php artisan config:cache
```

## 4. Database rollback (if migrations ran)

- Restore DB dump from pre-deploy backup
- Or run `php artisan migrate:rollback` **only** if migration was isolated and safe

Profile seed is idempotent — re-run only if profile data corrupted:

```bash
php artisan ota:seed-jetpakistan-client-profile
```

## 5. Asset version

If CSS/JS rollback: restore prior theme files **and** prior `$jpAssetVersion` in layout.

## 6. Verification

- [ ] Homepage loads
- [ ] Login loads
- [ ] No new errors in `storage/logs/laravel.log`
- [ ] `php artisan ota:route-page-health-audit --all` → `fail=0`

## 7. Document

- Record rollback commit/tag in `clients/jetpk/notes.md`
- Reset `deployment.json` timestamps if redeploying fixed version

## Master workspace preview

If `/jetpk/*` broken but root `/` fine:

- Rollback JetPK theme files only
- Master production unaffected (principle: Master remains live testing platform)

## Emergency disable

If preview parity causes issues on master:

```env
CLIENT_ROUTE_PARITY_ENABLED=false
```

Then `php artisan optimize:clear`. Root routes unchanged; `/jetpk/*` mirrors disabled.
