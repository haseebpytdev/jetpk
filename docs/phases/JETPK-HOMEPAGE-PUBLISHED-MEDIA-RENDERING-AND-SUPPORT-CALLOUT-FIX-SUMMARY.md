# JETPK-HOMEPAGE-PUBLISHED-MEDIA-RENDERING-AND-SUPPORT-CALLOUT-FIX

## Phase name
JETPK-HOMEPAGE-PUBLISHED-MEDIA-RENDERING-AND-SUPPORT-CALLOUT-FIX

## Branch name
main

## Objective
Fix Support CTA and all homepage CMS media so published uploads render immediately on the public homepage without stale fallbacks, cache clears, or hard refresh.

## Included scope
- Support CTA upload → webroot mirror → publish → public render pipeline
- Destination and Support CTA uploads via `JetpkHomepageAssetService`
- Publish-time asset mirroring for all homepage slots
- URL cache-busting via asset `updated_at`
- Extended `jetpk:homepage-media-audit`
- Focused publication/media tests

## Excluded scope
- Header/hero styling changes
- Booking, supplier, payment logic
- Database migrations / CMS data seeding
- Production deployment

## Investigation findings
1. `JetpkHomepageAssetService` stored files on the public disk but never mirrored them through `ClientPageAssetPublicationService` (unlike `ClientPageAssetService` used by the Media tab).
2. Support CTA frontend requires `background_mode` of `uploaded` or `uploaded_overlay`; uploads via the Support CTA manager left the default `gradient`, so `FALLBACK_ALWAYS_WINS` even when an asset record existed.
3. Homepage assets are profile-scoped in `client_page_assets` (not draft/published rows); publication promotes `content_json` only.
4. No application-level resolver cache for homepage media; stale risk was browser URL reuse and missing webroot mirrors.

## Root causes
- **Primary (Support CTA):** `FALLBACK_ALWAYS_WINS` + `FILE_MISSING_ON_DISK` / `PUBLIC_STORAGE_LINK_MISSING` on split webroot production.
- **Wider pipeline:** `JetpkHomepageAssetService` bypassed webroot publication; publish did not re-mirror assets.

## Files changed
- `app/Services/Homepage/JetpkHomepageAssetService.php`
- `app/Services/Client/ClientPageAssetService.php`
- `app/Services/Client/ClientPageAssetPublicationService.php`
- `app/Services/Client/ClientPageContentResolver.php`
- `app/Http/Controllers/Admin/ClientPageSettingsController.php`
- `app/Support/Audits/JetpkHomepageContentAuditService.php`
- `app/Console/Commands/JetpkHomepageMediaAuditCommand.php`
- `tests/Feature/Jetpk/JetpkHomepagePublishedMediaTest.php`

## Tests executed
- `php artisan test --filter=JetpkHomepagePublishedMediaTest` (6 tests, 23 assertions)
- `php artisan test --filter=JetpkHomepagePublishedMediaTest|JetpkHomepageMediaTest|ClientPageAssetServiceTest|HomepageDraftPublishPipelineTest` (23 tests, 75 assertions)
- `php artisan jetpk:homepage-customization-coverage-audit` (fail=0)
- `php artisan jetpk:homepage-content-audit --profile=jetpk` (fail_count=0)
- `php artisan jetpk:homepage-media-audit --profile=jetpk` (fail_count=0)
- `php artisan ota:route-page-health-audit --all`
- `php artisan view:cache` / `view:clear`

## Final status
Code fix complete. Production may require one-time mirror of existing Support CTA files after upload.
