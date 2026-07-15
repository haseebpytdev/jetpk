<<<<<<< HEAD
# JetPK local fork — setup notes (JETPK-SINGLE-CLIENT-FORK-ROOT-MODE-9A)
=======
﻿# JetPK local fork â€” setup notes (JETPK-SINGLE-CLIENT-FORK-ROOT-MODE-9A)
>>>>>>> jetpk/main

## Paths

- OTA master (unchanged): `C:\Users\khadi\ota`
- JetPK fork: `C:\Users\khadi\ota-jetpk`

## Database

- OTA master: `C:\Users\khadi\ota\database\database.sqlite` (SQLite, unchanged)
- JetPK copy: `C:\Users\khadi\ota-jetpk\database\database.sqlite` (separate file)

## Local URLs

<<<<<<< HEAD
### Option A — Laragon / hosts (preferred)
=======
### Option A â€” Laragon / hosts (preferred)
>>>>>>> jetpk/main

1. Add to `C:\Windows\System32\drivers\etc\hosts`:
   ```
   127.0.0.1 jetpk.test
   ```
2. Point vhost document root to:
   ```
   C:\Users\khadi\ota-jetpk\public
   ```
3. Open: http://jetpk.test

<<<<<<< HEAD
### Option B — Artisan serve (fallback)
=======
### Option B â€” Artisan serve (fallback)
>>>>>>> jetpk/main

```powershell
cd C:\Users\khadi\ota-jetpk
php artisan serve --host=127.0.0.1 --port=8091
```

Open: http://127.0.0.1:8091

## Post-copy commands (JetPK folder only)

```powershell
cd C:\Users\khadi\ota-jetpk
composer install
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan optimize:clear
```

## Demo credentials (from OtaFoundationSeeder)

<<<<<<< HEAD
=======
> Local development accounts only. These credentials are seeded for local testing and must never be used in production.
>>>>>>> jetpk/main
| Role | Email | Password |
|------|-------|----------|
| Admin | admin@ota.demo | password |
| Staff | staff@ota.demo | password |
| Agent | agent@ota.demo | password |
| Customer | customer@ota.demo | password |

## JetPK root-mode env (already set in `.env`)

```
OTA_SINGLE_CLIENT_MODE=true
OTA_SINGLE_CLIENT_ROOT=true
OTA_CLIENT_SLUG=jetpk
OTA_DEFAULT_CLIENT=jetpk
OTA_MASTER_CLIENT_SLUG=jetpk
CLIENT_ROUTE_PARITY_ENABLED=false
APP_URL=http://jetpk.test
```
<<<<<<< HEAD
=======

>>>>>>> jetpk/main
