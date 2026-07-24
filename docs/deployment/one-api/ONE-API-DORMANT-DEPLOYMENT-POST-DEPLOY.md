# ONE API Dormant Deployment — Post-Deploy Verification

Run on the JetPakistan test server. **No live supplier probes.**

```bash
set -euo pipefail
cd "/home/u654883295/domains/haseebasif.com/ota_app"

# PHP / Laravel
php -v
php artisan about --no-ansi
php artisan package:discover --ansi

# Cache clears (post-upload)
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Route + command registration
php artisan route:list --name=one-api --no-ansi
php artisan list --no-ansi | grep one-api

# Configuration-only readiness (no --live)
# Requires an existing One API supplier_connection row ID when testing admin audit output.
php artisan ota:one-api-audit --connection=<ID> || true
php artisan ota:one-api-connection-audit --connection=<ID> || true

# Existing suppliers still registered
php artisan route:list --no-ansi | grep -E 'sabre|iati|pia' || true

# Public JS mirror
ls -la "/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/js/ota-one-api-checkout.js"

# Permissions spot-check
find app/Services/Suppliers/OneApi -type f -name '*.php' -exec ls -la {} \;

# Logs — prove no supplier traffic from deployment itself
tail -n 50 storage/logs/laravel.log
tail -n 50 storage/logs/one-api.log 2>/dev/null || echo 'one-api.log not yet created (expected if dormant)'

# Prove env live gates default off (when unset)
php artisan tinker --execute="var_export(config('suppliers.one_api.live_search_enabled')); var_export(config('suppliers.one_api.live_booking_enabled')); var_export(config('suppliers.one_api.live_payment_modification_enabled'));"
```

## Dormant expectations

- `one_api_supplier` platform module remains **false**.
- No active `one_api` supplier connection required for boot.
- `ONE_API_LIVE_SEARCH_ENABLED`, `ONE_API_LIVE_BOOKING_ENABLED`, `ONE_API_LIVE_PAYMENT_MODIFICATION_ENABLED` unset or `false`.
- `one-api.log` should not show search/book/modify entries unless an operator runs a gated probe with explicit flags.

## Maintenance mode

Not required for this dormant code-only deployment (no migrations, no composer changes, no queue worker restarts mandated).
