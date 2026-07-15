# Runtime client asset resolution (MC-5A)

How the OTA resolves the active client profile and public asset URLs at runtime.
Complements [master-preview-routing.md](master-preview-routing.md) and
[client-deployment-architecture.md](client-deployment-architecture.md).

## Default vs preview context

| Mode | Trigger | `CurrentClientContext::isPreview()` | Profile source |
|------|---------|-------------------------------------|----------------|
| **Default** | Any request (lazy on first accessor call) | `false` | `ClientProfileResolver::resolveDefault()` — `config('ota_client.slug')` or **`haseeb-master`** |
| **Preview** | `/{clientSlug}/*` routes via `ResolvePreviewClient` | `true` | Active DB profile for route slug |

Root production routes (`/`, `/admin`, `/login`, etc.) are unchanged. The default
profile makes `haseeb-master` the implicit client on `ota.haseebasif.com` without
requiring `/haseeb-master/` in normal URLs. The `/haseeb-master/*` prefix remains
available for debug only.

## Preview slug base route

| Route | Name | Behavior |
|-------|------|----------|
| `GET /{clientSlug}` | `client.preview.root` | Redirects to `/{clientSlug}/home` when slug is active |

Example: `/jetpk` → `/jetpk/home`.

## Reserved slugs

Preview `{clientSlug}` cannot match these exact segments (see
`App\Support\Client\ReservedClientPreviewSlugs`):

`admin`, `staff`, `agent`, `dev`, `login`, `register`, `booking`, `bookings`,
`groups`, `api`, `storage`, `css`, `js`, `images`, `assets`, `build`, `vendor`,
`client-assets`, `themes`

This prevents preview routes from intercepting existing platform path prefixes.

## ClientAssetResolver

Service: `App\Services\Client\ClientAssetResolver`

| Method | Returns |
|--------|---------|
| `activeTheme()` | Frontend theme id (`v1-classic`, `v2-modern`, …) |
| `activeAssetProfile()` | Folder name under `public/client-assets/` |
| `frontendThemePath()` | Relative path `themes/frontend/{theme}/` |
| `frontendThemeUrl()` | Full public URL for theme directory |
| `clientAssetPath($path)` | Relative path under client assets |
| `clientAssetUrl($path)` | Full public URL for a client asset |
| `logoUrl()` | Logo URL from branding `logo_path`, or `null` |
| `faviconUrl()` | Favicon URL from branding `favicon_path`, or `null` |
| `bannerUrl($name)` | URL for `banners/{name}` under asset profile, or `null` |

### Source selection

- **Preview routes** (`isPreview()` true): theme, asset profile, and branding paths from the preview DB profile.
- **All other routes**: `config('ota_client')` + `ClientProfileConfigReader::brandingFromConfig()` — even when `CurrentClientContext` has lazily resolved the default DB profile.

Production layouts use Agency branding by default. When **`is_client_preview()`** is true,
**`ClientPreviewLayoutBranding`** overrides public/dashboard layout variables from
**`client_branding()`** / **`client_theme()`** (logo, favicon, CSS variables, footer
contact, theme meta tags). Root `/` and non-preview routes unchanged.

### Path conventions

| Asset type | Relative path pattern |
|------------|----------------------|
| Frontend theme | `themes/frontend/{activeTheme}/` |
| Client assets | `client-assets/{activeAssetProfile}/{path}` |
| Logo / favicon | Branding-relative paths via `clientAssetUrl()` |
| Banner | `banners/{name}` via `bannerUrl($name)` |

When `activeAssetProfile()` is empty, `clientAssetPath()` returns the path unchanged (preserves shared `public/css/` / `public/js/` behavior).

On-disk existence checks (diagnostics, theme registry) use **`ClientPublicWebrootPath`**
(`config('ota_client.public_webroot_path')` / `OTA_PUBLIC_WEBROOT_PATH`, falling back to
`public_path()`). URL generation via `asset()` is unchanged.

Shared platform CSS/JS under `public/css/` and `public/js/` are **not** modified in MC-5A.

## Preview UI

Placeholder preview pages (`resources/views/preview/client/`) include a **Resolved assets** section showing theme URL, client asset base URL, and logo/favicon URLs when available.

## Out of scope (MC-5A)

- Full JetPakistan theme assets under `public/themes/frontend/jetpakistan/` (JETPK phase 2)
- Supplier execution / `PlatformModuleGate` changes
- Edits to `public/css/` or `public/js/`

## Verification

```bash
php artisan ota:seed-jetpakistan-client-profile
php artisan ota:client-preview-runtime-status --client=jetpk
php artisan route:list --name=client.preview
php artisan test --filter=ClientPreviewRoutingTest
php artisan test --filter=ClientAssetResolverTest
```

Manual (master workspace):

1. `/jetpk` redirects to `/jetpk/home` and shows resolved asset URLs on the context card.
2. `/` still serves the production homepage; default context resolves `haseeb-master` when that profile exists.
3. `/admin/home` returns 404 (reserved slug — not a client preview path).
