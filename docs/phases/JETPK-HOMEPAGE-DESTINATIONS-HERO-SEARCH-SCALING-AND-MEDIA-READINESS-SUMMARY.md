# JETPK-HOMEPAGE-DESTINATIONS-HERO-SEARCH-SCALING-AND-MEDIA-READINESS

- **Phase name:** JETPK-HOMEPAGE-DESTINATIONS-HERO-SEARCH-SCALING-AND-MEDIA-READINESS
- **Branch:** main
- **Objective:** Homepage header breathing room, CMS hero/search sizing controls, compact search baseline, centered compact destination cards, destination image resolver alignment.
- **Final status:** READY_FOR_MANUAL_UPLOAD

## Included scope

- Header top offset via `--jp-header-top-offset` on `.jp-home .jp-site-header`
- Hero typography CMS sliders (`hero.eyebrow_size`, `headline_size`, `highlight_size`, `subtitle_size`)
- Search UI CMS slider (`hero.search_ui_scale`) with token-driven proportional scaling (no `transform: scale()`)
- Default compact search baseline at CMS 100% = `0.90` legacy token multiplier
- Centered destination grid (`repeat(4, minmax(0, 280px))`, max card width 310px)
- Canonical destination asset key helper `JetpkHomepageAssetService::destinationAssetKey()`
- Resolver chain uses `image_asset_key` → `destination_{slug(id)}` → `destination_{raw id}` → `destination_{n}`

## Excluded scope

- Supplier/booking/search execution logic
- Support CTA media pipeline
- Production CMS mutation
- Deployment

## Tests executed

- `tests/Unit/Support/Client/Homepage/JetpkHomepageHeroSizingTest.php` (4 tests)
- `tests/Feature/Jetpk/JetpkHomepageSizingTest.php` (7 tests)
- `php artisan jetpk:homepage-customization-coverage-audit` fail=0
- `php artisan jetpk:homepage-content-audit --profile=jetpk` fail_count=0
- `php artisan jetpk:homepage-media-audit --profile=jetpk` fail_count=0
- `php artisan ota:route-page-health-audit --all` fail=0 server_errors=0
