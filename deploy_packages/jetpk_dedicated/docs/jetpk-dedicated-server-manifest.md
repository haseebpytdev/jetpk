# JetPK dedicated server deployment manifest

**Phase:** JETPK-DEDICATED-SERVER-PACKAGE-AND-DEPLOYMENT-MANIFEST-7I  
**Client slug:** `jetpk`  
**Theme:** `jetpakistan`  
**Asset profile:** `jetpk-assets`  
**Last verified:** 2026-07-07

This manifest defines what must be present on a **dedicated JetPakistan server** (Mode B) without leaking Master / Parwaaz / YD client UI, routes, assets, or settings.

---

## Deployment modes

| Mode | Base URLs | Server | Status |
|------|-----------|--------|--------|
| **A — Shared preview** | `/jetpk/home`, `/jetpk/login`, `/jetpk/admin`, `/jetpk/admin/page-settings` | Hostinger Master (`ota.haseebasif.com`) | **Current live** — do not change |
| **B — Dedicated root** | `/`, `/login`, `/admin`, `/admin/page-settings`, `/groups/search`, `/flights/results` | `jetpakistan.com` (dedicated) | **Prepared** — deploy when approved |

### Mode B root-domain behavior (no code switch required)

With `OTA_CLIENT_SLUG=jetpk` on the dedicated server:

- `ClientProfileResolver::defaultDeploymentSlug()` returns `jetpk`
- Unprefixed routes (`/`, `/admin`, `/login`) serve JetPK directly
- Prefixed `/jetpk/*` URLs **302 redirect** to canonical root paths via `ResolvePreviewClient`
- `client_route()` generates unprefixed URLs when no preview slug is in the request
- `CLIENT_ROUTE_PARITY_ENABLED=true` remains valid; parity routes exist but default-slug redirect strips prefix

**Not implemented (future only):** `OTA_SINGLE_CLIENT_MODE`, `OTA_SINGLE_CLIENT_ROOT` — use `OTA_CLIENT_SLUG=jetpk` today.

### Runtime stack (shared — required)

| Component | Path / class |
|-----------|----------------|
| Client profile resolver | `app/Services/Client/ClientProfileResolver.php` |
| Current context | `app/Services/Client/CurrentClientContext.php` |
| Preview middleware | `app/Http/Middleware/ResolvePreviewClient.php` |
| Context persistence | `app/Http/Middleware/PersistClientPreviewContext.php` |
| GET parity registrar | `app/Services/Client/ClientPrefixedRouteRegistrar.php` |
| Page Settings mutating parity | `app/Services/Client/ClientPageSettingsParityRouteRegistrar.php` |
| View resolver | `app/Services/Client/RuntimeViewResolver.php` |
| Theme manager | `app/Services/Client/RuntimeThemeManager.php` |
| Page content | `app/Services/Client/ClientPageContentResolver.php` |
| Page assets | `app/Services/Client/ClientPageAssetService.php` |
| Theme palette | `app/Services/Branding/ClientThemePaletteService.php` |
| Helpers | `app/Support/Client/client_helpers.php` |
| Page keys | `app/Support/Client/ClientPageKeys.php` |
| Public webroot resolver | `app/Support/Client/ClientPublicWebrootPath.php` |

### Config (required)

| File | Purpose |
|------|---------|
| `config/ota_client.php` | `OTA_CLIENT_SLUG`, modules, OTP, public webroot path |
| `config/client_route_parity.php` | Parity enable, default slug, exclusions |
| `config/client_themes.php` | Theme registry (`jetpakistan`) |
| `config/client_view_paths.php` | View path resolution |
| `config/client_ui.php` | UI version / preview |
| `config/filesystems.php` | `public` disk, client asset storage |
| `config/mail.php` | SMTP structure (values from `.env`) |
| `config/queue.php` | Queue driver |

Supplier credential **values** live in DB (`supplier_connections`) — not in config files.

---

## A. JetPK frontend views

**Glob:** `resources/views/themes/frontend/jetpakistan/**`  
**Count:** ~57 files

| Area | Paths |
|------|-------|
| Layouts | `layouts/frontend.blade.php`, `layouts/auth.blade.php`, `layouts/portal.blade.php`, `layouts/error.blade.php` |
| Public pages | `frontend/home.blade.php`, `about.blade.php`, `support.blade.php`, `flights/results.blade.php`, `flights/details.blade.php`, `booking/*`, `groups/*` |
| Sections | `sections/hero.blade.php`, `feature-board.blade.php`, `groups.blade.php`, etc. |
| Errors | `errors/403.blade.php` … `500.blade.php`, `errors/partials/shell.blade.php` |
| Portal components | `components/portal/*` |

**JP components (shared by frontend):** `resources/views/components/jp/**` (~22 files)

---

## B. JetPK admin views

**Glob:** `resources/views/themes/admin/jetpakistan/**`  
**Count:** ~34 files

| Area | Paths |
|------|-------|
| Shell | `layouts/dashboard.blade.php`, `partials/sidebar.blade.php`, `partials/topbar.blade.php` |
| Ops | `index.blade.php`, `bookings.blade.php`, `bookings/show.blade.php` |
| Settings | `settings/index.blade.php`, `settings/branding.blade.php`, `settings/communications/index.blade.php` |
| Page Builder | `page-settings/index.blade.php`, `edit.blade.php`, `palette.blade.php` |
| Deep admin | `api-settings/*`, `group-ticketing/*`, `users/*`, `agencies/*`, `support/tickets/*`, `markups/*`, `supplier-diagnostics.blade.php`, `agent-applications/*`, `agents.blade.php`, `reports.blade.php` |
| Components | `components/empty-state.blade.php`, `components/status-badge.blade.php` |

**Anonymous components:** `resources/views/components/themes/admin/jetpakistan/**` (~2 files)

---

## C. JetPK staff / agent / customer views

| Portal | Glob | Count |
|--------|------|-------|
| Staff | `resources/views/themes/staff/jetpakistan/**` | ~4 |
| Agent | `resources/views/themes/agent/jetpakistan/**` | ~5 |
| Customer | `resources/views/themes/customer/jetpakistan/**` | ~4 |

---

## D. JetPK public assets

### Laravel repo (`public/`)

| Path | Count | Notes |
|------|-------|-------|
| `public/themes/frontend/jetpakistan/**` | ~21 | CSS/JS for public + results + booking |
| `public/themes/admin/jetpakistan/**` | ~2 | `dashboard.css`, `dashboard.js` |
| `public/client-assets/jetpk-assets/**` | logo, favicon, README | Brand kit — may be sparse in git |

### Hostinger live public webroot (authoritative for production bytes)

**Source (shared Master server today):**

```
/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/themes/frontend/jetpakistan/
/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/themes/admin/jetpakistan/
/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/client-assets/jetpk-assets/
```

Optional if used:

```
.../public_html/ota.haseebasif.com/storage/group-homepage-tiles/
.../public_html/ota.haseebasif.com/storage/
```

### Dedicated server target (Mode B example)

```
/home/<jetpk_user>/domains/jetpakistan.com/public_html/themes/frontend/jetpakistan/
/home/<jetpk_user>/domains/jetpakistan.com/public_html/themes/admin/jetpakistan/
/home/<jetpk_user>/domains/jetpakistan.com/public_html/client-assets/jetpk-assets/
/home/<jetpk_user>/domains/jetpakistan.com/public_html/storage/
```

Set `OTA_PUBLIC_WEBROOT_PATH` to the dedicated `public_html` path.

See [jetpk-dedicated-cutover-plan.md](jetpk-dedicated-cutover-plan.md).

---

## E. Shared client runtime (full Laravel app)

Deploy the **complete Laravel application** — JetPK is not a static theme pack.

| Category | Include |
|----------|---------|
| Application | `app/`, `bootstrap/`, `config/`, `routes/`, `database/migrations/` |
| Composer | `composer.json`, `composer.lock` |
| Shared views | `resources/views/auth/`, booking/supplier views, emails |
| Shared public | `public/index.php`, `public/css/`, `public/js/`, `public/images/` |
| Storage | `storage/` writable; `php artisan storage:link` |

**Ops metadata:** `clients/jetpk/**` (no secrets)

---

## F. Controllers / routes

| Area | Route files |
|------|-------------|
| Public | `routes/web.php` |
| Auth | `routes/auth.php` |
| Admin | `routes/admin.php` |
| Portals | `routes/staff.php`, `routes/agent.php`, `routes/customer.php` |
| Preview | `routes/preview.php` |
| Parity | `bootstrap/app.php` registrars |

---

## G. Commands / audits

```bash
php artisan ota:jetpk-dedicated-package-audit --client=jetpk
php artisan ota:jetpk-dedicated-server-readiness --client=jetpk
php artisan ota:route-page-health-audit --all
```

---

## H. Exclude list

| Exclude | Reason |
|---------|--------|
| `.env` | Secrets |
| Raw DB dumps | Credentials |
| `storage/logs/**`, framework cache | Ephemeral |
| `vendor/`, `node_modules/` | Install on server |
| `Binham/`, baselines | Local artifacts |
| Other client themes/assets | Master / YD leakage |
| Dev CP (optional) | `OTA_MODULE_DEV_CP=false` |

---

## I. Ambiguous decisions

| Item | Default recommendation |
|------|------------------------|
| Dev CP on dedicated JetPK | Off |
| `client_page_settings` seed | Required if empty — approved data pass |
| Google OAuth | Re-register for dedicated domain |

---

## Verification

```bash
php artisan ota:jetpk-dedicated-package-audit --client=jetpk
```
