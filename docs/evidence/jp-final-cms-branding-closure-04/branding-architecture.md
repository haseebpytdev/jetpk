# Branding architecture — Closure-04

## Authority chain

| Stage | Component |
|-------|-----------|
| Dashboard upload | `organization-profile-form.tsx` → `updateOrganizationBrandingMedia()` |
| API | `AgencyBrandingController@update` (`/admin/settings/branding`) |
| Storage | `AgencyBrandingService::uploadMedia()` → `agencies/{id}/branding/*` |
| DB fields | `agency_settings.logo_path`, `agency_settings.favicon_path` |
| Resolver | `JetpkCompanyBrandingResolver` via `ClientProfileConfigReader::loadAgencyBranding()` |
| Public API | `PublicContentApiPresenter::publicConfig()` → `logo_url`, `favicon_url` |
| Next header | `PublicConfigService` → `JetPakistanLogo` → `resolveHeaderLogoUrl()` |
| Favicon metadata | Next layout / Laravel Blade via `favicon_url` |

## Root cause — CMS-BRAND-001

**BRANDING_LOGO_ROOT_CAUSE:** `frontend/lib/branding/resolve-header-logo.ts` intentionally discarded `/storage/` agency logo URLs and always fell back to `/client-assets/jetpk/logo/logo.png`.

**FIX_AUTHORITY:** Accept organization storage URLs in `resolveHeaderLogoUrl`; version storage URLs with `agency_settings.updated_at` via `?v=` in `JetpkCompanyBrandingResolver`.

## Cache invalidation

- **URL_CHANGED_ON_REPLACE:** Often NO (same path overwritten)
- **CACHE_INVALIDATION_MECHANISM:** `?v={agency_settings.updated_at}` on storage logo/favicon URLs; client `PublicConfigService` uses `cache: no-store` in browser and `revalidate: 60` on SSR

## Favicon

- **Supported:** PNG, ICO (`AgencyBrandingController` + dashboard client validation)
- **Unsupported:** JPEG favicon uploads rejected with explicit validation message
