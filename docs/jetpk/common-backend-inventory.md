# JetPK common backend inventory

Files and directories required on a **JetPK standalone server** in addition to JetPK UI assets. This is the shared OTA Laravel application — not client-branded UI.

## Application core

| Path | Required |
|------|----------|
| `app/` | Yes — full tree (services, controllers, models, enums, data) |
| `bootstrap/` | Yes |
| `config/` | Yes — including `ota_client.php`, `client_themes.php`, `client_view_paths.php` |
| `database/migrations/` | Yes |
| `database/seeders/` | Yes (selective run on deploy) |
| `routes/` | Yes |
| `resources/views/` | **Partial** — see below |
| `public/index.php` | Yes |
| `storage/` | Writable on server |
| `vendor/` | `composer install --no-dev` on server |
| `composer.json`, `composer.lock` | Yes |

## Shared views (required)

JetPK theme overrides only a subset; these shared views are still loaded:

| Area | Path pattern |
|------|--------------|
| Auth forms | `resources/views/auth/*.blade.php` |
| Flight/booking (until themed) | `resources/views/frontend/flights/*`, `frontend/booking/*` |
| Groups | `resources/views/frontend/groups/*`, `frontend/group-ticketing/*` |
| Agent registration | `resources/views/frontend/agent-registration/*` |
| CMS | `resources/views/frontend/pages/*` |
| Shared components | `resources/views/components/ota-*`, `components/hero-*`, etc. |
| Master layouts (fallback) | `resources/views/layouts/*` when theme missing |
| Email templates | `resources/views/emails/*`, `mail/*` |
| Admin/agent/staff/customer | Full dashboard view trees until JetPK dashboard phase |

## Shared public assets (required today)

| Path | Notes |
|------|-------|
| `public/css/ota-public.css` | Used by legacy views inside JP shell |
| `public/js/*` | Platform JS for search, booking, etc. |
| `public/css/v2/` | Only if v2 preview enabled |
| `public/images/`, `public/fonts/` | Shared icons/assets |
| `public/build/` | If Vite build used |

JetPK theme CSS/JS is separate under `public/themes/frontend/jetpakistan/`.

## Services critical to JetPK flights/booking

| Service cluster | Path |
|-----------------|------|
| Flight search | `app/Services/Flights/*`, `app/Data/Flight*` |
| Booking | `app/Services/Bookings/*` |
| Suppliers | `app/Services/Suppliers/*` |
| Client runtime | `app/Services/Client/*` |
| Platform modules | `app/Support/Platform/*` |
| Payments | `app/Services/Payments/*` |

## Config / env

Runtime reads `config/ota_client.php` from `.env` — see [env-checklist.md](env-checklist.md).

## Optional on dedicated JetPK server

| Item | When to omit |
|------|--------------|
| `clients/haseeb-master/` | Other client ops folders |
| `public/client-assets/{other}/` | Other client branding |
| `resources/views/themes/frontend/v1-classic/` | If all public pages have JP overrides |
| Preview parity | Set `CLIENT_ROUTE_PARITY_ENABLED=false` on production JetPK domain |
| Dev CP | `OTA_MODULE_DEV_CP=false`, `OTA_DEVELOPER_CP_ENABLED=false` |

## Verification

```bash
php artisan about
php artisan route:list --path=jetpk
php artisan ota:client-preview-runtime-status --client=jetpk
php artisan ota:route-page-health-audit --all
```
