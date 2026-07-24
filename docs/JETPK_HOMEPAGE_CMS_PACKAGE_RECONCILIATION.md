# JetPK Homepage CMS Package Reconciliation

**Integration branch:** `integration/jetpk-homepage-cms-final`
**Authoritative baseline:** `624f3ddb26716c5bda893ae6e797b8c1780edda0`
**Package baseline:** `66eddbd11e7a1b8dfcf4feb8841a2d3f139a02f9`
**Package source:** `C:\Users\khadi\jetpk-cms-package-52` (not committed)

## Summary counts

| Classification | Count | Action taken |
|---|---:|---|
| APPLY_NEW | 10 | Copied schema, normalizer, diagnostic, models, services, migrations |
| MANUAL_MERGE | 24 | Ported logic into current-main files; preserved merge/restore stack |
| CURRENT_MAIN_NEWER | 16 | Rejected wholesale replacement (audit commands, client config, master-client) |
| TEST_ONLY | 18 | Copied/adapted; fixed FK fixtures, assertion bugs, mobile parity |
| DOC_ONLY | 21 | Referenced; integration docs written in-repo |
| OBSOLETE_AFTER_PRODUCTION_FIX | 3 | groups panel, fake-dynamic fares source, mobile-shell home |
| DEFERRED | 7 | Footer/global/about/support full CMS wiring (Task 18 follow-up) |

## Non-negotiable protections preserved

- `JetpkHomepageCmsRestoreCommand`, `JetpkHomepageContentMergeService`, `JetpkHomepageContentRestoreService`, `JetpkHomepageContentValidator`
- Section-scoped merge + `submitted_sections` + `preserveIntentionalEmptyScalars()`
- `PlatformBrandingResolver` JetPK mail name
- Trust-card authoritative defaults in `JetpkHomepageSectionData`
- Production restored homepage content (migrations are content-neutral)

## High-risk manual merges

| File | Decision |
|---|---|
| `ClientPageSettingsController.php` | Kept merge service + added reset/default routes |
| `ClientPageContentResolver.php` | Added normalizer, created_by fix, publish validation, revisions |
| `JetpkHomepageSectionData.php` | Added `featuredDealsForDisplay()` |
| `HomeController.php` | Mobile home disabled; diagnostic + ordered sections |
| `home.blade.php` | Dynamic section order + SEO meta |
| `fares.blade.php` | Editorial CMS items (Option B) |
| `page-settings-editor.js` | Package repeater JS + restored `submitted_sections` |
| `home-sections.blade.php` | Package alignment + featured_deals items repeater |

## Rejected / not integrated

- Package `DefaultClientRouteSafetyAuditService` cluster (current main newer at `624f3dd`)
- Package wholesale `config/ota_client.php` / master-client rewrites
- `featured_deals.source` hybrid/demo selector (removed; editorial items instead)
- Auto-seeding saved defaults
- Production backfill normalizer writes
