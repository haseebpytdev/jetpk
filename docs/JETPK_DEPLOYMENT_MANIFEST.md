# JetPK Deployment Manifest

**Date:** 2026-07-17  
**Phase:** JETPK-STANDALONE-PORTAL closure  
**Scope:** Changed **runtime** files only (excludes `UI_test/`, `storage/`, compiled views, local test artifacts)

---

## Dual-root SFTP paths

| Root | Hostinger path (Mode A — shared preview) | Dedicated path (Mode B — `jetpakistan.com`) |
|------|------------------------------------------|-----------------------------------------------|
| **Laravel app** | `/home/u654883295/domains/haseebasif.com/ota-jetpk/` | `/home/<jetpk_user>/domains/jetpakistan.com/ota-jetpk/` |
| **Public webroot** | `/home/u654883295/domains/haseebasif.com/public_html/ota.haseebasif.com/` | `/home/<jetpk_user>/domains/jetpakistan.com/public_html/` |

**Rule:** PHP, Blade, config, routes → **Laravel app root**. Theme CSS/JS under `public/themes/` → **both** app path and mirrored public webroot (same relative path from each root).

Set `OTA_PUBLIC_WEBROOT_PATH` to the Mode B public webroot on dedicated deploy.

---

## Post-upload SSH (both modes)

```bash
cd /path/to/ota-jetpk
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Files to upload

### Application / config

| Repo path | Mode A (app) | Mode B (app) | Public webroot |
|-----------|--------------|--------------|----------------|
| `app/Http/Controllers/Customer/SavedTravelerController.php` | ✓ | ✓ | — |
| `app/Services/Client/ClientProfileResolver.php` | ✓ | ✓ | — |
| `app/Services/Client/RuntimeViewResolver.php` | ✓ | ✓ | — |
| `app/Support/Client/ClientProfileConfigReader.php` | ✓ | ✓ | — |
| `app/Support/Emails/JetpkEmailBrandingResolver.php` | ✓ | ✓ | — |
| `config/client.php` | ✓ | ✓ | — |
| `config/client_route_parity.php` | ✓ | ✓ | — |
| `config/client_ui.php` | ✓ | ✓ | — |
| `config/ota-brand.php` | ✓ | ✓ | — |
| `config/ota-client.php` | ✓ | ✓ | — |
| `config/ota-mobile.php` | ✓ | ✓ | — |
| `config/ota_client.php` | ✓ | ✓ | — |
| `database/seeders/OtaFoundationSeeder.php` | ✓ | ✓ | — |

### Public assets

| Repo path | Mode A app | Mode A webroot | Mode B app | Mode B webroot |
|-----------|------------|----------------|------------|----------------|
| `public/themes/frontend/jetpakistan/css/portal.css` | ✓ | ✓ | ✓ | ✓ |

### Blade — layouts & components

| Repo path |
|-----------|
| `resources/views/components/jp/icon.blade.php` |
| `resources/views/themes/frontend/jetpakistan/layouts/portal.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/agency-role-form.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/default-traveler-card.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/staff-access-clarification.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/staff-form.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/staff-permission-matrix.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/support-thread.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/traveler-form.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/finance/ledger-entries-table.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/finance/ledger-filters.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/finance/ledger-summary-cards.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/finance/ledger-transaction-table.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/finance/statement-filters.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/finance/statement-movement-table.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/finance/statement-reconciliation.blade.php` |
| `resources/views/themes/frontend/jetpakistan/components/portal/finance/statement-summary-cards.blade.php` |

### Blade — agent portal

| Repo path |
|-----------|
| `resources/views/themes/agent/jetpakistan/accounting/ledger/index.blade.php` |
| `resources/views/themes/agent/jetpakistan/accounting/ledger/show.blade.php` |
| `resources/views/themes/agent/jetpakistan/agency.blade.php` |
| `resources/views/themes/agent/jetpakistan/agency-edit.blade.php` |
| `resources/views/themes/agent/jetpakistan/commissions/index.blade.php` |
| `resources/views/themes/agent/jetpakistan/commissions/statement.blade.php` |
| `resources/views/themes/agent/jetpakistan/deposits/index.blade.php` |
| `resources/views/themes/agent/jetpakistan/deposits/create.blade.php` |
| `resources/views/themes/agent/jetpakistan/finance/statement/show.blade.php` |
| `resources/views/themes/agent/jetpakistan/ledger/index.blade.php` |
| `resources/views/themes/agent/jetpakistan/reports/index.blade.php` |
| `resources/views/themes/agent/jetpakistan/staff/index.blade.php` |
| `resources/views/themes/agent/jetpakistan/staff/create.blade.php` |
| `resources/views/themes/agent/jetpakistan/staff/edit.blade.php` |
| `resources/views/themes/agent/jetpakistan/support/tickets/index.blade.php` |
| `resources/views/themes/agent/jetpakistan/support/tickets/create.blade.php` |
| `resources/views/themes/agent/jetpakistan/support/tickets/show.blade.php` |
| `resources/views/themes/agent/jetpakistan/travelers/index.blade.php` |
| `resources/views/themes/agent/jetpakistan/travelers/create.blade.php` |
| `resources/views/themes/agent/jetpakistan/travelers/edit.blade.php` |
| `resources/views/themes/agent/jetpakistan/wallet.blade.php` |

### Blade — customer portal

| Repo path |
|-----------|
| `resources/views/themes/customer/jetpakistan/support/tickets/show.blade.php` |
| `resources/views/themes/customer/jetpakistan/travelers/index.blade.php` |
| `resources/views/themes/customer/jetpakistan/travelers/create.blade.php` |
| `resources/views/themes/customer/jetpakistan/travelers/edit.blade.php` |

### Blade — mobile (customer travelers)

| Repo path |
|-----------|
| `resources/views/mobile/customer/travelers/index.blade.php` |
| `resources/views/mobile/customer/travelers/create.blade.php` |
| `resources/views/mobile/customer/travelers/edit.blade.php` |

---

## Excluded from SFTP (repo-only)

| Path | Reason |
|------|--------|
| `tests/**` | Local/CI only |
| `UI_test/**` | Playwright artifacts |
| `storage/framework/views/**` | Compiled cache |
| `storage/app/customer-routes.json` | Local audit capture |
| `docs/JETPK_*.md` | Documentation (optional upload) |

---

## Verification after deploy

```bash
php artisan test tests/Feature/Jetpk/JetpkStandalonePortalClosureTest.php
php artisan ota:route-page-health-audit --all
```

Expected: closure test **6 passed**; route health audit **fail=0**.

---

## Rollback

Re-upload previous known-good copies of the same paths from git tag/branch; run `php artisan optimize:clear`. Finance views are additive — rollback removes themed pages but legacy `dashboard.agent.*` remains on disk for non-standalone clients only.
