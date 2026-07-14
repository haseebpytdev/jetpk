# Dev CP client profile management (MC-2)

How OTA stores **deployment-level client profiles** in the database for Dev CP,
while keeping `.env` as base server secrets and runtime fallback.

## Source of truth

| Layer | Role |
|-------|------|
| **`client_profiles` DB** | Dev CP source of truth for slug, theme, modules, branding, suppliers (MC-2+) |
| **`.env` / `config/ota_client.php`** | Fallback when no DB row exists; base server secrets (APP_KEY, DB, mail, supplier tokens) |
| **`config/ota-client.php`** | Branding/contact fallbacks when syncing or exporting without a branding row |
| **`clients/{slug}/` JSON** | Ops export snapshot (not loaded at runtime) |

Runtime **still reads** `config/ota_client.php` via `App\Support\Client\ClientProfile`
until a later phase wires `ClientProfileResolver` into routes/gates/views.

## Database tables

| Table | Purpose |
|-------|---------|
| `client_profiles` | Identity, slug, domain, themes, locale, timezone, currency, master flag |
| `client_profile_modules` | Per-profile module toggles (`OTA_MODULE_*` keys) |
| `client_profile_suppliers` | Per-profile supplier enablement, mode, encrypted credentials, config |
| `client_profile_branding` | Company name, logo/favicon paths, colors, contact, footer |

## Commands

### Sync current deployment into DB

```bash
php artisan ota:sync-current-client-profile {--slug=} {--dry-run}
```

- Slug resolution: `--slug` → `OTA_CLIENT_SLUG` → default `haseeb-master`
- Reads `config/ota_client.php` + branding fallbacks + default agency `agency_settings`
- Upserts profile, modules, branding, suppliers (from agency `supplier_connections` when present)
- Sets `is_master_profile=true` when slug is `haseeb-master`
- Idempotent — safe to re-run after config or agency branding changes

### Export profile to `clients/{slug}/`

```bash
php artisan ota:export-client-profile {slug?} --from-db --include-assets --force
```

- **Prefers DB profile** by slug when `client_profiles` row exists
- Falls back to config + optional agency branding (`--from-db`) when DB row missing
- Never exports supplier credentials or other secrets into JSON or `env.production.example`

See also: [client-profile-export-sync.md](client-profile-export-sync.md)

## Typical ops workflow (master server)

1. Deploy MC-2 migration and PHP files
2. `php artisan migrate --force`
3. `php artisan ota:sync-current-client-profile`
4. `php artisan ota:export-client-profile haseeb-master --force`
5. Review `clients/haseeb-master/` locally before any client-specific deploy

## Master vs production client servers

| Server type | Profiles in DB | Assets |
|-------------|----------------|--------|
| **Master testing** | May contain all client profiles (`haseeb-master`, future clients) | `public/client-assets/{profile}/` for each |
| **Dedicated client production** | Should contain **only that client's** profile row | Only that client's asset folder |

Do not deploy another client's DB profile or assets to a dedicated production host.

## Services

| Class | Role |
|-------|------|
| `App\Services\Client\ClientProfileResolver` | Load profile by slug; `toRuntimeConfig()` for export |
| `App\Services\Client\ClientProfileSyncService` | Upsert DB from current config/branding |
| `App\Support\Client\ClientProfileConfigReader` | Shared config/agency branding reads |
| `App\Support\Client\ClientProfileExporter` | DB-first export to `clients/{slug}/` |

## Preview routing (out of scope)

Domain-based preview paths (`preview_path` column) and theme preview URLs are **not**
implemented in MC-2. That is the next phase (MC-3+).

## Dev CP UI (MC-3)

Implemented in Dev CP under **Clients** (`/dev/cp/clients`). See [devcp-client-manager-ui.md](devcp-client-manager-ui.md) for screens, routes, master confirmation, duplicate/export behavior, and deploy notes.

Preview routing and runtime theme switching remain out of scope.

## Safety

- Supplier credentials stored with `encrypted:array` on `ClientProfileSupplier`
- Export strips live `.env` secret values and refuses to write detected secrets
- Do not commit `.env` or copy encrypted credentials into JSON exports
