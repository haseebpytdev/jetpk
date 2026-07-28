# One API configuration

Connection fields are defined in `config/supplier_credentials.php` under `one_api`.

Secrets (`username`, `password`) use `SupplierConnection` encrypted credentials.

Operational flags (`live_*_enabled`, `on_hold_enabled`, carrier allowlists) are stored on the connection credentials array and parsed by `OneApiConfigResolver`.

Readiness: `php artisan ota:one-api-connection-audit --connection=<id>` (no network unless `--live`).
