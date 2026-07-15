# FILE-MANIFEST — JetPakistan Mobile App Theme

| File | Phase | Action | Blast radius |
|---|---|---|---|
| `config/client_view_paths.php` | MA-1 | EDIT (+14/−0) | Registers the `mobile` area (`legacy_prefix 'mobile'` ⇒ automatic fallback). Additive |
| `config/client_themes.php` | MA-1 | EDIT (+36/−0) | Registers `default-mobile` + `jetpakistan-app`. Additive |
| `config/ota-mobile.php` | MA-1 | EDIT (+16/−0) | Adds `app_theme` toggle (default `default-mobile`) |
| `app/Services/Client/RuntimeViewResolver.php` | MA-1 | EDIT (+25/−1) | `AREAS += 'mobile'`; `resolvedMobileTheme()`. **`RuntimeThemeManager` untouched** |
| `resources/views/themes/mobile/default-mobile/layouts/mobile-app.blade.php` | MA-1 | NEW | Shim → `@extends('layouts.mobile-app')`. **Byte-identical output** |
| `resources/views/themes/mobile/jetpakistan-app/layouts/mobile-app.blade.php` | MA-2 | NEW | Theme shell — **1:1 structural copy** (0 lines removed) + `jp-app` class + token/app.css links. `$jpMobileAssetVersion = 5` |
| `public/themes/mobile/jetpakistan-app/css/app.css` | MA-2→MA-6 | NEW | The whole skin (~29 KB): shell · public/booking · customer · agent · auth. **0 brand hex · 0 unscoped selectors** |
| `tests/proposed-safe-tests/mobile-theme-foundation.spec.ts` | MA-1 | NEW | Zero-change guard |
| `tests/proposed-safe-tests/mobile-app-shell.spec.ts` | MA-2 | NEW | Toggle, chrome, tap targets, focus |
| `tests/proposed-safe-tests/mobile-public-booking.spec.ts` | MA-3 | NEW | Booking flow (fixture only) |
| `tests/proposed-safe-tests/mobile-customer-portal.spec.ts` | MA-4 | NEW | Stats, chips, targets |
| `tests/proposed-safe-tests/mobile-agent-portal.spec.ts` | MA-5 | NEW | **Money-not-clipped guard** |

**Totals:** 7 application files · 5 specs · 9 documents.
**0 Blade views · 0 controllers · 0 shared/parwaaz files.**

## Not included (deliberately)
Per-page theme views under `themes/mobile/jetpakistan-app/**` — **none were needed.** All 51 mobile
views audited clean (0 `<style>`, 0 `<table>`, 0 fixed widths, already brand-coloured), so the skin
covers them. MA-1's `client_view(…,'mobile')` plumbing stands ready if a future page needs a
**structural** override.
