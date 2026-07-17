# JetPK Homepage CMS — Exact Runtime Manifest

**Integration HEAD:** 824ab74 | **Baseline:** 624f3dd | **Changed files:** 63

## Summary counts

| Class | Count |
|-------|------:|
| A | 34 |
| B | 3 |
| C | 2 |
| D | 21 |
| E | 3 |

## Per-file manifest

| Status | Path | Class | Subsystem | Runtime | Production target | Public mirror | Migration | Repo-only | Reason |
|--------|------|-------|-----------|---------|-------------------|---------------|-----------|-----------|--------|
| M | `app/Http/Controllers/Admin/ClientPageSettingsController.php` | A | controllers | yes | /home/pkjetp/jetpk_app/app/Http/Controllers/Admin/ClientPageSettingsController.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `app/Http/Controllers/Frontend/HomeController.php` | A | controllers | yes | /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/HomeController.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Models/ClientPageSettingDefault.php` | A | models | yes | /home/pkjetp/jetpk_app/app/Models/ClientPageSettingDefault.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Models/ClientPageSettingRevision.php` | A | models | yes | /home/pkjetp/jetpk_app/app/Models/ClientPageSettingRevision.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `app/Services/Client/ClientPageContentResolver.php` | A | services | yes | /home/pkjetp/jetpk_app/app/Services/Client/ClientPageContentResolver.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Services/Client/ClientPageResetService.php` | A | services | yes | /home/pkjetp/jetpk_app/app/Services/Client/ClientPageResetService.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Services/Client/ClientPageSettingDefaultService.php` | A | services | yes | /home/pkjetp/jetpk_app/app/Services/Client/ClientPageSettingDefaultService.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Services/Client/ClientPageSettingRevisionService.php` | A | services | yes | /home/pkjetp/jetpk_app/app/Services/Client/ClientPageSettingRevisionService.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `app/Services/Homepage/JetpkHomepageContentValidator.php` | A | services | yes | /home/pkjetp/jetpk_app/app/Services/Homepage/JetpkHomepageContentValidator.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `app/Support/Audits/JetpkHomepageCustomizationCoverageAuditService.php` | A | support | yes | /home/pkjetp/jetpk_app/app/Support/Audits/JetpkHomepageCustomizationCoverageAuditService.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Support/Client/Homepage/HomepageCanonicalSchema.php` | A | support | yes | /home/pkjetp/jetpk_app/app/Support/Client/Homepage/HomepageCanonicalSchema.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Support/Client/Homepage/HomepageContentNormalizer.php` | A | support | yes | /home/pkjetp/jetpk_app/app/Support/Client/Homepage/HomepageContentNormalizer.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Support/Client/Homepage/HomepageSectionOrderResolver.php` | A | support | yes | /home/pkjetp/jetpk_app/app/Support/Client/Homepage/HomepageSectionOrderResolver.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `app/Support/Client/Homepage/JetpkHomepageContextDiagnostic.php` | A | support | yes | /home/pkjetp/jetpk_app/app/Support/Client/Homepage/JetpkHomepageContextDiagnostic.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `app/Support/Client/JetpkHomepageSectionData.php` | A | support | yes | /home/pkjetp/jetpk_app/app/Support/Client/JetpkHomepageSectionData.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `config/jetpk_homepage.php` | A | config | yes | /home/pkjetp/jetpk_app/config/jetpk_homepage.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `config/ota-mobile.php` | A | config | yes | /home/pkjetp/jetpk_app/config/ota-mobile.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `database/migrations/2026_07_16_120000_create_client_page_setting_revisions_table.php` | C | migrations | yes | /home/pkjetp/jetpk_app/database/migrations/2026_07_16_120000_create_client_page_setting_revisions_table.php | no | yes | no | Additive migration; run after code upload with approval |
| A | `database/migrations/2026_07_16_130000_create_client_page_setting_defaults_table.php` | C | migrations | yes | /home/pkjetp/jetpk_app/database/migrations/2026_07_16_130000_create_client_page_setting_defaults_table.php | no | yes | no | Additive migration; run after code upload with approval |
| A | `docs/JETPK_HOMEPAGE_CMS_FINAL_ACCEPTANCE_CURRENT.md` | E | docs | no | — | no | no | yes | Repository documentation; optional on server |
| A | `docs/JETPK_HOMEPAGE_CMS_PACKAGE_RECONCILIATION.md` | E | docs | no | — | no | no | yes | Repository documentation; optional on server |
| A | `docs/JETPK_PUBLIC_PAGE_CMS_MATRIX_CURRENT.md` | E | docs | no | — | no | no | yes | Repository documentation; optional on server |
| M | `public/themes/admin/jetpakistan/js/page-settings-editor.js` | B | public-assets | yes | /home/pkjetp/jetpk_app/public/public/themes/admin/jetpakistan/js/page-settings-editor.js | /home/pkjetp/public_html/public/themes/admin/jetpakistan/js/page-settings-editor.js | yes | no | no | Public theme asset; must mirror to both public trees |
| M | `public/themes/frontend/jetpakistan/css/jp-search.css` | B | public-assets | yes | /home/pkjetp/jetpk_app/public/public/themes/frontend/jetpakistan/css/jp-search.css | /home/pkjetp/public_html/public/themes/frontend/jetpakistan/css/jp-search.css | yes | no | no | Public theme asset; must mirror to both public trees |
| M | `public/themes/frontend/jetpakistan/js/jp-dates.js` | B | public-assets | yes | /home/pkjetp/jetpk_app/public/public/themes/frontend/jetpakistan/js/jp-dates.js | /home/pkjetp/public_html/public/themes/frontend/jetpakistan/js/jp-dates.js | yes | no | no | Public theme asset; must mirror to both public trees |
| M | `resources/views/components/jp/dest-card.blade.php` | A | shared-components | yes | /home/pkjetp/jetpk_app/resources/views/components/jp/dest-card.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/edit.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/edit.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/partials/home-destinations-manager.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/partials/home-destinations-manager.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/partials/home-routes-manager.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/partials/home-routes-manager.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/partials/home-sections.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/partials/home-sections.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/partials/home-support-cta-manager.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/partials/home-support-cta-manager.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/components/search/flights-panel.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/components/search/flights-panel.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/components/search/search-shell.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/components/search/search-shell.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/frontend/home.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/frontend/home.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/destinations.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/destinations.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/fares.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/fares.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/feature-board.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/feature-board.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/groups.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/groups.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/hero.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/hero.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/trust.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/trust.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `routes/admin-page-settings.php` | A | routes | yes | /home/pkjetp/jetpk_app/routes/admin-page-settings.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `tests/Feature/Admin/MediaAssetReferenceGuardTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Admin/ResetToDefaultTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Admin/SaveCurrentAsDefaultTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| M | `tests/Feature/Client/DefaultClientCanonicalRedirectTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepageContentNormalizationIntegrationTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepageDraftPublishPipelineTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepageHostResolutionTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepagePublishRevisionIntegrationTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/JetpkContextDiagnosticNoPublicRouteTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/JetpkHomepageEditorialCoverageTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/JetpkHomepageSectionOrderTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/JetpkMobileHomepageParityTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| M | `tests/Support/JetpkHomepageFixture.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Services/Client/ClientPageResetServiceTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Services/Client/ClientPageSettingDefaultServiceTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Services/Client/ClientPageSettingRevisionServiceTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| M | `tests/Unit/Services/Client/ClientProfileResolverTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Support/Client/Homepage/HomepageContentNormalizerTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Support/Client/Homepage/HomepageSectionOrderResolverTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Support/Client/Homepage/JetpkHomepageContextDiagnosticTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| M | `tests/visual/helpers/public-critical-checks.ts` | D | tests | no | — | no | no | yes | CI/local verification only |
