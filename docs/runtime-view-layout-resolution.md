# Runtime view and layout resolution (MC-8B / MC-8C)

MC-8B introduces an **opt-in** view resolver that can return theme-specific Blade
names per client profile without replacing production layouts or overriding
Laravel’s global view finder.

## Theme view namespace

Theme views live under filesystem roots defined in `config/client_view_paths.php`:

| Area     | Theme root on disk                                      |
|----------|---------------------------------------------------------|
| frontend | `resources/views/themes/frontend/{theme}/`              |
| admin    | `resources/views/themes/admin/{theme}/`                 |
| staff    | `resources/views/themes/staff/{theme}/`                 |
| customer | `resources/views/themes/customer/{theme}/`             |
| agent    | `resources/views/themes/agent/{theme}/`                 |

Laravel dot notation for a logical view `home` in area `frontend` with resolved
theme `v1-classic`:

```
themes.frontend.v1-classic.home
```

Layouts use the same namespace, e.g. `themes.frontend.v1-classic.layouts.frontend`.

## Fallback chain

1. **Resolve theme** — `RuntimeThemeManager` for `frontend`, `admin`, and `staff`
   (profile DB → `config/ota_client` → registry fallback). Agent and customer
   use config fallbacks (`default-agent`, `default-customer`) until MC-8C+ wiring.
2. **Check theme view** — `themes.{area}.{theme}.{name}` via `View::exists()`.
3. **Legacy view** — map short names to existing production views, e.g.
   `home` + `frontend` → `frontend.home`, `index` + `admin` → `dashboard.admin.index`.
   Names that already contain a dot (e.g. `auth.login`) pass through unchanged.
4. **Missing theme folder** — never throws; resolver returns the legacy name.

## API

| Entry point | Purpose |
|-------------|---------|
| `RuntimeViewResolver` | Service: `view()`, `exists()`, `first()`, `layout()`, `summary()` |
| `client_view($name, $area)` | Helper returning resolved view name |
| `client_view_exists($name, $area)` | Helper boolean |
| `client_layout($name, $area)` | Helper for layout view names |
| `ota:client-view-audit --client=haseeb-master` | Read-only CLI audit |
| `ota:client-view-smoke --client=haseeb-master` | MC-8C read-only smoke (resolution + HTTP checks) |

## MC-8C first page migration

**Page chosen:** public homepage desktop shell (`HomeController@index`).

- **Controller change:** desktop branch returns
  `view(client_view('frontend.home', 'frontend'), $viewData)` instead of
  hardcoded `frontend.home`. Mobile shell still uses `mobile.home` unchanged.
- **Theme view:** `resources/views/themes/frontend/v1-classic/frontend/home.blade.php`
  is a **non-invasive delegate** — HTML comment marker plus `@include('frontend.home')`
  so production markup is unchanged while proving theme resolution at runtime.
- **Config:** `config/client_view_paths.php` → `mc8c_migrated_page` documents the
  logical view name for audits and smoke.

### Fallback behavior (unchanged from MC-8B)

1. Resolve active frontend theme for the current/default client profile.
2. If `themes.frontend.{theme}.frontend.home` exists on disk → use it.
3. Otherwise → `frontend.home` (legacy production view).

Removing or renaming the theme file instantly restores legacy rendering with no
controller change.

### Safely migrating more pages later

1. Pick one low-risk GET page (static CMS, about, diagnostic) or one controller
   return at a time — never bulk-replace `view()` globally.
2. Add a theme Blade under `resources/views/themes/{area}/{theme}/…` matching the
   logical name passed to `client_view()`.
3. Prefer delegate shells (`@include('legacy.view')`) or HTML comment markers until
   the themed layout is ready for production.
4. Update `mc8c_migrated_page` or add a new config key if smoke/audit should track
   additional pages.
5. Run **`ota:client-view-smoke`**, **`ota:client-view-audit`**, and
   **`ota:route-safety-audit`** before deploy.
6. Migrate **`client_layout()`** only when a themed layout file exists; keep
   `@extends('layouts.frontend')` in legacy views until then.

## Why layouts are not replaced yet

Production pages still `@extends('layouts.frontend')`, `layouts.dashboard`, etc.
MC-8B scaffolds theme directories and provides resolution machinery. **MC-8C**
migrated only the homepage **desktop** view return to `client_view()`; layouts and
most other pages remain on legacy names.

This keeps **haseeb-master** stable: the homepage looks identical because the theme
shell delegates to `frontend.home` until a future phase replaces the inner content
or layout intentionally.

## MC-8D and beyond

MC-8D+ will migrate additional layouts/pages into theme folders and update
selected call sites to use `client_view()` / `client_layout()`. Each migration
will be audited with `ota:client-view-audit`, `ota:client-view-smoke`, and
existing route safety checks.

**MC-9A/9B** completed frontend/auth/admin page `@extends` migration — see
[`runtime-layout-migration.md`](runtime-layout-migration.md).

## Related docs

- MC-8A theme registry and assets: `docs/runtime-theme-engine.md`
- Dev CP theme page shows both MC-8A runtime theme summary and MC-8B view roots.
