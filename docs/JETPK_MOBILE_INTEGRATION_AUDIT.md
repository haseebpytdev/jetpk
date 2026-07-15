# JetPakistan Mobile Integration Audit (MA-0 … MA-6)

**Date:** 2026-07-15  
**Branch:** `jetpk-production`  
**Baseline commit:** `0dade4f` (pre-integration)  
**Integration owner pass:** MA-0 documentation + MA-1 foundation + MA-2–MA-6 theme package from Downloads zips.

## 1. Repository state (pre-change)

| Item | Finding |
|---|---|
| **Branch** | `jetpk-production` (clean working tree) |
| **Recent commits** | Dashboard UI integration, hero LCP fixes, phase 5/6 merge |
| **Mobile shell** | `layouts/mobile-app.blade.php` + `resources/views/mobile/**` (76 Blade files, 50 page views) |
| **Mobile config** | `config/ota-mobile.php` — `mobile_pages` map, UA/cookie preference (no `app_theme` yet) |
| **Desktop theme** | `jetpakistan` under `themes/frontend/jetpakistan` |
| **Mobile theme area** | **Absent** — no `resources/views/themes/mobile/` or `public/themes/mobile/` |
| **RuntimeViewResolver** | Areas: `frontend`, `admin`, `staff`, `customer`, `agent` (no `mobile`) |
| **Asset versions** | `ota-mobile-app.css` / `ota-mobile-app.js` via `ui_asset()` in `mobile-app.blade.php` (no inline `?v=` on layout) |

## 2. Source packages inspected

| Zip | Phase | Contents |
|---|---|---|
| `files (18).zip` | MA-2 | `jetpk-ma2-mobile-app-shell.zip` — theme shell + `app.css` v1 |
| `files (19).zip` | MA-3 | MA-3 CSS layer (public/booking) |
| `files (20).zip` | MA-4 | MA-4 CSS layer (customer portal) |
| `files (21).zip` | MA-5 | MA-5 CSS layer (agent portal) |
| `files (22).zip` | MA-6 | Final compiled package (all layers + QA docs) |
| `m.zip` | MA-0/MA-1 | Foundation docs + `jetpk-ma0-ma1-mobile-foundation.zip` |

**Merge strategy:** Used MA-6 final package (`jetpk-mobile-app-theme-package`) as cumulative source for config, resolver, theme shell, and `app.css` (version 5). Did **not** blind-copy per-phase duplicates.

## 3. Architecture verification (Step 2)

| Requirement | Status |
|---|---|
| Desktop theme remains `jetpakistan` | ✅ Unchanged |
| Mobile theme key is `jetpakistan-app` (not `jetpakistan`) | ✅ Registered in `client_themes.areas.mobile` |
| `RuntimeThemeManager` untouched | ✅ No edits |
| Toggle via `OTA_MOBILE_APP_THEME` / `config('ota-mobile.app_theme')` | ✅ Added |
| `default-mobile` delegates to `layouts.mobile-app` | ✅ Shim at `themes/mobile/default-mobile/layouts/mobile-app.blade.php` |
| Independence from `active_*_theme` | ✅ `resolvedMobileTheme()` reads config only |

## 4. Files integrated

### Application (7 files)

- `config/client_view_paths.php` — `mobile` area + layout audit sample
- `config/client_themes.php` — `mobile` area (`default-mobile`, `jetpakistan-app`)
- `config/ota-mobile.php` — `app_theme` env toggle
- `app/Services/Client/RuntimeViewResolver.php` — `mobile` area + `resolvedMobileTheme()` + legacy prefix fix for `mobile` area

### Theme assets (new)

- `resources/views/themes/mobile/default-mobile/layouts/mobile-app.blade.php`
- `resources/views/themes/mobile/jetpakistan-app/layouts/mobile-app.blade.php`
- `public/themes/mobile/jetpakistan-app/css/app.css` (`$jpMobileAssetVersion = 5`)

### Wiring (required for theme activation)

- **50** `resources/views/mobile/**/*.blade.php` — `@extends('layouts.mobile-app')` → `@extends(client_layout('mobile-app', 'mobile'))`  
  *(Package phases ship skin-only; this one-line wiring activates `client_layout` resolution without changing page content.)*

### Documentation (from MA-6 package)

- `docs/MOBILE-SURFACE-MATRIX-AND-ARCHITECTURE.md`
- `docs/MOBILE-PARITY-REPORT.md`
- `docs/MOBILE-RESPONSIVE-REPORT.md`
- `docs/MOBILE-ACCESSIBILITY-REPORT.md`
- `docs/KNOWN-LIMITATIONS.md`
- `docs/FILE-MANIFEST.md`
- `docs/ASSET-VERSION-MANIFEST.md`

### Tests (proposed, safe)

- `tests/proposed-safe-tests/mobile-*.spec.ts` (5 specs from package)
- `tests/proposed-safe-tests/mobile-integration-screenshots.spec.ts` (integration gate)
- `playwright.mobile-integration.config.ts`

## 5. Explicitly NOT modified

- `app/Services/**` supplier adapters (Sabre, PIA NDC, IATI)
- Booking / payment controllers and services
- `resources/views/layouts/mobile-app.blade.php` (shared shell preserved)
- `public/css/ota-mobile-app.css` / `public/js/ota-mobile-app.js`
- Parwaaz / master branding paths

## 6. Security grep (Step 7)

```
resources/views/themes/mobile  → 0 branding hits
public/themes/mobile           → 1 comment-only hit in app.css ("parwaaz-master" in architecture comment)
```

## 7. Verification results

| Check | Result |
|---|---|
| `php -l` on 4 PHP files | ✅ Pass |
| `php artisan optimize:clear` | ✅ Done |
| `ota:route-page-health-audit --all` | ✅ **pass=55 fail=0 server_errors=0** |
| Supplier/booking mutations | ✅ `supplier_mutation_attempted=false` |
| Theme resolver (default) | `themes.mobile.default-mobile.layouts.mobile-app` |
| Theme resolver (jetpakistan-app) | `themes.mobile.jetpakistan-app.layouts.mobile-app` |
| `client_view('customer.bookings.index','mobile')` | `mobile.customer.bookings.index` |
| Local Playwright screenshots | ✅ 6/6 pass @ 390/430/768 (home + login) |
| Full `php artisan test` | ⚠️ Pre-existing failures (`HaseebMasterRouteSafetyAuditServiceTest`, `Bf7eRetrieveCertPnrSummaryTest` redeclare) — unrelated to this integration |

## 8. Default toggle

`OTA_MOBILE_APP_THEME` **not set** in production env → `default-mobile` → byte-identical to pre-integration mobile UI.

Enable in staging: `OTA_MOBILE_APP_THEME=jetpakistan-app` + `php artisan optimize:clear`.

## 9. Rollback

```env
OTA_MOBILE_APP_THEME=default-mobile
```

Then `php artisan optimize:clear`. No schema migration required.
