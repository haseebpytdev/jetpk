# Rollback — JetPakistan Canonical Responsive UI + CMS Correction

Baseline SHA:
5f97dc6c512d59ac2106c2bfa9854da2dc1210c8

Merge commit:
1a64a0ce017cc77400c90025adadd6d1a8e79f65

Runtime source SHA:
a9bc7826f4beb14deeaef29e17bbf4bfd195a737

Runbook content SHA:
725d6a470393ac45b4dfbbff6ca65a06c90214e5

Runbook metadata stamp SHA:
35ff22098ced63507c78dfa03b5c0ce32a8fd52b

Use this rollback only when the pre-deployment backup from the SSH runbook completed successfully.

## What rollback restores

- Extracting the archive restores the pre-deployment `jetpk_app` and `public_html` trees.
- Deleted legacy mobile files are recreated from the archive.
- Overwritten runtime files return to their pre-deployment versions.
- `.env` is restored separately from its dedicated backup.
- `vendor` remains unchanged because Composer dependencies are unchanged.
- No database rollback is required.
- Do not run CMS reset, default restore, or publish during rollback.

## Rollback commands

```bash
BACKUP_DIR="/home/pkjetp/deploy_backups"

ARCHIVE=$(cat "${BACKUP_DIR}/JETPK_CANONICAL_UI_LATEST_BACKUP.txt")
ENV_BACKUP=$(cat "${BACKUP_DIR}/JETPK_CANONICAL_UI_LATEST_ENV_BACKUP.txt")
METADATA=$(cat "${BACKUP_DIR}/JETPK_CANONICAL_UI_LATEST_METADATA.txt")

test -s "$ARCHIVE"
test -s "$ENV_BACKUP"
test -s "$METADATA"
test -s "${ARCHIVE}.sha256"

sha256sum -c "${ARCHIVE}.sha256"

cd /home/pkjetp/jetpk_app
php artisan down --retry=60

cd /home/pkjetp
tar -xzf "$ARCHIVE"

cp -a "$ENV_BACKUP" /home/pkjetp/jetpk_app/.env

cd /home/pkjetp/jetpk_app

php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan view:cache
php artisan view:clear

php artisan up

curl -s -o /dev/null -w "home=%{http_code}\n" \
https://jetpakistan.pk/

curl -s -o /dev/null -w "login=%{http_code}\n" \
https://jetpakistan.pk/login

php artisan route:list
php artisan jetpk:canonical-business-email-audit
php artisan ota:route-page-health-audit --all
```

## Emergency recovery

If an Artisan command fails before `php artisan up`, still attempt:

```bash
cd /home/pkjetp/jetpk_app
php artisan up
```

after restoring files and `.env`.