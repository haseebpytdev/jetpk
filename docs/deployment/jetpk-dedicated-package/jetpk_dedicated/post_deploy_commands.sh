#!/usr/bin/env bash
# JetPK dedicated server — post-deploy setup (safe commands only)
# Replace <jetpk_user> before running. Run on dedicated server after SFTP upload.

set -euo pipefail

cd /home/<jetpk_user>/domains/jetpakistan.com/ota_app

composer install --no-dev --optimize-autoloader

# Generate APP_KEY only on first deploy (skip if .env already has APP_KEY)
php artisan key:generate --force

php artisan storage:link

# Run migrations only when approved for this environment
php artisan migrate --force

php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Post-deploy setup complete. Run post_deploy_checks.sh next."
