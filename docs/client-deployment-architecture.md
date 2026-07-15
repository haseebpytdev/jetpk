# Client deployment architecture

Reusable Laravel OTA codebase with per-client deployment metadata. This document
describes the **prep-only** multi-client structure — it does not change live
behavior until env vars are set and future sprints wire `ClientProfile` into
gates or views.

## Goals

- One shared codebase (`app/`, `resources/`, `routes/`, etc.)
- Per-client ops metadata under `clients/{slug}/`
- Per-client public assets under `public/client-assets/{profile}/`
- Shared platform CSS/JS remains in `public/css/` and `public/js/` (unchanged)
- Safe defaults so the current deployment keeps working without new env vars

## Config separation (important)

| Config key | File | Purpose |
|------------|------|---------|
| `config('ota-client')` | `config/ota-client.php` | **Existing** branding/contact fallbacks (agency name, colors, support email) |
| `config('ota_client')` | `config/ota_client.php` | **New** deployment profile (slug, theme, asset profile, module flags) |

Do not merge these files. They serve different layers.

## Folder layout

```
clients/
  _template/          # Copy to create a new client
  client-demo/        # Example client (Sabre off, placeholders only)

public/
  css/                # Shared platform styles (unchanged)
  js/                 # Shared platform scripts (unchanged)
  themes/
    frontend/
      v1-classic/     # Future per-theme overrides
      v2-modern/
    admin/
    staff/
  client-assets/
    _template/
    client-demo/
      logo/
      banners/
      favicon/
      uploads/
```

## Create a new client

1. Copy `clients/_template/` → `clients/{client-slug}/`.
2. Edit JSON files (replace all `REPLACE_*` placeholders):
   - **`client.json`** — identity, domain, theme, locale, timezone, currency
   - **`branding.json`** — logo/favicon paths (relative to asset profile), colors, contact
   - **`modules.json`** — which modules this deployment should include
   - **`deployment.json`** — hosting panel, SSH host, paths (no real secrets in git)
3. Copy `env.production.example` → server `.env` and fill secrets (DB, mail, API keys).
4. Add assets under `public/client-assets/{active_public_asset_profile}/`.
5. Follow [new-client-deployment-checklist.md](new-client-deployment-checklist.md).

## JSON field reference

### client.json

| Field | Description |
|-------|-------------|
| `client_name` | Display name |
| `client_slug` | URL-safe identifier; maps to `OTA_CLIENT_SLUG` |
| `domain` | Production hostname |
| `environment` | `production`, `staging`, etc. |
| `active_theme` | Deployment theme id (`v1-classic`, `v2-modern`) |
| `active_public_asset_profile` | Folder under `public/client-assets/` |
| `default_locale` | e.g. `en` |
| `timezone` | e.g. `Asia/Karachi` |
| `currency` | e.g. `PKR` |

### branding.json

Paths are relative to `public/client-assets/{active_public_asset_profile}/`.

| Field | Description |
|-------|-------------|
| `logo_path` | e.g. `logo/logo.svg` |
| `favicon_path` | e.g. `favicon/favicon.ico` |
| `primary_color`, `secondary_color`, `accent_color` | Hex colors |
| `company_name`, `phone`, `email`, `address`, `footer_text` | Public contact/branding |

### modules.json

Deployment intent flags (copied to `OTA_MODULE_*` env vars):

| Key | Env var |
|-----|---------|
| `sabre` | `OTA_MODULE_SABRE` |
| `al_haider_group_ticketing` | `OTA_MODULE_AL_HAIDER_GROUP_TICKETING` |
| `accounting` | `OTA_MODULE_ACCOUNTING` |
| `hotels` | `OTA_MODULE_HOTELS` |
| `visa` | `OTA_MODULE_VISA` |
| `payment_gateway` | `OTA_MODULE_PAYMENT_GATEWAY` |
| `dev_cp` | `OTA_MODULE_DEV_CP` |
| `staff_panel` | `OTA_MODULE_STAFF_PANEL` |
| `admin_panel` | `OTA_MODULE_ADMIN_PANEL` |

**Note:** These are deployment-level flags read by `ClientProfile::moduleEnabled()`.
They are separate from:

- `PlatformModuleGate` / DB module overrides (runtime nav/route gating)
- Sabre live switches (`SABRE_BOOKING_ENABLED`, `SABRE_TICKETING_ENABLED`, etc.)
- Al-Haider API flags (`ALHAIDER_API_ENABLED`, etc.)

Future sprints may bridge deployment flags to runtime gates; this sprint does not.

### deployment.json

Ops metadata only — **never commit SSH passwords or private keys**.

| Field | Description |
|-------|-------------|
| `hosting_panel` | `hostinger`, `cwp`, `cpanel`, etc. |
| `ssh_host`, `ssh_port`, `ssh_user`, `auth_type` | SSH connection (password or key) |
| `app_path` | Laravel root on server |
| `public_html_path` | Web document root |
| `public_assets_path` | Client-specific public assets on server |
| `backup_path` | Remote backup directory |
| `deploy_strategy` | e.g. `sftp_single_file` |
| `last_deployed_at`, `last_deployed_by` | Audit fields |

## Set .env values

Copy from `clients/{slug}/env.production.example`. Minimum client block:

```env
OTA_CLIENT_SLUG=client-demo
OTA_ACTIVE_THEME=v1-classic
OTA_PUBLIC_ASSET_PROFILE=client-demo
OTA_MODULE_SABRE=false
OTA_MODULE_AL_HAIDER_GROUP_TICKETING=false
OTA_MODULE_ACCOUNTING=false
OTA_MODULE_HOTELS=false
OTA_MODULE_VISA=false
OTA_MODULE_PAYMENT_GATEWAY=true
OTA_MODULE_DEV_CP=false
OTA_MODULE_STAFF_PANEL=true
OTA_MODULE_ADMIN_PANEL=true
```

When **omitted** on the current live server, `config/ota_client.php` defaults all
module flags to `true` so existing behavior is preserved.

## Public assets

| Location | Contents |
|----------|----------|
| `public/css/`, `public/js/` | Shared OTA platform assets (all clients) |
| `public/client-assets/{profile}/` | Client logos, banners, favicons, uploads |
| `public/themes/` | Future per-theme CSS/JS overrides |

Resolve client asset URLs in code (future):

```php
asset(ClientProfile::assetPath('logo/logo.svg'));
// → /client-assets/client-demo/logo/logo.svg
```

When `OTA_PUBLIC_ASSET_PROFILE` is unset, `assetPath()` returns the path unchanged
(preserves existing `asset('css/ota-public.css')` behavior).

## Theme naming

| Deployment (`active_theme`) | UI channel (`config/ota-ui.php`) |
|----------------------------|----------------------------------|
| `v1-classic` | `v1` (canonical Blade paths) |
| `v2-modern` | `v2` (overlay views under `ui/{channel}/v2/`) |

No automatic bridge in this sprint. Set `OTA_UI_SITE_DEFAULT` separately if needed.

## Deploy to different servers

### Hostinger

Typical paths:

- App: `/home/{user}/domains/{domain}/laravel` or similar
- Public: `/home/{user}/domains/{domain}/public_html`

Upload app code via **OTA App - Laravel** SFTP profile (single files).
Upload `public/client-assets/{slug}/` via **OTA Public - Live Web Root** profile.

### CWP (CentOS Web Panel)

- App may live outside `public_html`; only `public/` contents are symlinked or copied to the vhost root.
- Record exact paths in `deployment.json` before first deploy.

### cPanel

- Document root is usually `public_html/`.
- Laravel app often one level above; `public/` mapped via subdomain or folder redirect.

**Safety rule:** Never blindly overwrite or sync the entire `public_html` directory.
Upload only the files you changed. Wrong sync can wipe client uploads or `.htaccess`.

## Backup-before-deploy checklist

- [ ] Database dump (mysqldump or panel backup)
- [ ] Server `.env` copy (secure storage, not git)
- [ ] `storage/` (uploads, logs if needed)
- [ ] `public/client-assets/{slug}/` (logos, banners)
- [ ] Note current git commit / tag for rollback

## Runtime helper

`App\Support\Client\ClientProfile`:

- `slug()` — client slug from env
- `theme()` — active deployment theme
- `assetProfile()` — public asset folder name
- `moduleEnabled($module)` — deployment module flag
- `assetPath($path)` — relative public path for client assets

Not called from Blade or routes in this sprint.

## Git ignore rules

Tracked: `client.json`, `branding.json`, `modules.json`, `deployment.json`, `notes.md`, `env.production.example`

Ignored: `clients/*/env.production`, `clients/*/.env`, `clients/*/secrets.json`, key/pem files
