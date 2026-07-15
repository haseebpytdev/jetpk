# OTA pre-deploy no-500 rule

Mandatory gate before any Cursor implementation is considered complete or uploaded to live (`ota_app`).

## Required commands (local, before SFTP)

Run in order after code changes:

```bash
php -l <each changed PHP file>
php -l <each changed Blade file>   # syntax spot-check; also compile via view:cache below

composer dump-autoload -o            # when composer.json autoload or new PHP classes changed
php artisan optimize:clear
php artisan view:clear
php artisan view:cache               # when Blade changed — must compile without error
```

```bash
php artisan ota:route-page-health-audit --all --seed
php artisan ota:production-readiness-audit
php artisan ota:smoke-live-routes --guest-only
php artisan ota:ui-version-audit
```

### Pass criteria

| Command | Required result |
|---------|-----------------|
| `ota:route-page-health-audit --all` | `fail=0`, `server_errors=0` |
| `ota:production-readiness-audit` | `fail=0` |
| `ota:smoke-live-routes --guest-only` | `failed=0` |
| `ota:ui-version-audit` | `fail=0` |

### Hard no-upload blockers

Do **not** SFTP upload to live when any of:

- `ota:route-page-health-audit` → `fail > 0` or `server_errors > 0`
- `ota:production-readiness-audit` → `fail > 0`
- `ota:smoke-live-routes --guest-only` → `failed > 0`
- `ota:ui-version-audit` → `fail > 0`
- Mojibake grep finds hits outside intentional exclusions (below)
- **New** `production.ERROR` (or fatal/parse/view) in `storage/logs/laravel.log` after this pass

Authenticated admin/staff/booking-show checks must pass locally with `--all --seed` before upload.
On production SSH (no demo users), run at minimum `ota:route-page-health-audit --guest-only`.

## Mojibake grep

```bash
grep -RIn "|Ã|Â|â" app resources routes public/js public/css \
  --exclude-dir=vendor \
  --exclude-dir=node_modules \
  --exclude="RoutePageHealthAuditCatalog.php" \
  --exclude="display_helpers.php" || true
grep -RIn ": display_unknown()" app resources routes --exclude-dir=vendor || true
```

Expected: no matches in changed/rendered scope (intentional pattern catalog and
`display_helpers.php` U+FFFD stripper excluded).

Use display helpers for empty states:

- `display_unknown()` / `display_unknown($value)`
- `display_sep_dot()` / `display_sep_dash()`
- `clean_display_text()`

Never use bare `: display_unknown()` in Blade (invalid PHP — causes HTTP 500).

## Log check after manual page loads

```bash
tail -n 100 storage/logs/laravel.log | grep -E "production\.ERROR|SQLSTATE|Fatal|Parse error|ViewException" || true
```

Expected: no new errors for routes exercised in this pass.

## What `ota:route-page-health-audit` does

- **Read-only.** No supplier calls, booking creation (except optional local `--seed` fixture), ticketing, cancellation, or email.
- **Static scan** for `: display_unknown()` and mojibake in `app/`, `resources/`, `routes/`.
- **Safe GET dispatch** for critical public and authenticated pages (booking show, reports, api-settings, etc.).
- Skips routes when safe DB fixtures are missing (SKIPPED, not FAIL).

Scopes:

- `--guest-only` — public routes (safe on live)
- `--admin` — platform admin pages
- `--staff` — staff pages
- `--all` — full pre-deploy gate (use with `--seed` locally)

## Files not uploaded to live

- `tests/**`
- `docs/**` (unless ops explicitly requests)
- `.cursor/**`
