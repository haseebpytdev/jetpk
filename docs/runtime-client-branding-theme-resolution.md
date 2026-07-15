# Runtime client branding and theme resolution (MC-6A)

How preview pages resolve branding metadata and theme ids from the active client
profile. Complements [runtime-client-asset-resolution.md](runtime-client-asset-resolution.md)
and [master-preview-routing.md](master-preview-routing.md).

## Scope

MC-6A adds **`ClientBrandingResolver`** and **`ClientThemeResolver`** and wires them
into **preview placeholder pages only** (`/{clientSlug}/home`, `/login`, `/admin`,
`/staff`, `/agent`). Production layouts (`frontend.blade.php`, dashboard/admin/staff
layouts) are unchanged.

MC-8A adds registry-backed validation via **`ClientThemeRegistry`** and
**`RuntimeThemeManager`** — see [runtime-theme-engine.md](runtime-theme-engine.md).

## Source priority

### Branding (`ClientBrandingResolver`)

| Priority | Source |
|----------|--------|
| 1 | `CurrentClientContext::branding()` → `client_profile_branding` row |
| 2 | `config('ota-client')` + `config('ota_client')` via `ClientProfileConfigReader::brandingFromConfig()` |
| 3 | Safe defaults (`#0c4a6e`, `#0ea5e9`, `#f59e0b`, `config('app.name')`, etc.) |

Logo and favicon **URLs** delegate to **`ClientAssetResolver`** (path + asset profile
resolution unchanged from MC-5A).

### Themes (`ClientThemeResolver`)

| Priority | Source |
|----------|--------|
| 1 | `CurrentClientContext` profile → `client_profiles.active_*_theme`, `asset_profile` |
| 2 | `config('ota_client')` (`theme`, optional future `admin_theme` / `staff_theme`, `asset_profile`, `slug`) |
| 3 | `v1-classic` / empty asset profile |

## Services

### `App\Services\Client\ClientBrandingResolver`

| Method | Returns |
|--------|---------|
| `companyName()` | Display company name |
| `logoUrl()` | Public logo URL or `null` |
| `faviconUrl()` | Public favicon URL or `null` |
| `primaryColor()` | Hex color |
| `secondaryColor()` | Hex color |
| `accentColor()` | Hex color |
| `phone()` | Support phone |
| `email()` | Support email |
| `address()` | Office address |
| `footerText()` | Footer copy |
| `all()` | Associative array of all fields |

### `App\Services\Client\ClientThemeResolver`

| Method | Returns |
|--------|---------|
| `frontendTheme()` | Frontend theme id |
| `adminTheme()` | Admin theme id |
| `staffTheme()` | Staff theme id |
| `assetProfile()` | Folder under `public/client-assets/` |
| `frontendThemeUrl()` | URL for `themes/frontend/{theme}/` |
| `adminThemeUrl()` | URL for `themes/admin/{theme}/` |
| `staffThemeUrl()` | URL for `themes/staff/{theme}/` |
| `themeExists($theme, $area = 'frontend')` | Whether `public/themes/{area}/{theme}/` exists |
| `all()` | Themes, URLs, asset profile, on-disk flags |

## Helpers

Autoloaded from `app/Support/Client/client_helpers.php` (same pattern as `ui_helpers.php`):

| Helper | Returns |
|--------|---------|
| `client_branding()` | `ClientBrandingResolver` instance |
| `client_theme()` | `ClientThemeResolver` instance |
| `client_assets()` | `ClientAssetResolver` instance |

Preview controller uses constructor injection; helpers are available for future Blade
or service code.

## Preview UI

`resources/views/preview/client/partials/context-card.blade.php` shows:

- **Resolved branding** — company, logo/favicon (URL + image when present), colors (swatch + hex), contact, footer
- **Resolved themes** — frontend/admin/staff theme ids, asset profile, theme URLs, on-disk indicator
- **Resolved assets** — client asset base URL, logo/favicon URLs via `ClientAssetResolver`

Preview layout sets `<link rel="icon">` when a favicon URL resolves.

## Out of scope (MC-6A)

- `resources/views/layouts/frontend.blade.php` and operator layouts
- `public/css/` and `public/js/`
- Supplier execution / `PlatformModuleGate`
- Production homepage theme switching

## Verification

```bash
composer dump-autoload
php artisan test --filter=ClientBrandingResolverTest
php artisan test --filter=ClientThemeResolverTest
php artisan test --filter=ClientPreviewRoutingTest
php artisan test --filter=ClientAssetResolverTest
```

Manual (master workspace):

1. `/jetpk/home` — resolved branding, themes, and asset URLs for JetPakistan fixture.
2. `/` — production homepage unchanged; default context remains `haseeb-master`.
3. `/haseeb-master/home` — redirects to `/` (MC-5B).
