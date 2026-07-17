# JetPK Homepage CMS — SSH Verification & Supported Commands

**Integration HEAD:** `824ab74`  
**App root:** `/home/pkjetp/jetpk_app`  
**Public root:** `/home/pkjetp/public_html`

## Supported production command signatures (verified locally)

| Command | Supported options |
|---------|-------------------|
| `jetpk:homepage-customization-coverage-audit` | `--json` only |
| `jetpk:canonical-business-email-audit` | **No options** (do not use `--profile`) |
| `jetpk:homepage-content-audit` | `--profile=jetpk` |
| `jetpk:homepage-media-audit` | `--profile=jetpk` |
| `ota:route-page-health-audit` | `--all`, `--guest-only`, `--admin`, `--staff`, `--seed` (local/testing only) |

## Run now (post-upload, pre-migration)

```bash
cd /home/pkjetp/jetpk_app

# 1. Backup
cp .env .env.backup-$(date -u +%Y%m%dT%H%M%SZ)
php artisan migrate:status > storage/logs/pre-cms-migrate-status-$(date -u +%Y%m%dT%H%M%SZ).txt

# 2. Runtime file existence (sample — repeat for all Class A files in EXACT_RUNTIME_MANIFEST)
test -f app/Support/Client/Homepage/HomepageContentNormalizer.php
test -f app/Services/Client/ClientPageResetService.php
test -f resources/views/themes/frontend/jetpakistan/frontend/home.blade.php

# 3. PHP syntax (all changed PHP runtime files)
find app config database/migrations routes resources/views -name '*.php' -newer /tmp/cms-deploy-marker 2>/dev/null | while read f; do php -l "$f" || exit 1; done

# 4. Public asset SHA parity (example)
sha256sum public/themes/frontend/jetpakistan/css/jp-search.css
sha256sum /home/pkjetp/public_html/themes/frontend/jetpakistan/css/jp-search.css
# must match

# 5. Public HTTP 200
curl -s -o /dev/null -w "%{http_code}" https://jetpakistan.pk/
curl -s -o /dev/null -w "%{http_code}" https://jetpakistan.pk/themes/frontend/jetpakistan/css/jp-search.css?v=36

# 6. Cache
php artisan optimize:clear
php artisan view:cache && php artisan view:clear

# 7. Audits (production env must have mail.from.address=ota@jetpakistan.pk)
php artisan jetpk:homepage-customization-coverage-audit
php artisan jetpk:canonical-business-email-audit
php artisan jetpk:homepage-content-audit --profile=jetpk
php artisan jetpk:homepage-media-audit --profile=jetpk
php artisan ota:route-page-health-audit --all
# Required: customization fail=0, email fail_count=0, content fail_count=0, media fail_count=0, route fail=0

# 8. Homepage content preservation (manual spot-check)
# hero, 4 routes, 4 destinations, groups.enabled=1, phone 0311 1222427, email ota@jetpakistan.pk

# 9. Mobile/desktop render
curl -s -H "User-Agent: Mozilla/5.0 (iPhone)" https://jetpakistan.pk/ | head -c 2000
curl -s https://jetpakistan.pk/ | grep -E 'data-hero-search|Featured'

# 10. Post-migration sanity (after approval block below)
# SELECT COUNT(*) FROM client_page_setting_defaults;  -- expect 0 unless admin saved
# SELECT COUNT(*) FROM client_page_setting_revisions; -- expect 0 unless publish/reset ran
```

## RUN ONLY AFTER EXPLICIT MIGRATION APPROVAL

```bash
cd /home/pkjetp/jetpk_app
php artisan migrate --path=database/migrations/2026_07_16_120000_create_client_page_setting_revisions_table.php --force
php artisan migrate --path=database/migrations/2026_07_16_130000_create_client_page_setting_defaults_table.php --force
php artisan migrate:status | grep client_page_setting
```

## Rollback

```bash
cd /home/pkjetp/jetpk_app
# Code rollback to 624f3dd deployment artifact
php artisan migrate:rollback --path=database/migrations/2026_07_16_130000_create_client_page_setting_defaults_table.php --force
php artisan migrate:rollback --path=database/migrations/2026_07_16_120000_create_client_page_setting_revisions_table.php --force
php artisan optimize:clear
# Retain CMS restore backup 20260716T185657Z — invoke only if content damaged
```

## Migration up/down (local evidence)

- `migrate:fresh --env=testing` — OK
- Rollback defaults → OK
- Rollback revisions → OK
- Re-apply both → OK
- No duplicate migration timestamps on `624f3dd` main

## Content neutrality

`HomepageCmsContentNeutralityTest` — **PASS**: migrate/boot-only preserves published/draft hashes; defaults=0; revisions=0; no HTTP/supplier/email side effects.

## SFTP

See `docs/JETPK_HOMEPAGE_CMS_SFTP_COMMANDS.txt` (39 runtime files, explicit `put` each, no `bye`).
