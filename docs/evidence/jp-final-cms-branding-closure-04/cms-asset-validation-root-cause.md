# CMS asset validation — root cause

## CMS_ASSET_ROOT_CAUSE

Primary defects identified:

1. **ASSET_KEY** — Dashboard `routeAssetKey()` sent `route_{rawId}` while backend `JetpkHomepageAssetService::routeAssetKey()` slugifies IDs (`route_route_lhe_dxb` vs `route_route-lhe-dxb`). Destination/deal keys already slugified; routes did not.
2. **REQUEST_VALIDATION_MESSAGE** — Failed uploads surfaced generic `"Asset validation failed."` instead of the specific Laravel rule (size/MIME).

File size was **not** the blocker at the Laravel layer (`max:5120` KB = 5 MB). Local test `JetpkHomepageCmsAssetUploadTest::test_two_point_two_five_megabyte_jpeg_upload_is_accepted` PASS.

## Upload path

| Layer | Authority |
|-------|-----------|
| UI | `homepage-settings-panel.tsx` → `uploadPageSettingsAsset()` |
| Route | `POST /admin/page-settings/home/assets` |
| Controller | `ClientPageSettingsController::storeAsset` |
| Validator | `file`: mimes jpg,jpeg,png,webp; max 5120 KB |
| Service | `ClientPageAssetService::store` |
| Record | `client_page_assets` keyed by `asset_key` |

## Limits

| Layer | Value |
|-------|-------|
| LARAVEL_IMAGE_MAX | 5120 KB (5 MB) |
| EFFECTIVE_IMAGE_MAX | 5 MB (when PHP/post limits ≥ 5 MB) |

## Fixes applied

- `routeAssetKey()` uses `slugifyAssetId()` (matches backend)
- Specific validation messages for oversize / invalid MIME
- Dashboard surfaces first field error from API `errors`
