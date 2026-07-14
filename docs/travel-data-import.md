# Travel Data Import (Airports + Airlines + Logos)

This project supports one-time local import of global airport and airline reference data.

## Runtime policy

- Kaggle is used only to download raw datasets for local import.
- The live OTA does **not** call Kaggle at runtime.
- Kaggle credentials are not stored in Laravel config/env.
- Raw files remain under `storage/app/imports/` and are not served from `public/`.
- Airport autocomplete uses the local DB (`airports` table).

## Expected dataset locations

- `storage/app/imports/kaggle/airports-global/airports.csv`
- `storage/app/imports/kaggle/airports-global/airlines.csv`
- `storage/app/imports/kaggle/airports-global/routes.csv`
- `storage/app/imports/kaggle/airline-logos/` (optional logo assets)

## Import command

```bash
php artisan ota:import-airports-airlines --path="storage/app/imports/kaggle/airports-global"
```

With optional logo import:

```bash
php artisan ota:import-airports-airlines --path="storage/app/imports/kaggle/airports-global" --logos
```

Logos are copied to:

- `storage/app/public/travel-assets/airlines/logos/`

`logo_path` is stored in DB as:

- `travel-assets/airlines/logos/{filename}`

## Fallback seeder

For demo/dev when full import is unavailable:

```bash
php artisan db:seed --class=AirportAirlineReferenceSeeder
```

This seeds a small major-airports/major-airlines reference only.

## Source/license notes

- Global airports/airlines dataset: downloaded from Kaggle; license displayed as **"other"** at download time.
- Airline logos dataset: downloaded from Kaggle; license displayed as **CC0-1.0** at download time.

> Always verify airline logo trademark/licensing suitability before commercial/public deployment.

