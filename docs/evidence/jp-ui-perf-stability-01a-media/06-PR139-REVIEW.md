# PR #139 review residuals

## Start

```text
START_HEAD_SHA=5534bcaf2cb0b622a01a053c8001c806d9aa1884
BASE_MAIN_SHA=f76f772001f652bdbddeb12e735ee7126d450b47
PR_NUMBER=139
```

## Item 1 — backfill client scope

Fixed: `ClientProfileResolver::isDefaultDeploymentSlug()` exact canonical slug (config `client.canonical_client.slug`).

```text
CANONICAL_JETPAKISTAN_PROFILE_ALLOWED=YES
NON_CANONICAL_JETPK_PREFIX_PROFILE_ALLOWED=NO
OTHER_CLIENT_PROFILE_ALLOWED=NO
```

## Item 2 — hero preload

`PublicHero` is a client component; body `<link rel=preload>` was not a reliable initial-document LCP hint.

Fix: server `HomepageContent` calls `react-dom` `preload()` for hero preload entries before paint.

```text
HERO_PRELOAD_MECHANISM=react-dom preload in SSR HomepageContent
CLIENT_BODY_LINK_PRELOAD_REMOVED=YES
```

## Item 3 — support CTA desktop/mobile

CMS still has `support_cta_background` + `support_cta_background_mobile` (admin + Blade).

Presenter now emits `image`, `image_mobile`, `responsive`, `responsive_mobile`.

Next `PublicSupportBanner` illustration remains `lg+` only; desktop asset is the active Next visual source; mobile payload preserved for Blade / legacy compatibility without dual download in the hidden mobile column.

```text
SUPPORT_CTA_DESKTOP_ASSET_CONSUMED=YES
SUPPORT_CTA_MOBILE_ASSET_CONSUMED=EMITTED_FOR_COMPAT_BLADE_PRIMARY
SUPPORT_CTA_MOBILE_LEGACY_COMPATIBILITY=YES
SUPPORT_CTA_DUPLICATE_DOWNLOAD=NO
```

## Item 6–7 — replacement + idempotence

Replacement still deletes previous fingerprint directory when a new fingerprint activates.

Second optimize of the same source skips rewrite when fingerprint + on-disk JPEG variants already match (`unchanged=true`, `writes=false`).

## Item 10 — N+1

`HomepagePublicContentPresenter::present()` loads homepage `ClientPageAsset` rows once into an in-memory map; per-card lookups no longer issue per-asset DB queries.

```text
RESPONSIVE_MEDIA_N_PLUS_ONE=NO
```

## Item 11 — security

Public presenter payloads continue to emit `/storage/...` URLs only (no absolute filesystem paths in `responsive` JSON). Regression covered in large-fixture optimizer URL assertions.

## Size comparison (local noisy JPEG fixtures)

See `06-SIZE-COMPARISON-LARGE.json`.

```text
AVIF_ENCODER_AVAILABLE=YES (function_exists imageavif; noisy fixtures did not retain AVIF after validation in this run)
WEBP_ENCODER_AVAILABLE=YES
HERO_SOURCE≈1.92MB → best WebP≈155KB (≈92% reduction)
CARD_SOURCE≈890KB → best WebP≈20KB
```
