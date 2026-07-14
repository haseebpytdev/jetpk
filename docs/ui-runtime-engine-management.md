# UI Runtime Engine Management (MC-8D)

Developer guide for the client UI runtime engine: theme selection, view/layout resolution, asset profiles, Dev CP visibility, and safe migration.

## Overview

The runtime engine resolves **views** and **layouts** at request time based on the active client profile. It is **opt-in** — existing production Blade files continue to work unchanged until a page or controller explicitly uses the helpers.

| Component | Helper | Service |
|-----------|--------|---------|
| Theme selection | `client_theme()` | `RuntimeThemeManager` |
| Page views | `client_view()` | `RuntimeViewResolver::view()` |
| Layouts | `client_layout()` | `RuntimeViewResolver::layout()` |
| Assets | `client_assets()` | `ClientAssetResolver` |
| Links | `client_route()`, `client_url()` | route parity + slug prefix |

## How Dev CP theme settings map to code folders

On **Dev CP → Clients → Theme** tab:

| Dev CP field | Code effect |
|--------------|-------------|
| **Frontend theme** | Views/layouts under `resources/views/themes/frontend/{theme}/` |
| **Admin theme** | Views/layouts under `resources/views/themes/admin/{theme}/` |
| **Staff theme** | Views/layouts under `resources/views/themes/staff/{theme}/` |
| **Asset profile** | Client assets under `public/client-assets/{asset_profile}/` |

Theme keys must exist in `config/client_themes.php` registry. Invalid selections fall back to the area default.

Agent and customer areas use config fallbacks (`default-agent`, `default-customer`) from `config/client_view_paths.php`.

## Where to place theme views

```
resources/views/themes/{area}/{theme}/
├── frontend/          # page views (frontend area)
├── layouts/           # layout shells
│   ├── frontend.blade.php
│   └── auth.blade.php
└── diagnostics/       # optional test-only views
```

**Logical name → path examples (frontend, theme `v1-classic`):**

| Logical name | Theme path | Legacy fallback |
|--------------|------------|-----------------|
| `home` | `themes.frontend.v1-classic.home` | `frontend.home` |
| `frontend.home` | `themes.frontend.v1-classic.frontend.home` | `frontend.home` |
| Layout `frontend` | `themes.frontend.v1-classic.layouts.frontend` | `layouts.frontend` |
| Layout `auth` | `themes.frontend.v1-classic.layouts.auth` | `layouts.auth` |

## Where to place theme assets

| Type | Path |
|------|------|
| Theme-scoped public assets | `public/themes/{area}/{theme}/` |
| Client-specific assets | `public/client-assets/{clientSlug}/` |

Asset base URLs come from `RuntimeThemeManager::assetBase()` / registry entries in `config/client_themes.php`.

## How fallback works

1. Resolver builds the theme-specific dot name (e.g. `themes.frontend.v1-classic.layouts.frontend`).
2. If the file exists on disk → use it.
3. Otherwise → use the legacy production name (e.g. `layouts.frontend`).

**Rules:**

- Do **not** delete legacy layouts or views until all callers are migrated.
- Theme layout shells may delegate with a one-line `@extends('layouts.frontend')` — no visual change.
- Missing theme files never cause 500s; fallback is automatic.

## How to migrate one page safely

1. Create theme view under `resources/views/themes/{area}/{theme}/` (delegate with `@include('legacy.view')` if needed).
2. Change **one controller** to `return view(client_view('logical.name', 'area'), $data);`
3. Run verification commands (below).
4. Upload changed files only; clear view cache on server.
5. Smoke-test the affected URL (root + `/{clientSlug}/…` if applicable).

**MC-8C example:** `HomeController` uses `client_view('frontend.home', 'frontend')`; theme shell at `themes/frontend/v1-classic/frontend/home.blade.php` includes `frontend.home`.

## How to migrate one layout safely

1. Add theme layout shell that `@extends('layouts.{name}')` (delegate-only, no redesign).
2. Change **one view** from `@extends('layouts.frontend')` to `@extends(client_layout('frontend', 'frontend'))`.
3. Verify with layout audit and render test.
4. Do not bulk-replace `@extends` across the app in one pass.

**MC-8D shells** exist for frontend, auth, admin/staff dashboard, agent portal, and customer account layouts.

Diagnostic view (tests only):

`resources/views/themes/frontend/v1-classic/diagnostics/layout-resolution-smoke.blade.php`

## Verification commands

All read-only; use `--client=haseeb-master` (default deployment client):

```bash
php artisan ota:client-theme-audit --client=haseeb-master
php artisan ota:client-view-audit --client=haseeb-master
php artisan ota:client-layout-audit --client=haseeb-master
php artisan ota:ui-runtime-audit --client=haseeb-master
php artisan ota:route-safety-audit --client=haseeb-master
php artisan ota:client-view-smoke --client=haseeb-master
```

**Combined audit:** `ota:ui-runtime-audit` prints theme, view, layout, route safety, and client context summaries.

**Tests:**

```bash
php artisan test --filter=RuntimeViewResolverTest
php artisan test --filter=OtaClientLayoutAuditCommandTest
php artisan test --filter=OtaUiRuntimeAuditCommandTest
php artisan test --filter=Mc8dClientLayoutMigrationTest
php artisan test --filter=DevCpClientProfilesTest
```

## Dev CP UI Runtime Engine panel

**Dev CP → Clients → {profile} → Theme** shows:

- Active frontend/admin/staff themes and asset profile
- View and layout resolver status
- Theme roots and layout fallback samples
- Registered themes not currently active
- Developer instructions for folders and helpers

## Rollback rules

1. Revert controller/view changes to use legacy dot names directly.
2. Remove or leave theme shells (unused shells are harmless).
3. Restore prior Dev CP theme fields if changed.
4. Server: `php artisan view:clear` and `php artisan cache:clear`.
5. Re-run `ota:ui-runtime-audit` and affected feature tests.

Do not delete legacy `resources/views/layouts/*` or production page views during rollback.

## Related docs

- `docs/runtime-theme-engine.md` — MC-8A theme registry
- `docs/runtime-view-layout-resolution.md` — MC-8B/8C view resolver detail
