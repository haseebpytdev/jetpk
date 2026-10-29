# JP-UI-PERF-STABILITY-01A — Production media backfill write + live certification

```text
APPLICATION_SHA=321be5673036912868f2846a1f51322854bd157e
PUBLIC_FRONTEND_SHA=43f5a3beb9a8e88567cf49bef76853f49b55e0c6
MERGED_PR139_MAIN_SHA=998413b18db442658f1635ff1134dc08a0c27901 (not stamped)
EXISTING_APP_BACKUP=/home/pkjetp/backups/jetpk_app-20261029T134124Z.tar.gz
PREV_NEXT_BAK=/home/pkjetp/jetpk_app/frontend/.next.bak-pr139-20261029T135510Z
ASSET_METADATA_SNAPSHOT=/home/pkjetp/backups/pr139-homepage-assets-pre-backfill-20261029T143201Z.json
```

## Component deploy fallback (prior phase; unchanged)

`jetpk-deploy.sh` initial rsync encountered `chgrp` failure on root-owned `app/Services`.
Exact seven staged Laravel blobs were installed through the allowlisted install path.
Final ownership PASS; final blob parity PASS; component provenance PASS.
Not rewritten as a clean wrapper deploy.

## Phase 1 — provenance

```text
APPLICATION_SHA=321be5673036912868f2846a1f51322854bd157e
PUBLIC_FRONTEND_SHA=43f5a3beb9a8e88567cf49bef76853f49b55e0c6
```

## Phase 2 — skipped asset

```text
SKIPPED_ASSET_KEY=hero_background_mobile
SKIPPED_ASSET_REASON=no_media_profile (PROFILE=NULL)
SKIPPED_ASSET_IS_ONE_OF_EXPECTED_14=NO
```

## Phase 3–4 — snapshots

```text
ASSET_METADATA_SNAPSHOT=PASS
ASSET_METADATA_SNAPSHOT_ROWS=15
ASSET_METADATA_SNAPSHOT_PATH=/home/pkjetp/backups/pr139-homepage-assets-pre-backfill-20261029T143201Z.json
PREBACKFILL_DERIVATIVE_FILE_COUNT=21
PREBACKFILL_DERIVATIVE_BYTES=34450220
```

Pre-existing tree included `pages/home/lcp/7fe81ea32316e854/` hero derivatives.

## Phase 5–7 — dry-run + write + activation

Final pre-write dry-run:

```text
processed=14 skipped=1 writes=no
MISSING_SOURCE_ASSETS=0
```

Write mode:

```text
processed=14 skipped=1 writes=yes
WRITE_MODE_EXIT_CODE=0
ELIGIBLE_ASSETS_PROCESSED=14
ASSETS_ACTIVATED_YES=14
ASSETS_ACTIVATED_NO=0
```

All 14 `RUN` lines reported `activated=yes`. Encoders observed: JPEG + WebP (no AVIF).

## Phase 8–11 — originals / manifests / files / HTTP

```text
ORIGINAL_FILES_DELETED=0
ORIGINAL_PUBLIC_URLS_CHANGED=0
HERO_MANIFEST_ACTIVE=YES
NON_HERO_RESPONSIVE_MANIFESTS_ACTIVE=13
TOTAL_ACTIVE_RESPONSIVE_MANIFESTS=14
RESPONSIVE_MEDIA_WARNING_COUNT=0
HERO_LCP_WARNING=NO
PRODUCTION_JPEG_ENCODER=YES
PRODUCTION_WEBP_ENCODER=YES
PRODUCTION_AVIF_ENCODER=NO
POSTBACKFILL_DERIVATIVE_FILE_COUNT=101
POSTBACKFILL_DERIVATIVE_BYTES=38087996
TOTAL_GENERATED_DERIVATIVE_BYTES=3637776   # tree delta vs pre-backfill
MANIFEST_DERIV_BYTES=5878117               # all listed derivative payloads
DERIVATIVE_PUBLICATION=PASS
DERIVATIVE_HTTP_404_COUNT=0
```

Representative HTTP 200 checks: hero/support/route/destination/featured JPEG+WebP.

## Phase 12 — Next homepage revalidation

Invoked `NextPublicCacheRevalidator::revalidateHomepage()` via helpers (secret not logged).

```text
NEXT_HOMEPAGE_REVALIDATION=PASS
ok=true
endpoints: revalidate-public-content status=200; revalidate/seo status=200
```

## Phase 13 — public content API

`GET https://jetpakistan.pk/api/public/content/homepage` → HTTP 200, `source=cms`.

```text
API_HERO_RESPONSIVE=YES
API_DESTINATION_RESPONSIVE_COUNT=4
API_FEATURED_RESPONSIVE_COUNT=3
API_SUPPORT_DESKTOP_RESPONSIVE=YES
API_SUPPORT_MOBILE_RESPONSIVE=YES
API_ROUTE_RESPONSIVE_COUNT=2
```

### Consumption blocker (not a backfill activation failure)

DB rows exist and are activated for hyphenated keys:

- `route_seed-khi-dxb` → fingerprint `2f5f8ced8f55a2d6`
- `route_seed-lhe-jed` → fingerprint `269ea6178cd31fa6`

`HomepagePublicContentPresenter::guessCardAssetKey()` resolves routes via
`JetpkHomepageAssetService::routeAssetKey($id)` → `Str::slug(..., '_')`, producing
`route_seed_khi_dxb` / `route_seed_lhe_jed`, which **do not** match the stored
`asset_key` values. Manifest lookup therefore returns null for those two items, so
the API still serves the original PNG URLs without `responsive.sources`.

Underscored route keys `route_seed_isb_lhr` / `route_seed_khi_ruh` map correctly.

## Phase 14–19 — live Playwright (`13-live-network-evidence.json`)

Viewports 390 / 768 / 1440 against `https://jetpakistan.pk` (clean Chromium contexts).

```text
HERO_PRELOAD_INITIAL_DOCUMENT=YES
HERO_CURRENT_SRC_IS_DERIVATIVE=YES
HERO_ORIGINAL_SELECTED=NO
HERO_DUPLICATE_TRANSFER=NO
MOBILE_AND_DESKTOP_HERO_BOTH_TRANSFERRED=NO
DESTINATION_CURRENT_SRC_IS_DERIVATIVE=YES
FEATURED_CURRENT_SRC_IS_DERIVATIVE=YES (card webp fingerprints observed)
ROUTE_CURRENT_SRC_IS_DERIVATIVE=NO  # representative first route still original PNG
BELOW_FOLD_LAZY_LOADING=PASS
SUPPORT_DESKTOP_DERIVATIVE_SELECTED=YES (1440 webp)
SUPPORT_DUPLICATE_DESKTOP_MOBILE_TRANSFER=NO
GIANT_ORIGINAL_SELECTED_WHEN_DERIVATIVE_AVAILABLE=NO  # destination_seed_dxb not transferred as PNG
```

Unique image transfer (deduped URLs; 1440):

```text
TOTAL_IMAGE_TRANSFER_BYTES≈4305626
TOTAL_PNG_TRANSFER_BYTES≈3942512   # dominated by the two unmapped route originals
TOTAL_WEBP_TRANSFER_BYTES≈363114
TOTAL_JPEG_TRANSFER_BYTES≈0        # browser preferred webp where srcset present
TOTAL_AVIF_TRANSFER_BYTES=0
LARGEST_IMAGE_TRANSFER_KEY=route_seed_lhe_jed original PNG
LARGEST_IMAGE_TRANSFER_BYTES=1988311
PRE_PHASE_A_TOTAL_PAGE_BYTES=29325000
PRE_PHASE_A_PNG_BYTES=28927000
TOTAL_PAGE_REDUCTION_PERCENT≈85.3   # vs unique ~4.3MB image-dominated page
IMAGE_TRANSFER_REDUCTION_PERCENT≈85.1
```

Primary ≤5MB page target: **met on unique-transfer basis** (~4.3MB images), but blocked from full certification because two route cards still select originals when derivatives exist in storage/DB.

## Phase 20–21 — visual / health

Hero/search/routes/destinations/featured/support render with JetPakistan branding.
No horizontal overflow in Playwright viewports. Some lazy imgs reported incomplete mid-scroll in IDE browser (measurement artifact); Playwright 1440 `brokenImages=0`.

Component markers unchanged after write. Supplier/booking/payment mutations: 0.

## Final status

```text
FINAL_STATUS=JP_UI_PERF_STABILITY_01A_LIVE_CONSUMPTION_BLOCKED
NEXT_RECOMMENDED_ACTION=FIX_ROUTE_ASSET_KEY_LOOKUP_FOR_HYPHENATED_KEYS_THEN_REVALIDATE_AND_RECERT_BROWSER
```

Authorized backfill write succeeded (14/14 activated). Live consumption is blocked by presenter key slug mismatch for 2/4 routes only — out of scope for this media-write loop (no app code deploy authorized here).
