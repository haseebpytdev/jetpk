# JP-UI-PERF-STABILITY-01A — SUMMARY

## Status

`JP_UI_PERF_STABILITY_01A_MEDIA_PR_READY_FOR_REVIEW`

## What shipped

1. Shared `JetpkResponsiveImageOptimizer` (hero/card/support-background profiles).
2. CMS upload path generates fingerprinted AVIF/WebP/JPEG derivatives + `responsive_media` manifest.
3. Hero BC facade retained (`JetpkHeroImageOptimizer` → shared core; `hero_lcp` unchanged for Blade).
4. Additive `responsive` fields on homepage JSON for Next.
5. Homepage-scoped `ResponsiveCmsPicture` consumption (no global ImageSlot change).
6. `php artisan jetpk:homepage-media-optimize --dry-run` JetPakistan-scoped backfill command.

## Next action

Review PR → controlled production deploy → dry-run then write-mode media backfill UAT → GTmetrix remeasure.
