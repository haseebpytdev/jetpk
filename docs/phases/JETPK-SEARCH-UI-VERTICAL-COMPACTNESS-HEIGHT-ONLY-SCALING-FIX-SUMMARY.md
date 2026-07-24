# JETPK-SEARCH-UI-VERTICAL-COMPACTNESS-HEIGHT-ONLY-SCALING-FIX

- **Phase name:** JETPK-SEARCH-UI-VERTICAL-COMPACTNESS-HEIGHT-ONLY-SCALING-FIX
- **Branch:** main
- **Previous SHA:** 91670e7
- **Objective:** Scale homepage Search UI vertical compactness only; width and grid layout remain responsive-invariant across slider values.
- **Final status:** READY_FOR_MANUAL_UPLOAD

## Width contract

- **Before (848d5d6):** `--jp-search-box-max-width: min(100%, calc(1160px × scale))` — slider changed outer width.
- **After:** No `--jp-search-box-max-width`; shell uses `max-width: 100%` and existing hero/wrap layout. Horizontal padding (`card-padding-x`, `field-padding-x`, `btn-padding-x`) and fields grid gap are fixed.

## Scale contract

`--jp-search-ui-scale = CMS% / 100` (no hidden 0.90 multiplier).

## Files changed

- `app/Support/Client/Homepage/JetpkHomepageHeroSizing.php`
- `app/Support/Client/Homepage/HomepageCanonicalSchema.php`
- `public/themes/frontend/jetpakistan/css/tokens.css`
- `public/themes/frontend/jetpakistan/css/theme.css`
- `public/themes/frontend/jetpakistan/css/jp-search.css`
- `resources/views/themes/admin/jetpakistan/page-settings/partials/home-sections.blade.php`
- `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php` (v58)
- `playwright.jetpk-search-ui-vertical-scale.config.ts`
- `tests/Unit/Support/Client/Homepage/JetpkHomepageHeroSizingTest.php`
- `tests/Feature/Jetpk/JetpkSearchUiVerticalScaleContractTest.php`
- `tests/Feature/Jetpk/JetpkHomepageSizingTest.php`
- `tests/playwright/jetpk/homepage-search-ui-vertical-scale.spec.ts`

## Playwright metrics (1440×900)

| Metric | 80% | 90% | 100% | 115% |
|--------|-----|-----|------|------|
| Outer width | 1391.75px | 1391.75px | 1391.75px | 1391.75px |
| Outer height | 203px | 226px | 249px | 283px |
| Top padding | 14.4px | 16.2px | 18px | 20.7px |
| Bottom padding | 14.4px | 16.2px | 18px | 20.7px |
| Field height | 59.6px | 66.8px | 74.0px | 84.8px |
| Search button | 41.3px | 45.9px | 51.0px | 58.6px |
| Tab height | 28.8px | 32.4px | 36.0px | 41.4px |
| Swap (fixed) | 38px | 38px | 38px | 38px |
| Swap icon | 12.8px | 14.4px | 16.0px | 18.4px |
| Fields row gap | 12px | 12px | 12px | 12px |
| First field width | 343.03px | 343.03px | 343.03px | 343.03px |

Screenshots: `tests/playwright/artifacts/homepage-search-ui-vertical-scale/`
