# Client profile export and sync

How to capture a **local-safe** client deployment profile from a live OTA instance
after Platform Owner / Dev CP company setup is complete.

## Source of truth

| Layer | Role |
|-------|------|
| **`client_profiles` DB** | Dev CP source of truth when synced (see **`docs/devcp-client-profile-management.md`**) |
| **Dev CP / admin company setup** | Agency settings feed sync/export branding fallbacks |
| **`clients/{slug}/` JSON** | Human-readable deployment metadata for ops (not loaded at runtime) |
| **`config/ota_client.php`** | Runtime deployment profile fallback (`OTA_CLIENT_*`, `OTA_MODULE_*`) |
| **`config/ota-client.php`** | Branding/contact fallbacks (unchanged; separate from deployment profile) |

Export prefers the DB profile by slug when present; otherwise uses config (and optional agency branding with `--from-db`).

Run export **after** company/branding setup so DB-enriched fields (logo paths, colors,
contact details) reflect production intent.

## Export command

```bash
php artisan ota:export-client-profile {slug?} --from-db --include-assets --force
```

| Option | Purpose |
|--------|---------|
| `{slug?}` | Target slug; defaults to `config('ota_client.slug')` / `OTA_CLIENT_SLUG` |
| `--from-db` | Merge default agency `agency_settings` when tables exist |
| `--include-assets` | Copy logo/favicon/banner files into `public/client-assets/{profile}/` |
| `--force` | Overwrite existing `clients/{slug}/` export |

### What gets written

**Profile folder** — `clients/{slug}/`:

- `client.json` — identity, domain, theme, locale, timezone, currency
- `branding.json` — logo/favicon paths, colors, contact, footer text
- `modules.json` — deployment module flags (`OTA_MODULE_*`)
- `deployment.json` — hosting/SSH path placeholders (no secrets)
- `env.production.example` — safe env template with client values filled (secrets blank)
- `notes.md` — export summary and deployment-scope warning

**Asset scaffold** — `public/client-assets/{slug}/`:

- `logo/`
- `banners/`
- `favicon/`
- `uploads/`

With `--include-assets`, files are copied from agency storage paths and/or an existing
`public/client-assets/{profile}/` tree when present.

### What is never exported

- Server `.env`
- `APP_KEY`, DB credentials, mail passwords
- Supplier API keys (Duffel, Sabre, Al-Haider, etc.)
- SSH passwords or private keys
- `clients/*/secrets.json` or `*.pem` key material

If live env values would leak into generated files, the exporter strips them and leaves
empty placeholders.

## Config and DB inputs

Always read from runtime deployment config:

- `config('ota_client.slug')`
- `config('ota_client.theme')`
- `config('ota_client.asset_profile')`
- `config('ota_client.modules')`

With `--from-db`, also read default agency branding (`OTA_DEFAULT_AGENCY_SLUG`) when
`agencies` / `agency_settings` exist:

- Company name, domain, phone, email, address
- Logo, favicon, hero image paths
- Primary/secondary/accent colors, footer text

Missing DB rows fall back to `config/ota-client.php`, `config/app.php`, and env safely.

## Master testing vs client production

| Environment | `clients/` | `public/client-assets/` |
|-------------|------------|---------------------------|
| **Master testing workspace** | May contain **all** client profiles for validation | May contain **all** client asset trees |
| **Dedicated client production server** | **Only** `clients/{own-slug}/` | **Only** `public/client-assets/{own-slug}/` |

Do **not** deploy the full multi-client scaffold to a client production host. Each
production server should ship only its own profile and assets.

`notes.md` in every export repeats this warning.

## Sync workflow (live → local)

1. Complete Dev CP / company branding on the live server.
2. SSH to server (or run locally on a staging clone with the same setup).
3. Run:
   ```bash
   php artisan ota:export-client-profile --from-db --include-assets --force
   ```
4. Review generated JSON and `env.production.example` locally.
5. Use SFTP to **pull** new/updated files from live to local when the export was run
   on the server:
   - `clients/{slug}/`
   - `public/client-assets/{slug}/`
6. Commit profile metadata to git (never commit `.env` or secrets).

### SFTP profiles (this project)

| Profile | Use for |
|---------|---------|
| **OTA App - Laravel** | `clients/{slug}/` is outside normal app sync — copy manually or pull via SFTP |
| **OTA Public - Live Web Root** | `public/client-assets/{slug}/` uploads |

Follow `.cursor/rules/sftp-live-server-rules.mdc`: single-file uploads, no blind folder sync.

## Related docs

- [client-deployment-architecture.md](client-deployment-architecture.md) — multi-client folder layout
- [new-client-deployment-checklist.md](new-client-deployment-checklist.md) — first deploy checklist

## Runtime helper

`App\Support\Client\ClientProfile` — static deployment profile (slug, theme, modules).

`App\Support\Client\ClientProfileExporter` — export implementation used by the Artisan command.

Neither replaces the existing multi-client scaffold under `clients/_template/` and
`clients/client-demo/`.
