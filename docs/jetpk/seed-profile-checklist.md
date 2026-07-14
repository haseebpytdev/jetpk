# JetPK seed & profile checklist

## 1. Ops folder (git)

- [ ] `clients/jetpk/client.json` — name, slug, domain, theme
- [ ] `clients/jetpk/branding.json` — colors, contact, logo paths
- [ ] `clients/jetpk/modules.json` — deploy module flags
- [ ] `clients/jetpk/deployment.json` — SFTP paths (no secrets)
- [ ] `clients/jetpk/env.production.example` — server `.env` template

## 2. Database profile (master or JetPK server)

```bash
php artisan ota:seed-jetpakistan-client-profile
```

Creates/updates:

| Table | Values |
|-------|--------|
| `client_profiles` | slug `jetpk`, theme `jetpakistan`, asset `jetpk-assets`, domain `jetpakistan.com` |
| `client_profile_branding` | Green/gold brand, support contact |
| `client_profile_modules` | From config defaults |
| `client_profile_suppliers` | All suppliers disabled initially |

Verify:

```bash
php artisan ota:client-preview-runtime-status --client=jetpk
```

## 3. Production `.env` (JetPK dedicated server)

Copy `clients/jetpk/env.production.example` → server `.env`:

- [ ] `OTA_CLIENT_SLUG=jetpk`
- [ ] `OTA_ACTIVE_THEME=jetpakistan`
- [ ] `OTA_PUBLIC_ASSET_PROFILE=jetpk-assets`
- [ ] `OTA_MODULE_*` match `modules.json`
- [ ] `APP_URL=https://www.jetpakistan.com` (or staging domain)
- [ ] `OTA_DEFAULT_AGENCY_SLUG=jetpk` (or agency slug for JetPK)

On **master workspace** (testing only), keep `OTA_CLIENT_SLUG=haseeb-master`; use `/jetpk/*` preview routes.

## 4. Client assets on disk

- [ ] Upload `public/client-assets/jetpk-assets/logo/` and `favicon/`
- [ ] Confirm branding paths match DB (`logo/logo.svg`, `favicon/favicon.ico`)

## 5. Theme registry

Confirm `config/client_themes.php` includes `jetpakistan` with `status: active`.

## 6. Agency linkage (if separate agency per client)

- [ ] Default agency exists for JetPK bookings
- [ ] Agency branding aligned or overridden by client preview branding

## 7. Smoke test

- [ ] `/` or `/jetpk/home` loads
- [ ] Branding colors and company name correct
- [ ] Logo/favicon resolve
- [ ] Login page uses JetPakistan auth shell
- [ ] Disabled modules not in nav

## Re-seed (idempotent)

Safe to re-run `ota:seed-jetpakistan-client-profile` — updates profile, does not delete bookings.
