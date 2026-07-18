# JetPK Homepage CMS — Exact Runtime Manifest

**Main SHA:** `0f48c97a1dafe59f5900008d82cb0b43862196b7` | **Merge:** `0f48c97` | **Integration merged:** `e1fef54` | **Baseline:** `624f3dd` | **Changed files:** 89

## Deployment classification summary

| Classification | Count |
|----------------|------:|
| APP_RUNTIME | 36 |
| PUBLIC_RUNTIME_BOTH_TREES | 4 |
| MIGRATION | 2 |
| REPOSITORY_ONLY_TEST | 24 |
| REPOSITORY_ONLY_DOC | 10 |
| REPOSITORY_ONLY_LOCAL_TOOLING | 2 |
| REPOSITORY_ONLY_CI | 4 (.gitignore, playwright configs, scripts/test) |

**SFTP upload totals:** 38 application runtime paths (36 APP_RUNTIME + 2 MIGRATION) + 4 public assets (8 mirrored put lines).

## REPOSITORY_ONLY_LOCAL_TOOLING (Class F — never upload)

| Path | Reason |
|------|--------|
| `app/Console/Commands/JetpkLocalAuditFixtureCommand.php` | Local audit fixture seeder; CI/dev only |
| `app/Support/Fixtures/JetpkHomepageAuditFixtureBuilder.php` | Local audit fixture builder; CI/dev only |

## Per-file manifest

| Status | Path | Class | Subsystem | Runtime | Production target | Public mirror | Migration | Repo-only | Reason |
|--------|------|-------|-----------|---------|-------------------|---------------|-----------|-----------|--------|
| A | `app/Http/Controllers/Admin/ClientPageSettingsController.php` | A | controllers | yes | /home/pkjetp/jetpk_app/app/Http/Controllers/Admin/ClientPageSettingsController.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
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
| A | `app/Console/Commands/JetpkLocalAuditFixtureCommand.php` | F | local-tooling | no | — | no | no | yes | REPOSITORY_ONLY_LOCAL_TOOLING |
| A | `app/Support/Fixtures/JetpkHomepageAuditFixtureBuilder.php` | F | local-tooling | no | — | no | no | yes | REPOSITORY_ONLY_LOCAL_TOOLING |
| M | `config/jetpk_homepage.php` | A | config | yes | /home/pkjetp/jetpk_app/config/jetpk_homepage.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `config/ota-mobile.php` | A | config | yes | /home/pkjetp/jetpk_app/config/ota-mobile.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `database/migrations/2026_07_16_120000_create_client_page_setting_revisions_table.php` | C | migrations | yes | /home/pkjetp/jetpk_app/database/migrations/2026_07_16_120000_create_client_page_setting_revisions_table.php | no | yes | no | Additive migration; run with path-scoped migrate after approval |
| A | `database/migrations/2026_07_16_130000_create_client_page_setting_defaults_table.php` | C | migrations | yes | /home/pkjetp/jetpk_app/database/migrations/2026_07_16_130000_create_client_page_setting_defaults_table.php | no | yes | no | Additive migration; run with path-scoped migrate after approval |
| M | `public/themes/admin/jetpakistan/js/page-settings-editor.js` | B | public-assets | yes | /home/pkjetp/jetpk_app/public/themes/admin/jetpakistan/js/page-settings-editor.js | /home/pkjetp/public_html/themes/admin/jetpakistan/js/page-settings-editor.js | no | no | Public theme asset; must mirror to both public trees |
| M | `public/themes/frontend/jetpakistan/css/jp-search.css` | B | public-assets | yes | /home/pkjetp/jetpk_app/public/themes/frontend/jetpakistan/css/jp-search.css | /home/pkjetp/public_html/themes/frontend/jetpakistan/css/jp-search.css | no | no | Public theme asset; must mirror to both public trees |
| M | `public/themes/frontend/jetpakistan/js/jp-dates.js` | B | public-assets | yes | /home/pkjetp/jetpk_app/public/themes/frontend/jetpakistan/js/jp-dates.js | /home/pkjetp/public_html/themes/frontend/jetpakistan/js/jp-dates.js | no | no | Public theme asset; must mirror to both public trees |
| M | `public/themes/frontend/jetpakistan/js/passengers.js` | B | public-assets | yes | /home/pkjetp/jetpk_app/public/themes/frontend/jetpakistan/js/passengers.js | /home/pkjetp/public_html/themes/frontend/jetpakistan/js/passengers.js | no | no | Public theme asset; must mirror to both public trees |
| M | `resources/views/components/jp/dest-card.blade.php` | A | shared-components | yes | /home/pkjetp/jetpk_app/resources/views/components/jp/dest-card.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/edit.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/edit.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/partials/home-destinations-manager.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/partials/home-destinations-manager.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/partials/home-routes-manager.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/partials/home-routes-manager.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/partials/home-sections.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/partials/home-sections.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/admin/jetpakistan/page-settings/partials/home-support-cta-manager.blade.php` | A | admin-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/admin/jetpakistan/page-settings/partials/home-support-cta-manager.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/components/search/flights-panel.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/components/search/flights-panel.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/components/search/passenger-selector.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/components/search/passenger-selector.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/components/search/search-shell.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/components/search/search-shell.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/frontend/home.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/frontend/home.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/destinations.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/destinations.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/fares.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/fares.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/feature-board.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/feature-board.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/groups.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/groups.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/hero.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/hero.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `resources/views/themes/frontend/jetpakistan/sections/trust.blade.php` | A | frontend-views | yes | /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/sections/trust.blade.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| M | `routes/admin-page-settings.php` | A | routes | yes | /home/pkjetp/jetpk_app/routes/admin-page-settings.php | no | no | no | PHP/Blade/config runtime required for CMS integration |
| A | `docs/JETPK_CLIENT_FEATURE_IMPROVEMENT.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_HOMEPAGE_CMS_EXACT_RUNTIME_MANIFEST.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_HOMEPAGE_CMS_FINAL_ACCEPTANCE_CURRENT.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_HOMEPAGE_CMS_FULL_TEST_BASELINE_COMPARISON.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_HOMEPAGE_CMS_NEW_ROUTE_SECURITY_MATRIX.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_HOMEPAGE_CMS_PACKAGE_RECONCILIATION.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_HOMEPAGE_CMS_PLAYWRIGHT_BASELINE_COMPARISON.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_HOMEPAGE_CMS_SFTP_COMMANDS.txt` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_HOMEPAGE_CMS_SSH_VERIFICATION.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_MOBILE_FLIGHT_OTA_RETIREMENT.md` | E | docs | no | — | no | no | yes | Repository documentation |
| A | `docs/JETPK_PUBLIC_PAGE_CMS_MATRIX_CURRENT.md` | E | docs | no | — | no | no | yes | Repository documentation |
| M | `.gitignore` | G | repo-config | no | — | no | no | yes | Repository hygiene |
| A | `playwright.admin-page-settings.config.ts` | G | ci-config | no | — | no | no | yes | CI/local Playwright config |
| A | `playwright.non-home-mobile.config.ts` | G | ci-config | no | — | no | no | yes | CI/local Playwright config |
| A | `scripts/test/README.md` | G | ci-scripts | no | — | no | no | yes | Local test runner docs |
| A | `scripts/test/run-feature-dirs.ps1` | G | ci-scripts | no | — | no | no | yes | Local test runner script |
| A | `tests/Feature/Admin/MediaAssetReferenceGuardTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Admin/ResetToDefaultTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Admin/SaveCurrentAsDefaultTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| M | `tests/Feature/Client/DefaultClientCanonicalRedirectTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepageCmsContentNeutralityTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepageContentNormalizationIntegrationTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepageDraftPublishPipelineTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepageHostResolutionTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/Client/HomepagePublishRevisionIntegrationTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/JetpkContextDiagnosticNoPublicRouteTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/JetpkHomepageEditorialCoverageTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/JetpkHomepageSectionOrderTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Feature/JetpkMobileHomepageParityTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| M | `tests/Support/JetpkHomepageFixture.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Bf7dControlledCertBrandVariantTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Bf7eRetrieveCertPnrSummaryTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Services/Client/ClientPageResetServiceTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Services/Client/ClientPageSettingDefaultServiceTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Services/Client/ClientPageSettingRevisionServiceTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| M | `tests/Unit/Services/Client/ClientProfileResolverTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Support/Client/Homepage/HomepageContentNormalizerTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Support/Client/Homepage/HomepageSectionOrderResolverTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/Unit/Support/Client/Homepage/JetpkHomepageContextDiagnosticTest.php` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/visual/admin-page-settings-auth.setup.ts` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/visual/admin-page-settings-functional.spec.ts` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/visual/desktop-return-range-picker.spec.ts` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/visual/helpers/jetpk-login-with-otp.ts` | D | tests | no | — | no | no | yes | CI/local verification only |
| M | `tests/visual/helpers/public-critical-checks.ts` | D | tests | no | — | no | no | yes | CI/local verification only |
| A | `tests/visual/helpers/public-fast-navigation.ts` | D | tests | no | — | no | no | yes | CI/local verification only |
| D | `tests/visual/mobile-flight-ota.spec.ts` | D | tests | no | — | no | no | yes | Retired; replaced by non-home-mobile-scope |
| A | `tests/visual/non-home-mobile-scope.spec.ts` | D | tests | no | — | no | no | yes | CI/local verification only |

## Approved migration commands (production)

```bash
php artisan migrate --path=database/migrations/2026_07_16_120000_create_client_page_setting_revisions_table.php --force
php artisan migrate --path=database/migrations/2026_07_16_130000_create_client_page_setting_defaults_table.php --force
```

Do **not** run generic `php artisan migrate --force`.
