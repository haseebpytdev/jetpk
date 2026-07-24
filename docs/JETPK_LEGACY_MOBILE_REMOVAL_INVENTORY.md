# JETPK Legacy Mobile-App Removal Inventory

Branch: `fix/jetpk-canonical-responsive-ui-cms`
Baseline: `5f97dc6c512d59ac2106c2bfa9854da2dc1210c8`

## Classification legend

| Code | Meaning |
|------|---------|
| `REMOVE_RUNTIME_CONNECTION` | Config, resolver, provider wiring for mobile-app shell |
| `REMOVE_VISIBLE_CONTROL` | Floating Mobile App / Desktop toggle UI |
| `REMOVE_ROUTE_SUBSTITUTION` | Controller branching to `mobile.*` blades |
| `REMOVE_SESSION_OR_COOKIE_MODE` | Cookie/session/query/UA/viewport mode switching |
| `KEEP_RESPONSIVE_CSS` | Normal `@media` responsive styling in shared public CSS |
| `KEEP_NON_THEME_MOBILE_BEHAVIOR` | Viewport/field logic unrelated to dual-shell theme |
| `TEST_ONLY` | Tests/docs/playwright for mobile-app shell |
| `UNRELATED` | Different system (UiVersion v1/v2 preview lane) |

## Removed runtime connections

| Path | Classification | Action |
|------|----------------|--------|
| `config/ota-mobile.php` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `app/Support/Ui/MobileViewPreference.php` | REMOVE_SESSION_OR_COOKIE_MODE | Deleted |
| `app/Http/Controllers/Frontend/MobileViewController.php` | REMOVE_SESSION_OR_COOKIE_MODE | Deleted |
| `resources/views/mobile/**` (81 blades) | REMOVE_ROUTE_SUBSTITUTION | Deleted |
| `resources/views/layouts/mobile-app.blade.php` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `resources/views/themes/mobile/**` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `public/css/ota-mobile-app.css` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `public/js/ota-mobile-app.js` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `public/css/v2/ota-mobile-app-v2.css` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `public/js/v2/ota-mobile-app-v2.js` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `public/themes/mobile/**` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `config/client_view_paths.php` mobile area | REMOVE_RUNTIME_CONNECTION | Removed |
| `config/client_themes.php` mobile themes | REMOVE_RUNTIME_CONNECTION | Removed |
| `config/client.php` `canonical_client.mobile_theme` | REMOVE_RUNTIME_CONNECTION | Removed |
| `config/client_ui.php` mobile-app asset maps | REMOVE_RUNTIME_CONNECTION | Removed |
| `app/Providers/AppServiceProvider.php` mobile composers | REMOVE_RUNTIME_CONNECTION | Removed |
| `app/Services/Client/RuntimeViewResolver.php` mobile area | REMOVE_RUNTIME_CONNECTION | Removed |
| 27 controllers `shouldUseMobileShell()` branches | REMOVE_ROUTE_SUBSTITUTION | Removed |

## Removed visible controls

| Path | Classification | Action |
|------|----------------|--------|
| `resources/views/themes/frontend/jetpakistan/partials/mobile-app-view-link.blade.php` | REMOVE_VISIBLE_CONTROL | Deleted |
| `resources/views/layouts/partials/desktop-mobile-link.blade.php` | REMOVE_VISIBLE_CONTROL | Deleted |
| `resources/views/layouts/partials/mobile-app-desktop-link.blade.php` | REMOVE_VISIBLE_CONTROL | Deleted |
| `resources/views/layouts/partials/mobile-viewport-reconcile.blade.php` | REMOVE_SESSION_OR_COOKIE_MODE | Deleted |
| `resources/views/layouts/partials/mobile-app-top-bar.blade.php` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `resources/views/layouts/partials/mobile-app-bottom-nav.blade.php` | REMOVE_RUNTIME_CONNECTION | Deleted |
| `routes/web.php` view-preference routes | REMOVE_SESSION_OR_COOKIE_MODE | Removed |

## Kept (responsive only)

| Path | Classification | Notes |
|------|----------------|-------|
| `public/css/ota-public.css` `.ota-mobile-home-*` blocks | KEEP_RESPONSIVE_CSS | Breakpoint layout for canonical homepage |
| `resources/views/frontend/partials/ota-hero-flight-search.blade.php` `isMobileDateSheet()` | KEEP_NON_THEME_MOBILE_BEHAVIOR | Date picker UX |
| `public/themes/frontend/jetpakistan/js/passengers.js` `isMobileViewport()` | KEEP_NON_THEME_MOBILE_BEHAVIOR | Picker behavior |
| `app/Http/Middleware/UiVersionRoutePrefixMiddleware.php` | UNRELATED | v1/v2 preview only |
| `app/Http/Middleware/ResolveClientUiVersion.php` | UNRELATED | v1/v2 preview only |

## Test artifacts updated

| Path | Classification | Action |
|------|----------------|--------|
| `tests/Feature/Ui/MobileViewPreferenceTest.php` | TEST_ONLY | Deleted |
| `tests/Unit/Ui/MobileViewportPreferenceTest.php` | TEST_ONLY | Deleted |
| `tests/proposed-safe-tests/mobile-*.spec.ts` | TEST_ONLY | Left as historical; not run in gate |
| `tests/Feature/Jetpk/JetpkCanonicalResponsiveUiTest.php` | TEST_ONLY | Added |

## Homepage/CMS corrections (same phase)

| Item | Action |
|------|--------|
| `HomeController::shouldUseJetPakistanThemeHome()` preview gate | Removed — `/` always renders CMS section stack |
| Hero CTA buttons (`Search flights`, `Group fares`) | Removed from public hero + Admin fields |
| `HomepageContentNormalizer` hero CTA keys | Stripped on read as deprecated |
| `ClientPageAdminContentResolver` | Normalizes home content for Admin form parity |
| Canonical email default | `ota@jetpakistan.pk` in `ota-brand` / `ota-client` |
