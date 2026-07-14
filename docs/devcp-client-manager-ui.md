# Dev CP Client Profile Manager (MC-3)

Operator guide for managing DB-backed client profiles from the Developer Control Panel.

## Access

- URL: `/dev/cp/clients`
- Requires `OTA_DEVELOPER_CP_ENABLED=true` and an active `developer_users` session
- Authorization: Dev CP session middleware + FormRequest `authorize()` (not Laravel Policies)

## Navigation

**Developer Control Panel → Clients**

## Screens

### List (`dev.cp.clients.index`)

Columns: Name, Slug, Domain, Theme, Environment, Master, Active, Actions.

Actions per row:

| Action | Route | Notes |
|--------|-------|-------|
| Edit | `dev.cp.clients.edit` | General tab |
| Export | `dev.cp.clients.export` | Uses `ClientProfileExporter` (DB-first, `--force`) |
| Duplicate | `dev.cp.clients.duplicate` | Modal form; optional credential copy |

### Create (`dev.cp.clients.create`)

Fields: name, slug (immutable after create), domain, environment, locale, timezone, currency, frontend theme, asset profile, active flag.

On create the service seeds:

- `client_profile_branding` (company_name = profile name)
- All `ClientProfileConfigReader::MODULE_KEYS` modules (defaults from `config/ota_client.php`)
- All `SupplierProvider` suppliers (disabled)

### Edit tabs

Shared tab navigation on all edit screens:

| Tab | Route | Fields |
|-----|-------|--------|
| General | `dev.cp.clients.edit` | name, domain, environment, locale, timezone, currency, active |
| Branding | `dev.cp.clients.branding` | company_name, logo/favicon paths, colors, phone, email, address, footer |
| Modules | `dev.cp.clients.modules` | Per-module enable toggles |
| Suppliers | `dev.cp.clients.suppliers` | enabled, mode, masked credentials, optional credential updates |
| Theme | `dev.cp.clients.theme` | frontend/admin/staff theme, asset profile, preview path |

**Theme tab note:** `preview_path` is stored but preview routing is **not** wired in MC-3.

## Master profile protection

Profiles with `is_master_profile=true` (e.g. `haseeb-master` from sync) show a warning on all tabs.

Any mutation (general, branding, modules, suppliers, theme, duplicate) requires:

```
confirm_master_edit = 1
```

Export does **not** require master confirmation.

`is_master_profile` is display-only in the UI (set by `ota:sync-current-client-profile` for `haseeb-master`).

## Duplicate behavior

Copies: profile (new slug/uuid), branding, modules, suppliers.

Does **not** copy supplier credentials unless **Copy supplier credentials** is checked.

New profile always has `is_master_profile=false` and `asset_profile` set to the new slug.

## Export behavior

POST export calls:

```php
ClientProfileExporter::export($slug, fromDb: true, includeAssets: false, force: true)
```

Writes `clients/{slug}/` JSON snapshot. Never exports live secrets or supplier credentials.

Equivalent CLI:

```bash
php artisan ota:export-client-profile {slug} --force
```

## Routes (complete)

| Method | Path | Name |
|--------|------|------|
| GET | `/dev/cp/clients` | `dev.cp.clients.index` |
| GET | `/dev/cp/clients/create` | `dev.cp.clients.create` |
| POST | `/dev/cp/clients` | `dev.cp.clients.store` |
| GET | `/dev/cp/clients/{clientProfile}/edit` | `dev.cp.clients.edit` |
| PUT | `/dev/cp/clients/{clientProfile}` | `dev.cp.clients.update` |
| GET | `/dev/cp/clients/{clientProfile}/branding` | `dev.cp.clients.branding` |
| PUT | `/dev/cp/clients/{clientProfile}/branding` | `dev.cp.clients.branding.update` |
| GET | `/dev/cp/clients/{clientProfile}/modules` | `dev.cp.clients.modules` |
| PUT | `/dev/cp/clients/{clientProfile}/modules` | `dev.cp.clients.modules.update` |
| GET | `/dev/cp/clients/{clientProfile}/suppliers` | `dev.cp.clients.suppliers` |
| PUT | `/dev/cp/clients/{clientProfile}/suppliers` | `dev.cp.clients.suppliers.update` |
| GET | `/dev/cp/clients/{clientProfile}/theme` | `dev.cp.clients.theme` |
| PUT | `/dev/cp/clients/{clientProfile}/theme` | `dev.cp.clients.theme.update` |
| POST | `/dev/cp/clients/{clientProfile}/export` | `dev.cp.clients.export` |
| POST | `/dev/cp/clients/{clientProfile}/duplicate` | `dev.cp.clients.duplicate` |

## PHP components

| Class | Role |
|-------|------|
| `DevCpClientProfilesController` | HTTP entry points |
| `DevCpClientProfileManagerService` | Create, update tabs, duplicate |
| `DevCpAuthorizedRequest` | Shared Dev CP session authorize + master confirm helper |
| `ClientProfileExporter` | Export action (reused from MC-2) |
| `PlatformAuditLogger` | Audit trail for create/update/duplicate/export |

## Out of scope (MC-3)

- Preview routing / theme preview URLs
- Runtime theme switching (still uses `config/ota_client.php` via `App\Support\Client\ClientProfile`)
- Public website route changes
- Frontend, admin, or staff layout edits
- File upload for branding assets (paths are text fields)

## Deploy (SFTP)

Upload OTA App profile files, then on server SSH:

```bash
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

## Tests

```bash
php artisan test --filter=DevCpClientProfilesTest
php artisan test --filter=DevCpSectionsTest
```

## Related docs

- [devcp-client-profile-management.md](devcp-client-profile-management.md) — MC-2 DB schema, sync, export
- [client-profile-export-sync.md](client-profile-export-sync.md) — export workflow
