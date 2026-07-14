# OTA F3 Deployment Owner Scope Report

Generated: 2026-06-17  
Task: **OTA-DEVCP-F3-DEPLOYMENT-OWNER-SCOPE-CORRECTION**

## Summary

Re-scoped Developer Control Panel from multi-tenant SaaS-style company management to **deployment-owner controls** for a single OTA install per server/database.

## What changed

### Navigation and legacy routes
- Removed **Companies** from Dev CP nav; renamed subtitle to **Deployment Owner Controls**.
- `dev.cp.companies.*` routes redirect to **Platform Admin users** with message: agencies are managed in OTA Admin Panel.
- Overview cards/stats updated: platform admin count, deployment module access, platform admin handoff.

### Platform Admin users (Dev CP only)
- New **`DevCpPlatformAdminUserService`**: create, reset password, activate/deactivate `platform_admin` only.
- New **`DevCpPlatformAdminUsersController`** + routes: `dev.cp.users.store`, `reset-password`, `status`.
- One-time temporary password via session flash; `must_change_password=true` on create/reset.
- Audit/security events: `devcp.platform_admin.*` (fail-soft loggers).

### Deployment module/package scope
- Modules page callout: controls **this deployment**, not individual agencies.
- New **Deployment package assignment** section applies catalog packages globally via presets.
- **`DevCpDeploymentPackageService`** records current package in `platform_feature_flags` (`deployment_package:{key}`).
- Route: `dev.cp.modules.package`.

### Bootstrap command
- **`devcp:bootstrap-platform-admin`** delegates to shared service; output: *Platform Admin created for this deployment.*
- Deployment fallback agency (for `current_agency_id`) — not SaaS tenant provisioning.

### Unchanged (by design)
- Agency models/tables, Admin Panel agency management, `company_module_entitlements` table/enforcer.
- All other dashboards (admin/staff/agent/customer/developer monitoring).

## Files changed

| Path |
|------|
| `app/Services/Developer/DevCpPlatformAdminUserService.php` (new) |
| `app/Services/Developer/DevCpDeploymentPackageService.php` (new) |
| `app/Http/Controllers/Developer/DevCpPlatformAdminUsersController.php` (new) |
| `app/Http/Controllers/Developer/DevCpPlatformController.php` |
| `app/Http/Controllers/Developer/PlatformModuleControlController.php` |
| `app/Http/Requests/Developer/StoreDevCpPlatformAdminRequest.php` (new) |
| `app/Http/Requests/Developer/UpdateDevCpPlatformAdminStatusRequest.php` (new) |
| `app/Http/Requests/Developer/ApplyDevCpDeploymentPackageRequest.php` (new) |
| `app/Services/Platform/PlatformPackageService.php` |
| `app/Console/Commands/DevcpBootstrapPlatformAdminCommand.php` |
| `app/Console/Commands/OtaAuditDevcpGapCommand.php` |
| `bootstrap/app.php` |
| `resources/views/layouts/developer.blade.php` |
| `resources/views/developer/control-panel/index.blade.php` |
| `resources/views/developer/users/index.blade.php` |
| `resources/views/developer/platform-modules/index.blade.php` |
| `tests/Feature/Developer/DevCpSectionsTest.php` |
| `tests/Feature/Developer/DevCpPlatformAdminUsersTest.php` (new) |
| `tests/Feature/Developer/DevcpBootstrapPlatformAdminCommandTest.php` |
| `tests/Feature/Developer/DeveloperControlPanelTest.php` |
| `tests/Feature/Developer/PlatformModuleSettingsPersistenceTest.php` |
| `docs/audits/OTA_DEV_CP_GAP_REPORT.md` |
| `docs/audits/OTA_F3_DEPLOYMENT_OWNER_SCOPE_REPORT.md` (this file) |
| `summary.md` |

## Verification (local)

| Command | Result |
|---------|--------|
| `php -l` on changed PHP files | Pass |
| `composer dump-autoload -o` | Pass |
| `php artisan optimize:clear` | Pass |
| `php artisan route:list` (dev.cp / module / password.force) | Pass — includes new user + package routes |
| `php artisan migrate:status` | Pass (1 unrelated pending migration) |
| `php artisan test --filter=Developer` | 66 passed |
| `php artisan test --filter=DevCp` | 20 passed |
| `php artisan test --filter=PlatformAdmin` | 27 passed |
| `php artisan test --filter=CompanyModuleEntitlement` | 2 passed |

## Remaining gaps

1. **`company_module_entitlements`** — table and enforcer remain; Dev CP no longer exposes per-agency UI.
2. **Admin Panel** — agency/user CRUD unchanged; client ops happen there.
3. **Deployment package marker** — display-only via `platform_feature_flags`; preset-only modes do not auto-set marker unless applied via package route.
4. **Developer vs platform admin** — separate auth (`developer_users` session); no forgot-password on Dev CP login (by design).
5. **Full manual browser QA** — deferred per sprint workflow until live deploy confirmed.

## Live deploy note

Do **not** upload `docs/`, `summary.md`, or `tests/` to live. Upload app/bootstrap/views only (see parent task SFTP list).
