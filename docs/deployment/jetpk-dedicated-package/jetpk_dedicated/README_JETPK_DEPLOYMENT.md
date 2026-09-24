# JetPakistan dedicated server deployment package

**Phase:** JETPK-DEDICATED-SERVER-PACKAGE-BUILD-7J  
**Package path:** `deploy_packages/jetpk_dedicated/`  
**Type:** **Manifest-based** — file lists and scripts only; no application bytes copied into this folder  
**Deploy target:** Not executed in 7J — upload when explicitly approved

---

## What this package includes

| File | Purpose |
|------|---------|
| `app_files_manifest.txt` | Laravel app paths to upload to `ota_app/` |
| `public_webroot_manifest.txt` | Public asset source → target mapping |
| `data_seed_manifest.txt` | DB rows to export/import (no secrets) |
| `.env.example.jetpk` | Dedicated `.env` template (placeholders only) |
| `post_deploy_commands.sh` | Safe server setup commands |
| `post_deploy_checks.sh` | Read-only audit commands after deploy |
| `docs/` | Manifest, data checklist, cutover plan, Google OAuth guide |

---

## What this package excludes

- `.env` (live secrets)
- `vendor/`, `node_modules/` (install on server)
- `storage/logs/`, `storage/framework/cache|sessions|views/`
- Raw DB dumps with credentials
- Supplier API credentials, SMTP passwords, OAuth secrets, `APP_KEY`
- `tar.gz` / zip archives (not used)
- Master-only themes and other client assets

---

## Dedicated server target paths

| Role | Path |
|------|------|
| Laravel app | `/home/<jetpk_user>/domains/jetpakistan.com/ota_app/` |
| Public webroot | `/home/<jetpk_user>/domains/jetpakistan.com/public_html/` |
| Theme CSS/JS | `public_html/themes/frontend/jetpakistan/`, `.../admin/jetpakistan/` |
| Client branding | `public_html/client-assets/jetpk-assets/` |
| Storage symlink | `public_html/storage` → `ota_app/storage/app/public` |

Set `OTA_PUBLIC_WEBROOT_PATH` in `.env` to the `public_html` path.

---

## App upload checklist

1. Upload full Laravel app per `app_files_manifest.txt` (or sync changed files from git)
2. **Exclude:** `.env`, `vendor/`, ephemeral `storage/framework/*`, logs
3. Run `composer install --no-dev` on server (see `post_deploy_commands.sh`)
4. Copy `.env.example.jetpk` → `.env` and fill DB + post-deploy values on server only

---

## Public webroot upload checklist

**Authoritative source today (shared Hostinger):**

```
/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/themes/frontend/jetpakistan/
/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/themes/admin/jetpakistan/
/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/client-assets/jetpk-assets/
/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/storage/group-homepage-tiles/  (if used)
```

**Dedicated target:** see `public_webroot_manifest.txt` for each file path.

Also upload shared `css/`, `js/`, `images/` if JetPK booking flows reference them.

**Note:** Logo/favicon may exist on live webroot but not in `ota_app/public/` — copy from live `public_html`.

---

## `.env` setup

1. `cp .env.example.jetpk .env` on dedicated server
2. `php artisan key:generate`
3. Set `DB_*`, `APP_URL=https://jetpakistan.com`, `OTA_CLIENT_SLUG=jetpk`
4. Set `OTA_PUBLIC_WEBROOT_PATH` to dedicated `public_html`
5. **SMTP** — configure after deployment (new JetPK SMTP details)
6. **Google OAuth** — configure after deployment (see `docs/jetpk-google-oauth-setup.md`)
7. **Suppliers** — not in `.env`; use Admin → Supplier & API Settings

---

## DB / user seed expectations

- Export **jetpk** `client_profiles`, branding, modules, supplier **gates** (not credentials) from Master
- **Reuse the same seeded users** from Master Client testing (platform admin, staff, agent, customer)
- **Do not** require creating a new `bobsif` user — reuse existing seed
- Passwords/hashes are **not** in this package; reset on dedicated server if needed
- `client_page_settings` — **seed/export required before client handoff** if table is empty (approved pass)

---

## Post-deployment configuration (in order)

1. **Database** — import seed per `data_seed_manifest.txt`
2. **Storage** — `php artisan storage:link`
3. **SMTP** — `.env` mail settings + test from Admin → Communications
4. **Google OAuth** — JetPK official Google account → `docs/jetpk-google-oauth-setup.md`
5. **Suppliers** — Admin → Supplier & API Settings (Sabre, PIA NDC, AirBlue, Duffel, Al-Haider, etc.)
6. **Page settings** — seed then `/admin/page-settings` if empty
7. **Email templates** — separate future Claude phase (not in 7J)
8. **Cron** — `* * * * * php artisan schedule:run`
9. **Queue worker** — if `QUEUE_CONNECTION=database`

---

## Verification commands

On server after deploy:

```bash
bash post_deploy_checks.sh
```

Or manually:

```bash
php artisan ota:jetpk-dedicated-package-audit --client=jetpk
php artisan ota:jetpk-dedicated-server-readiness --client=jetpk
php artisan ota:route-page-health-audit --all
```

---

## Browser checklist (manual)

| URL | Expected |
|-----|----------|
| `/` | JetPK home |
| `/login` | JetPK auth; Google button after OAuth configured |
| `/admin` | JetPK admin (after login) |
| `/admin/page-settings` | Page Settings index |
| `/groups/search` | Group search |
| `/flights/results?...` | Results + branded fare CTA |
| `/about-us`, `/support` | Public pages |

Confirm no redirect to Master `/jetpk/` on dedicated domain (root mode).

---

## Rollback plan

1. Stop DNS cutover or point domain to maintenance page
2. Restore `ota_app` and `public_html` from pre-deploy backup
3. Restore MySQL snapshot
4. Shared Master server (`/jetpk/*`) unchanged — verify `ota:client-context-flow-audit --client=jetpk`

---

## Mode reference

| Mode | URLs | Server |
|------|------|--------|
| A — Shared preview | `/jetpk/home`, `/jetpk/admin` | Current Master (unchanged) |
| B — Dedicated root | `/`, `/admin` | `jetpakistan.com` with `OTA_CLIENT_SLUG=jetpk` |

---

## Related docs

- `docs/jetpk-dedicated-server-manifest.md` — full file manifest
- `docs/jetpk-dedicated-data-checklist.md` — data rows
- `docs/jetpk-dedicated-cutover-plan.md` — step-by-step cutover
- `docs/jetpk-google-oauth-setup.md` — OAuth after deploy
