# OTA F2 Production Crash Gap Report

Generated: 2026-06-17T13:10:00+00:00  
Phase: **OTA-DEVCP-F2-PRODUCTION-CRASH-GAP-CLOSURE**

## Objective

Close production 500-risk gaps from live logs; make Dev CP and key dashboards safe to open without DB cleanup, migrate:fresh, or public_html changes.

## Gap closure status

| # | Gap | Fix | Local verification |
|---|-----|-----|-------------------|
| 1 | TurnstileVerifier missing / `RESPONSE_FIELD` | `App\Support\Security\TurnstileVerifier` + `RESPONSE_FIELD`; component gated by `isEnabled()` | `php artisan test --filter=Turnstile` — pass |
| 2 | SensitiveDataRedactor missing | `App\Support\Security\SensitiveDataRedactor`; `SupplierDiagnosticLogger` uses `redact()` | `php artisan test --filter=SensitiveDataRedactor` — pass |
| 3 | BookingManagementController missing methods | `preview()`, `data()`, `suggestions()`; trait `syncPnrItinerary()`; static `buildSabrePnrReadinessPanel()` | `php artisan test --filter=BookingManagement` — pass |
| 4 | EmailTemplateRegistry unhandled enum | `categoryForOtaEvent()` + `default` fallbacks; ops entry per `OtaNotificationEvent` case | `php artisan test --filter=EmailTemplateRegistry` — pass |
| 5 | Route `flights.return-options.data` | `routes/web.php` + `FlightController::returnOptionsData()` | `route:list --name=flights.return-options` — registered |
| 6 | AuthSecurityEmailNotificationService namespace | Correct `App\Services\Communication\…`; fail-soft try/catch on login email paths | grep: no bad Controller namespace |
| 7 | Ambiguous `created_at` with fare join | Qualified `bookings.created_at` in `BookingReportService`, `AgencyDashboardService`, `BookingManagementController` | `BookingReportCreatedAtQualificationTest` — pass |
| 8 | Dev CP browser safety | `dev.cp.*` routes + middleware; guest redirect; `AuthenticationException` → `dev.cp.login` on `/dev/cp*` | `php artisan test --filter=Developer` — pass |
| 9 | Security/audit logging fail-safe | `SecurityEventLogger` / `PlatformAuditLogger` with internal + caller try/catch | wired at auth, Dev CP, module services |
| 10 | Local docs | This report + refreshed audit reports + `summary.md` changelog | local only — not deployed |

## Migration note

[`database/migrations/2026_06_17_100200_create_company_module_entitlements_table.php`](../database/migrations/2026_06_17_100200_create_company_module_entitlements_table.php) uses short FK name **`cme_assigned_dev_fk`** for `assigned_by_developer_user_id`. F1 migrations already ran on live; no re-run required.

## Live prerequisites (manual)

1. Set **`OTA_DEVELOPER_CP_ENABLED=true`** in live `.env`, then `php artisan config:clear`.
2. Do **not** run `devcp:bootstrap-platform-admin` (platform admins already exist).
3. Download latest server copies via SFTP before overwriting (per live-server rules).

## Files changed this pass (local)

- `app/Services/Reports/BookingReportService.php`
- `app/Services/Dashboard/AgencyDashboardService.php`
- `app/Http/Controllers/Admin/BookingManagementController.php`
- `bootstrap/app.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `tests/Feature/Reports/BookingReportCreatedAtQualificationTest.php` (new)
- `tests/Feature/Admin/BookingManagementControllerSmokeTest.php` (new)
- `docs/audits/OTA_F2_PRODUCTION_CRASH_GAP_REPORT.md` (new)
- `docs/audits/OTA_SECURITY_HARDENING_REPORT.md` (regenerated)
- `docs/audits/OTA_DEV_CP_GAP_REPORT.md` (regenerated + env note)
- `summary.md`

## Remaining gaps (expected / out of scope)

- **`security_events` / `platform_audit_logs` count 0** until post-deploy traffic; smoke one login after upload.
- **`APP_DEBUG=true` on live** — flagged unsafe in security audit; not changed in F2.
- **Full manual QA** (desktop/tablet/mobile all portals) deferred per sprint workflow.
- **Pending local migration** `2026_06_16_110300_add_missing_group_passenger_identity_columns` — unrelated to F2; do not migrate:fresh.

## Verification commands run (local)

```text
php -l (all changed PHP) — pass
composer dump-autoload -o — pass
php artisan optimize:clear — pass
php artisan route:list — dev.cp.* and flights.return-options.data present
php artisan migrate:status — F1 Dev CP migrations Ran
php artisan test --filter=Turnstile — pass
php artisan test --filter=SensitiveDataRedactor — pass
php artisan test --filter=EmailTemplateRegistry — pass
php artisan test --filter=Developer — pass
php artisan test --filter=BookingManagement — pass
php artisan test --filter=BookingReportCreatedAtQualification — pass
php artisan ota:audit-security-hardening — pass
php artisan ota:audit-devcp-gap — pass
```
