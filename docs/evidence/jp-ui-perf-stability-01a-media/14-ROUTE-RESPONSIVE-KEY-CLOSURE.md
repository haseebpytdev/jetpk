# JP-UI-PERF-STABILITY-01A — Route responsive asset-key closure

## Root cause

`HomepagePublicContentPresenter::guessCardAssetKey()` used only
`JetpkHomepageAssetService::routeAssetKey($id)`, which slugifies to underscores
(`route_seed_khi_dxb`). Production stores hyphenated keys
(`route_seed-khi-dxb`, `route_seed-lhe-jed`) with activated `responsive_media`.

`JetpkHomepageSectionData` already resolved image URLs via
`JetpkHomepageMediaAuthorityCatalog::assetKeyVariants()`; the public-content
responsive attach path did not.

## Fix

`HomepagePublicContentPresenter` now resolves ordered candidates:

1. explicit `image_asset_key` + separator variants
2. generated helper key + variants
3. legacy `route_<literal-id>` (and destination/featured prefixes) + variants

Lookup uses the batched `homepageAssetsByKey` map (no per-card responsive
fallback queries during `present()`). Source-path parity guard retained.

## Tests

`tests/Feature/Jetpk/HomepagePublicContentResponsiveKeyTest.php`

- hyphenated `route_seed-khi-dxb` / `route_seed-lhe-jed`
- underscored `route_seed_khi_ruh`
- destination + featured regressions
- missing metadata fallback
- source_path mismatch guard
- batched load / no responsive whereIn N+1
