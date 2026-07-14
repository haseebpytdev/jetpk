# Client deployment notes (template)

Copy this folder to `clients/{client-slug}/` and replace all `REPLACE_*` placeholders.

## JSON files are ops metadata

The JSON files in this folder are the **human-readable source of truth** for deployers.
They are **not** loaded automatically at runtime. Copy the relevant values into the
server `.env` (see `env.production.example` in this folder).

## Config separation

| File / config | Purpose |
|---------------|---------|
| `clients/{slug}/*.json` | Deployment metadata for ops |
| `config/ota_client.php` | Runtime deployment profile (reads `OTA_CLIENT_*` / `OTA_MODULE_*` env) |
| `config/ota-client.php` | Existing branding/contact fallbacks (unchanged) |

## Before first deploy

1. Fill in `client.json`, `branding.json`, `modules.json`, `deployment.json`.
2. Copy `env.production.example` → server `.env` (never commit real `.env`).
3. Upload client assets to `public/client-assets/{active_public_asset_profile}/`.
4. Follow `docs/new-client-deployment-checklist.md`.

## Safety

- Never blind-sync or overwrite entire `public_html`.
- Back up DB, `.env`, `storage/`, and client assets before deploy.
- Do not store SSH passwords or private keys in tracked JSON files.
