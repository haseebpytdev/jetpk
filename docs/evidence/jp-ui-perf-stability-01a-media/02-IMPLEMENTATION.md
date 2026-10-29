# Implementation

## Architecture

Chose **B**: shared `JetpkResponsiveImageOptimizer` with profiles; `JetpkHeroImageOptimizer` remains a thin BC facade.

Profiles:

| Profile | Widths | Output dir |
|---------|--------|------------|
| hero | desktop 1200 / tablet 1024 / mobile 768 | `.../lcp/{fingerprint}` |
| card | 320 / 480 / 640 / 768 | `.../card/{fingerprint}` |
| support-background | 480 / 768 / 1024 / 1280 | `.../support/{fingerprint}` |

Formats: AVIF (if GD), WebP (if GD), JPEG fallback. No upscaling beyond source width. Fingerprinted directories. Originals retained.

## Backend

- CMS scoped uploads (`JetpkHomepageAssetService`) generate `meta_json.responsive_media` + publish derivatives.
- Hero continues via `ClientPageAssetService` → `hero_lcp`.
- `HomepagePublicContentPresenter` emits additive `responsive` objects for hero/cards/support.
- Command: `php artisan jetpk:homepage-media-optimize --dry-run` (JetPakistan-scoped, idempotent).

## Frontend

- `ResponsiveCmsPicture` homepage-scoped `<picture>` consumer.
- Mappers accept optional `responsive` without breaking `image: string`.
- Global `ImageSlot.shouldBypassNextOptimizer` unchanged.
