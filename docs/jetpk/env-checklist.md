# JetPK production environment checklist

Copy from `clients/jetpk/env.production.example`. Never commit real `.env`.

## Core application

| Variable | JetPK value | Required |
|----------|-------------|----------|
| `APP_NAME` | `JetPakistan` | Yes |
| `APP_ENV` | `production` | Yes |
| `APP_KEY` | Generate on server | Yes |
| `APP_DEBUG` | `false` | Yes |
| `APP_URL` | `https://www.jetpakistan.com` | Yes |
| `APP_LOCALE` | `en` | Yes |

## Database & session

| Variable | Notes |
|----------|-------|
| `DB_*` | MySQL credentials for JetPK server |
| `SESSION_DRIVER` | `database` recommended |
| `CACHE_STORE` | `database` or `redis` |
| `QUEUE_CONNECTION` | `database` for async jobs |

## Mail

| Variable | Notes |
|----------|-------|
| `MAIL_*` | JetPakistan SMTP; `MAIL_FROM_NAME=JetPakistan` |
| `MAIL_FROM_ADDRESS` | e.g. `ticketingjp@jetpakistan.com` |

## OTA client deployment

| Variable | JetPK value |
|----------|-------------|
| `OTA_CLIENT_SLUG` | `jetpk` |
| `OTA_ACTIVE_THEME` | `jetpakistan` |
| `OTA_PUBLIC_ASSET_PROFILE` | `jetpk-assets` |
| `OTA_DEFAULT_AGENCY_SLUG` | `jetpk` (or linked agency) |
| `OTA_PUBLIC_WEBROOT_PATH` | Set if Laravel app path ≠ public_html |

## Client modules (`clients/jetpk/modules.json` → env)

| Variable | Recommended JetPK v1 |
|----------|---------------------|
| `OTA_MODULE_SABRE` | `false` until GDS approved |
| `OTA_MODULE_AL_HAIDER_GROUP_TICKETING` | `true` when groups live |
| `OTA_MODULE_ACCOUNTING` | `false` initially |
| `OTA_MODULE_HOTELS` | `false` |
| `OTA_MODULE_VISA` | `false` |
| `OTA_MODULE_PAYMENT_GATEWAY` | `true` |
| `OTA_MODULE_DEV_CP` | `false` on client production |
| `OTA_MODULE_STAFF_PANEL` | `true` |
| `OTA_MODULE_ADMIN_PANEL` | `true` |

## Platform control

| Variable | JetPK production |
|----------|------------------|
| `OTA_DEVELOPER_CP_ENABLED` | `false` |
| `CLIENT_ROUTE_PARITY_ENABLED` | `false` on dedicated domain (optional) |
| `CLIENT_UI_DEFAULT_VERSION` | `v1` |

## Suppliers (Admin → API Settings)

Configure on server after deploy — not in git:

- Duffel / Sabre credentials per enabled modules
- `OTA_SUPPLIER_DEFAULT_PROVIDER`
- `SABRE_*` flags — keep disabled until cert complete

## Security

| Variable | Notes |
|----------|-------|
| `TURNSTILE_*` | Enable for public forms if required |
| `APP_DEBUG` | Must be `false` |

## Post-config

```bash
php artisan config:cache
php artisan route:cache   # optional
php artisan view:cache    # optional
```

Verify:

```bash
php artisan about
php artisan ota:client-preview-runtime-status --client=jetpk
```
