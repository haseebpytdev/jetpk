# Phase 0 — Production homepage media contract

## End-to-end path

```text
CMS upload (Admin ClientPageSettingsController)
  → JetpkHomepageAssetService::store* / ClientPageAssetService::store (hero)
  → ClientPageAsset (disk=public, path, public_url, meta_json)
  → ClientPageAssetPublicationService::publish* → public/storage + client-assets
  → HomepagePublicContentPresenter (Laravel JSON for Next)
  → frontend HomepageContentService mapHero/mapRoutes/mapDestinations/mapFeaturedDeals/mapSupportCta
  → PublicHero / RoutesSection / DestinationsSection / FeaturedOffersSection / PublicSupportBanner
  → browser image request (/storage/... or static /images/home/...)
```

## Findings

1. **URL emission**
   - Hero: `HomepagePublicContentPresenter::presentHero` → `image.url` from `hero_background`.
   - Routes/destinations/deals: string `image` from `JetpkHomepageSectionData` asset keys.
   - Support CTA: string `image` from `support_cta_background` (Next uses `<img>` / picture, not CSS background).

2. **Hero responsive manifest**
   - Generated into `meta_json.hero_lcp` by `JetpkHeroImageOptimizer` on hero upload.
   - Consumed by Blade `resources/views/themes/frontend/jetpakistan/sections/hero.blade.php` via `JetpkHeroLcpPresenter`.
   - **Not previously emitted** on the Next JSON payload — PublicHero only received original `/storage/...` URL.

3. **Card/support metadata**
   - `storeScopedImage()` retained original only; no `responsive_media` until this phase.

4. **Production homepage composition**
   - Next: `frontend/features/home/components/HomepageContent.tsx` → public-visual sections.
   - Legacy Blade homepage remains for themed Laravel rendering; public production primary path is Next on `jetpakistan.pk`.

5. **`/storage/` routing**
   - Laravel `public/storage` symlink + publication copies; Next `ImageSlot` sets `unoptimized` for `/storage/` (intentionally preserved).

6. **Support CTA**
   - Production Next uses an `<img>` (now ResponsiveCmsPicture), not CSS `background-image`.
   - Desktop asset key `support_cta_background`; mobile key exists for CMS but Next previously used a single image field.

## Root cause

Homepage photographic CMS assets (especially non-hero cards) were published as original giant PNG/JPEG URLs. Hero derivatives existed for Blade only. Next bypassed Next image optimization for `/storage/` and downloaded originals — ~98.6% of homepage transfer.
