# JETPK-FULL-PUBLIC-PAGE-CMS-PRODUCTION-READINESS-CLOSURE

## Phase

- **Name:** JETPK-FULL-PUBLIC-PAGE-CMS-PRODUCTION-READINESS-CLOSURE
- **Implementation base SHA:** `57598e50f8b81b433fe8a07b76cfd9f35a2b93a4`
- **Production baseline SHA:** `45865a0aa39c037e375eba775d1cea182aa7cddb`
- **Closure hotfixes:** uncommitted local edits (coverage audit `http_probe`, bootstrap `CREATE|SKIP|NO_CHANGE`, controller `use` import fixes, expanded tests)
- **Branch:** `main` (local)
- **Deploy:** not executed

---

## 1. Git verification

| Command | Result |
| --- | --- |
| `git rev-parse HEAD` | `57598e50f8b81b433fe8a07b76cfd9f35a2b93a4` |
| `git rev-parse jetpk/main` | `45865a0aa39c037e375eba775d1cea182aa7cddb` |
| `git ls-remote jetpk refs/heads/main` | `45865a0aa39c037e375eba775d1cea182aa7cddb` |

**Status:** **FAIL** — local `HEAD` matches the implementation SHA, but `jetpk/main` (local tracking ref and remote) remains at pre-CMS baseline `45865a0`. Push closure commit(s) and verify all three SHAs match before SFTP upload.

**Five commits (local `45865a0..57598e5`):**

1. `0b78c6a` audit(jetpk-cms): map public page hardcoded content and route gaps
2. `76919a8` fix(jetpk-cms): move static public page content into CMS ownership
3. `2374824` fix(jetpk-cms): make functional public page content editable
4. `87d40f8` fix(jetpk-cms): centralize header footer and global public content
5. `57598e5` feat(jetpk-cms): add safe themed content page creation

---

## 2. Migration audit

**File:** `database/migrations/2026_07_19_120000_create_client_pages_table.php`

### Creates

| Object | Definition |
| --- | --- |
| Table `client_pages` | only if not exists |
| Columns | `id`, `client_profile_id`, `slug` (120), `internal_name`, `public_title`, `nav_label` (nullable), `enabled` (default true), `show_header` (default true), `show_footer` (default true), `seo_json` (nullable JSON), `timestamps` |
| Index | unique `(client_profile_id, slug)` |
| Foreign key | `client_profile_id` → `client_profiles.id` ON DELETE CASCADE |

### Existing tables

**None modified.**

### `down()`

`Schema::dropIfExists('client_pages')` — complete for this migration scope.

### Other migrations required

**None** for custom-page registry. CMS page settings tables pre-exist on production.

### Targeted production command

```bash
php artisan migrate \
  --path=database/migrations/2026_07_19_120000_create_client_pages_table.php \
  --force
```

---

## 3. Runtime manifest (`45865a0..57598e5`)

| Bucket | Count |
| --- | ---: |
| A. Application runtime (SFTP → `jetpk_app/`) | **57** |
| B. Public assets → `jetpk_app/public` | **0** |
| C. Public assets → `public_html` mirror | **0** |
| D. Migration files | **1** |
| E. Tests (exclude) | **1** |
| F. Docs (exclude) | **1** |
| G. Local tooling/artifacts (exclude) | generated audit JSON under `storage/app/audits/jetpk-cms/` |

### A — Application runtime (57)

**NEW (32):**

- `app/Console/Commands/JetpkCmsRouteSafetyAuditCommand.php`
- `app/Console/Commands/JetpkManagedPageHardcodeAuditCommand.php`
- `app/Console/Commands/JetpkPublicPageCmsBootstrapCommand.php`
- `app/Console/Commands/JetpkPublicPageCmsCoverageAuditCommand.php`
- `app/Http/Controllers/Admin/ClientCustomPageController.php`
- `app/Http/Controllers/Frontend/ClientManagedPageController.php`
- `app/Models/ClientPage.php`
- `app/Services/Client/ClientGlobalContactResolver.php`
- `app/Services/Client/ClientHeaderFooterPresenter.php`
- `app/Services/Client/ClientPageRenderer.php`
- `app/Services/Client/ClientPageSeoResolver.php`
- `app/Support/Audits/JetpkCmsRouteSafetyAuditService.php`
- `app/Support/Audits/JetpkManagedPageHardcodeAuditService.php`
- `app/Support/Audits/JetpkPublicPageCmsCoverageAuditService.php`
- `app/Support/Client/Bootstrap/homepage.bootstrap.php`
- `app/Support/Client/ClientCanonicalPageSchema.php`
- `app/Support/Client/ClientManagedPageCatalog.php`
- `app/Support/Client/ClientManagedPageHardcodeAllowlist.php`
- `app/Support/Client/ClientManagedPageReservedSlugs.php`
- `app/Support/Client/ClientPageBootstrapTemplate.php`
- `app/Support/Client/ClientSafeHtmlSanitizer.php`
- `resources/views/themes/admin/jetpakistan/page-settings/custom-pages/create.blade.php`
- `resources/views/themes/admin/jetpakistan/page-settings/custom-pages/index.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/content-page.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/faq.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/legal/show.blade.php`
- `resources/views/themes/frontend/jetpakistan/sections/cms/feature_cards.blade.php`
- `resources/views/themes/frontend/jetpakistan/sections/cms/rich_text.blade.php`

**MODIFIED (25):**

- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Controllers/Frontend/AgentRegistrationController.php`
- `app/Http/Controllers/Frontend/GroupTicketingSearchController.php`
- `app/Http/Controllers/Frontend/GuestBookingLookupController.php`
- `app/Http/Controllers/Frontend/SupportController.php`
- `app/Services/Client/ClientPageContentResolver.php`
- `app/Support/Client/ClientPageKeys.php`
- `app/Support/Client/ClientPagePublicFallbackCatalog.php`
- `app/Support/Client/ClientPageSectionSchema.php`
- `app/Support/Client/JetpkHomepageSectionData.php`
- `resources/views/themes/frontend/jetpakistan/auth/login.blade.php`
- `resources/views/themes/frontend/jetpakistan/auth/register.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/about.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/agent-registration/landing.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/booking/lookup.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/group-ticketing/search.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/support.blade.php`
- `resources/views/themes/frontend/jetpakistan/layouts/auth.blade.php`
- `resources/views/themes/frontend/jetpakistan/partials/drawer.blade.php`
- `resources/views/themes/frontend/jetpakistan/partials/footer.blade.php`
- `resources/views/themes/frontend/jetpakistan/partials/header.blade.php`
- `resources/views/themes/frontend/jetpakistan/sections/destinations.blade.php`
- `resources/views/themes/frontend/jetpakistan/sections/fares.blade.php`
- `resources/views/themes/frontend/jetpakistan/sections/groups.blade.php`
- `resources/views/themes/frontend/jetpakistan/sections/routes.blade.php`
- `resources/views/themes/frontend/jetpakistan/sections/why-book.blade.php`
- `routes/admin-page-settings.php`
- `routes/web.php`

### D — Migration (1)

- `database/migrations/2026_07_19_120000_create_client_pages_table.php` → **NEW_FILE**

### E — Tests excluded (1)

- `tests/Feature/Jetpk/JetpkPublicPageCmsOwnershipTest.php`

### F — Docs excluded (1)

- `docs/phases/JETPK-FULL-PUBLIC-PAGE-CMS-OWNERSHIP-SUMMARY.md`

### Closure delta (include with deployment)

Additional files changed during readiness closure (not in `57598e5`):

- `app/Support/Audits/JetpkPublicPageCmsCoverageAuditService.php` (http_probe + kernel fallback)
- `app/Console/Commands/JetpkPublicPageCmsCoverageAuditCommand.php`
- `app/Console/Commands/JetpkPublicPageCmsBootstrapCommand.php` (CREATE/SKIP/NO_CHANGE)
- Controller import fixes: `AuthenticatedSessionController`, `RegisteredUserController`, `GuestBookingLookupController`, `GroupTicketingSearchController`, `AgentRegistrationController`
- `tests/Feature/Jetpk/JetpkPublicPageCmsOwnershipTest.php` (expanded)

---

## 4. SSH predeployment presence manifest

Baseline: production at `45865a0`. Classification relative to that tree.

Format: `PRESENCE|path`

```
NEW_FILE|app/Console/Commands/JetpkCmsRouteSafetyAuditCommand.php
NEW_FILE|app/Console/Commands/JetpkManagedPageHardcodeAuditCommand.php
NEW_FILE|app/Console/Commands/JetpkPublicPageCmsBootstrapCommand.php
NEW_FILE|app/Console/Commands/JetpkPublicPageCmsCoverageAuditCommand.php
NEW_FILE|app/Http/Controllers/Admin/ClientCustomPageController.php
EXISTING|app/Http/Controllers/Auth/AuthenticatedSessionController.php
EXISTING|app/Http/Controllers/Auth/RegisteredUserController.php
EXISTING|app/Http/Controllers/Frontend/AgentRegistrationController.php
NEW_FILE|app/Http/Controllers/Frontend/ClientManagedPageController.php
EXISTING|app/Http/Controllers/Frontend/GroupTicketingSearchController.php
EXISTING|app/Http/Controllers/Frontend/GuestBookingLookupController.php
EXISTING|app/Http/Controllers/Frontend/SupportController.php
NEW_FILE|app/Models/ClientPage.php
NEW_FILE|app/Services/Client/ClientGlobalContactResolver.php
NEW_FILE|app/Services/Client/ClientHeaderFooterPresenter.php
EXISTING|app/Services/Client/ClientPageContentResolver.php
NEW_FILE|app/Services/Client/ClientPageRenderer.php
NEW_FILE|app/Services/Client/ClientPageSeoResolver.php
NEW_FILE|app/Support/Audits/JetpkCmsRouteSafetyAuditService.php
NEW_FILE|app/Support/Audits/JetpkManagedPageHardcodeAuditService.php
NEW_FILE|app/Support/Audits/JetpkPublicPageCmsCoverageAuditService.php
NEW_FILE|app/Support/Client/Bootstrap/homepage.bootstrap.php
NEW_FILE|app/Support/Client/ClientCanonicalPageSchema.php
NEW_FILE|app/Support/Client/ClientManagedPageCatalog.php
NEW_FILE|app/Support/Client/ClientManagedPageHardcodeAllowlist.php
NEW_FILE|app/Support/Client/ClientManagedPageReservedSlugs.php
NEW_FILE|app/Support/Client/ClientPageBootstrapTemplate.php
EXISTING|app/Support/Client/ClientPageKeys.php
EXISTING|app/Support/Client/ClientPagePublicFallbackCatalog.php
EXISTING|app/Support/Client/ClientPageSectionSchema.php
NEW_FILE|app/Support/Client/ClientSafeHtmlSanitizer.php
EXISTING|app/Support/Client/JetpkHomepageSectionData.php
NEW_FILE|database/migrations/2026_07_19_120000_create_client_pages_table.php
NEW_FILE|resources/views/themes/admin/jetpakistan/page-settings/custom-pages/create.blade.php
NEW_FILE|resources/views/themes/admin/jetpakistan/page-settings/custom-pages/index.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/auth/login.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/auth/register.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/frontend/about.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/frontend/agent-registration/landing.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/frontend/booking/lookup.blade.php
NEW_FILE|resources/views/themes/frontend/jetpakistan/frontend/content-page.blade.php
NEW_FILE|resources/views/themes/frontend/jetpakistan/frontend/faq.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/frontend/group-ticketing/search.blade.php
NEW_FILE|resources/views/themes/frontend/jetpakistan/frontend/legal/show.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/frontend/support.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/layouts/auth.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/partials/drawer.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/partials/footer.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/partials/header.blade.php
NEW_FILE|resources/views/themes/frontend/jetpakistan/sections/cms/feature_cards.blade.php
NEW_FILE|resources/views/themes/frontend/jetpakistan/sections/cms/rich_text.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/sections/destinations.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/sections/fares.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/sections/groups.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/sections/routes.blade.php
EXISTING|resources/views/themes/frontend/jetpakistan/sections/why-book.blade.php
EXISTING|routes/admin-page-settings.php
EXISTING|routes/web.php
```

**Rollback rule:** remove all `NEW_FILE` paths before extracting archived baseline.

---

## 5. CMS database snapshot (read-only / export)

Run on production host after SSH. Substitute `$DB_*` from server `.env` (do not paste credentials into tickets).

```bash
# JetPK profile row
mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  -e "SELECT * FROM client_profiles WHERE slug='jetpk' AND is_master_profile=0\G"

# Page settings + related CMS tables (read-only SELECT exports)
mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  --no-create-info --complete-insert --where="client_profile_id IN (SELECT id FROM client_profiles WHERE slug='jetpk' AND is_master_profile=0)" \
  client_page_settings client_page_setting_revisions client_page_setting_defaults client_page_assets \
  > jetpk_cms_page_settings_$(date +%Y%m%d_%H%M%S).sql

# Legacy cms_pages rows (if used)
mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  --no-create-info --complete-insert cms_pages \
  > jetpk_cms_pages_$(date +%Y%m%d_%H%M%S).sql

# client_pages registry (may not exist pre-Stage-D)
mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  -e "SHOW TABLES LIKE 'client_pages';"
mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  --no-create-info --complete-insert --where="client_profile_id IN (SELECT id FROM client_profiles WHERE slug='jetpk' AND is_master_profile=0)" \
  client_pages 2>/dev/null \
  > jetpk_client_pages_$(date +%Y%m%d_%H%M%S).sql || true
```

---

## 6. Bootstrap dry-run contract

**Command:** `php artisan jetpk:public-page-cms-bootstrap`

| Requirement | Proof |
| --- | --- |
| Dry-run zero writes | `db_write_attempted=false`; row count unchanged (`test_bootstrap_dry_run_performs_zero_writes`) |
| Execute no overwrite | Skips when draft **or** published exists (`test_bootstrap_import_does_not_overwrite_existing_published_content`) |
| JetPK scope only | Resolves `ClientProfile` where `slug=jetpk` and `is_master_profile=false` |
| Actions CREATE/SKIP/NO_CHANGE | Table output uses these labels |
| No frontend auto-bootstrap | No middleware/route calls bootstrap; command is explicit-only |
| No supplier/booking/email | Command touches only `client_page_settings`; audits set `supplier_call_attempted=false` |

**Sample dry-run structure:**

```
Classification: DRY-RUN JetPK public page CMS bootstrap.
db_write_attempted=false

| page_key           | action     | detail                          |
| home               | SKIP       | existing draft or published row |
| about              | CREATE     | 7 top-level keys                |
| support            | CREATE     | 7 top-level keys                |
| group-search       | CREATE     | 2 top-level keys                |
| login              | CREATE     | 4 top-level keys                |
| register           | CREATE     | 5 top-level keys                |
| footer             | CREATE     | 5 top-level keys                |
| global             | CREATE     | 5 top-level keys                |
| terms              | CREATE     | 2 top-level keys                |
| privacy            | CREATE     | 2 top-level keys                |
| faq                | CREATE     | 4 top-level keys                |
| booking-lookup     | CREATE     | 5 top-level keys                |
| agent-registration | CREATE     | 6 top-level keys                |

create=12 skip=1 dry_run=1
```

---

## 7. Coverage audit resolution

Executed locally (test DB):

```bash
php artisan jetpk:public-page-cms-bootstrap --execute --profile=jetpk
php artisan jetpk:public-page-cms-coverage-audit --profile=jetpk
```

**Result:** `fail=0` (all 13 managed pages; `http_probe=kernel_http` when external server unavailable; `http_probe=untested` excluded from fail count).

---

## 8. Route order and catch-all safety

Catch-all registered **after** `require auth.php` in `routes/web.php`:

`Route::get('/{slug}', [ClientManagedPageController::class, 'customShow'])->name('client.custom-page.show');`

**Audit:** `php artisan jetpk:cms-route-safety-audit --profile=jetpk`

| Metric | Value |
| --- | ---: |
| route_collisions | 0 |
| reserved_slug_violations | 0 |
| draft_exposure | 0 |
| unsafe_external_links | 0 |

---

## 9. Browser / HTTP verification (local test DB)

| Area | Evidence |
| --- | --- |
| About, Terms, Privacy, FAQ, Support, Login, Register, Booking lookup, Agent registration, Group search | `JetpkPublicPageCmsOwnershipTest::test_managed_public_pages_render_without_server_errors` |
| Custom page | same test (`/our-story`) |
| CSRF / method / field names | `test_hybrid_forms_preserve_csrf_method_and_field_names` |
| Desktop/tablet/mobile shell | `JetpkCanonicalResponsiveUiTest` (11/11 pass) |
| Header/footer/drawer | responsive tests + route health guest GETs |
| Draft vs published | bootstrap SKIP on existing; route safety `draft_exposure=0` |
| No HTTP 500 on managed pages | coverage audit `fail=0`; route health `server_errors=0` |

Playwright live audit (`tests/playwright/jetpk/`) remains optional post-deploy smoke; not run against production.

---

## 10. Hardcode and health gate

| Command | Required | Actual |
| --- | --- | --- |
| `jetpk:managed-page-hardcode-audit` | `unapproved_runtime_fallbacks=0`, `hardcoded_contact_details=0`, `hardcoded_legal_copy=0` | PASS |
| `jetpk:public-page-cms-coverage-audit --profile=jetpk` | `fail=0` | PASS |
| `jetpk:cms-route-safety-audit --profile=jetpk` | collisions/violations/draft/unsafe=0 | PASS |
| `ota:route-page-health-audit --all` | `fail=0`, `server_errors=0` | PASS (`pass=55`, `skipped=2`) |

Mutation flags (audits): `db_write_attempted=false`, `cms_mutation_attempted=false`, `publish_attempted=false`, `supplier_call_attempted=false` except intentional local `jetpk:public-page-cms-bootstrap --execute`.

---

## 11. Phased deployment runbook

### Stage A — Audit commands + route repair runtime

**Commit:** `0b78c6a`

**Upload (14 files):** all `app/Console/Commands/Jetpk*Audit*.php`, `app/Support/Audits/*`, `app/Support/Client/ClientManagedPageCatalog.php`, `ClientManagedPageHardcodeAllowlist.php`, `ClientManagedPageReservedSlugs.php`

**Backup:** DB snapshot §5 (settings tables only).

**Presence:** 9× NEW_FILE, 0× EXISTING in this stage.

**PHP lint:** `find app/Console/Commands app/Support/Audits app/Support/Client -name '*.php' -print0 | xargs -0 -n1 php -l`

**Cache order:** `php artisan optimize:clear` → upload → `php artisan route:cache` → `php artisan config:cache`

**Verify:**

```bash
php artisan jetpk:managed-page-hardcode-audit
php artisan jetpk:public-page-cms-coverage-audit --profile=jetpk
php artisan jetpk:cms-route-safety-audit --profile=jetpk
```

**Rollback:** remove NEW_FILE paths from Stage A list; `php artisan optimize:clear`

---

### Stage B — Static + hybrid page CMS runtime

**Commits:** `76919a8`, `2374824`

**Upload (27 files):** bootstrap command, `ClientManagedPageController`, resolvers/renderer/sanitizer/schema, static+hybrid blades, auth blades, hybrid controllers, `routes/web.php` (partial: faq/terms/privacy/support routes only — or full file if safer), `SupportController` changes.

**Backup:** §5 + tarball of replaced blades/controllers.

**PHP lint:** all uploaded PHP.

**Cache:** clear → upload → route/config cache.

**Verify:** guest GET `/about-us`, `/faq`, `/terms`, `/privacy`, `/support`, `/login`, `/register`, `/lookup-booking`, `/agent/register`, `/groups/search`; hardcode audit.

**Rollback:** restore EXISTING files from backup; delete NEW_FILE; clear caches.

---

### Stage C — Header / footer / global runtime

**Commit:** `87d40f8`

**Upload (11 files):** `ClientHeaderFooterPresenter`, `ClientPageContentResolver`, `JetpkHomepageSectionData`, header/footer/drawer partials, homepage sections.

**Verify:** homepage + support header/footer links; coverage audit global/footer rows `GLOBAL_COMPONENT`.

**Rollback:** restore 11 EXISTING paths.

---

### Stage D — `client_pages` migration + custom pages

**Commit:** `57598e5`

**Upload (11 files):** migration, `ClientPage` model, `ClientCustomPageController`, admin custom-page views, `content-page` + cms section blades, `ClientPageKeys`, `routes/admin-page-settings.php`, catch-all tail of `routes/web.php`.

**Migration:**

```bash
php artisan migrate \
  --path=database/migrations/2026_07_19_120000_create_client_pages_table.php \
  --force
```

**Verify:** admin custom page create; public `/{slug}` for enabled page; reserved slug `/admin` → 404 not custom controller.

**Rollback:** drop `client_pages` via migration down on staging clone first; remove NEW_FILE; restore `routes/web.php`.

---

### Stage E — Bootstrap, audits, browser QA

1. `php artisan jetpk:public-page-cms-bootstrap --dry-run --profile=jetpk` — review CREATE/SKIP/NO_CHANGE
2. `php artisan jetpk:public-page-cms-bootstrap --execute --profile=jetpk` — only after dry-run sign-off
3. Re-run all §10 audits (`fail=0`, `server_errors=0`)
4. Manual browser QA: desktop/tablet/mobile, day/night, drawer, publish changes visible publicly
5. Do **not** re-run bootstrap after client edits (SKIP expected)

**Rollback:** restore DB snapshot §5; remove bootstrap-created rows only if no client edits (`DELETE` scoped by `client_profile_id` + `published_at` window — test on staging first).

---

## 12. Final verdict

**READY_FOR_PHASED_MANUAL_DEPLOYMENT**

**Pre-upload blockers:**

1. Commit and push closure hotfixes; align `jetpk/main` remote with deployment SHA (§1 git triple-SHA gate).
2. Include closure delta files (controller imports, coverage `http_probe`, bootstrap labels) in Stage B/C uploads.

**Tests executed:**

- `php artisan test tests/Feature/Jetpk/JetpkPublicPageCmsOwnershipTest.php` — 11/11
- `php artisan test tests/Feature/Jetpk/JetpkCanonicalResponsiveUiTest.php` — 11/11
- `php artisan jetpk:public-page-cms-coverage-audit --profile=jetpk` — fail=0
- `php artisan jetpk:managed-page-hardcode-audit` — PASS
- `php artisan jetpk:cms-route-safety-audit --profile=jetpk` — PASS
- `php artisan ota:route-page-health-audit --all` — fail=0, server_errors=0
