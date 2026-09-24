#!/usr/bin/env bash
# JetPK dedicated server — post-deploy verification (read-only audits)
# Replace <jetpk_user> before running.

set -euo pipefail

cd /home/<jetpk_user>/domains/jetpakistan.com/ota_app

php artisan ota:jetpk-dedicated-package-audit --client=jetpk
php artisan ota:jetpk-dedicated-server-readiness --client=jetpk
php artisan ota:client-preview-runtime-status --client=jetpk
php artisan ota:client-context-flow-audit --client=jetpk
php artisan ota:jetpk-theme-isolation-audit --client=jetpk
php artisan ota:route-page-health-audit --all

date '+SERVER_MARKER %Y-%m-%d %H:%M:%S'
grep -n "production.ERROR" storage/logs/laravel.log | tail -n 40 || true

echo "Automated checks complete. Complete browser checklist in README_JETPK_DEPLOYMENT.md"
