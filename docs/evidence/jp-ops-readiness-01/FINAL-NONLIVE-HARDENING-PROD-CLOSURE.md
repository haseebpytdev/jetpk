# FINAL-NONLIVE-HARDENING-PROD-CLOSURE

**Loop:** JP-FINAL-NONLIVE-HARDENING-PROD-01  
**Merged PR:** #106 (squash)  
**Protected main SHA:** `1fa2f4ab7edfe9a74fa038ed30114669fba3257d`  
**Reviewed PR head:** `d2ff59bff6b9ceea7190cbc635ccbe9b9558ae5d`  
**Release guards:** PASS (`39312482357`)  
**Canonical app root:** `/home/pkjetp/jetpk_app`  
**Date (UTC):** 2026-10-19

## Absolute mutation assertions

| Assertion | Value |
|-----------|-------|
| PRODUCTION_BOOKINGS_MUTATED | 0 |
| PRODUCTION_PAYMENTS_MUTATED | 0 |
| PRODUCTION_WALLETS_MUTATED | 0 |
| PRODUCTION_LEDGERS_MUTATED | 0 |
| PRODUCTION_SUPPLIER_ROWS_MUTATED | 0 |
| PRODUCTION_FINANCIAL_ROWS_MUTATED | 0 |
| COMMUNICATION_ROWS_MUTATED | 0 |
| PUBLIC_STORAGE_MUTATED | NO |
| AIRBLUE_EXTERNAL_NETWORK_CALLS | 0 |
| SABRE_PNR_CREATE_CALLS | 0 |
| SUPPLIER_BOOKING_CALLS | 0 |
| TICKETING_CALLS | 0 |
| LIVE_PAYMENT_CALLS | 0 |
| ABHIPAY_CALLS | 0 |
| MIGRATION_RUN | NO |

## Phase 0 — merge / blob parity

| Gate | Result |
|------|--------|
| PR_106_MERGED | YES |
| PROTECTED_MAIN_SHA | `1fa2f4ab7edfe9a74fa038ed30114669fba3257d` |
| REVIEWED_RUNTIME_BLOB_PARITY (17 runtime paths vs reviewed head) | **PASS** (`FAILS=0`) |

## Phase 1 — pre-deploy health (baseline)

| Probe | Value |
|-------|-------|
| HTTP_UP_PRE | 200 |
| HTTP_HOME_PRE | 200 |
| HTTP_LOGIN_PRE | 200 |
| HTTP_LOOKUP_PRE | 200 |
| HTTP_AGENT_PRE | 200 |
| HTTP_ADMIN_PRE | 302 |
| PUBLIC_BUILD_ID_PRE | `HNDspC9CoB4Bat_x0DwZ6` |
| DASHBOARD_BUILD_ID_PRE | `3z0ZMmB40wEnJ1kRq0f1M` |
| FAILED_JOBS_PRE | 8 |
| PENDING_DEFAULT_PRE | 0 |
| AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED | **NO** |

## Staff authorization inventory (read-only, anonymized)

| Aggregate | Value |
|-----------|-------|
| ACTIVE_STAFF_COUNT | 2 |
| ACTIVE_STAFF_WITH_EXPLICIT_PERMS | 1 |
| ACTIVE_STAFF_LEGACY_MISSING_PERMS_PRE | 1 |
| ACTIVE_STAFF_MALFORMED_PERMS | 0 |
| AMBIGUOUS_STAFF_ACCOUNT_COUNT | 0 |

Decision: the single legacy-empty Staff account already evaluates **DENY** under fail-closed `hasExplicitStaffPermission` and has no authoritative preset mapping requiring elevation. **No Staff `meta.staff_permissions` rows were written.**

| Gate | Value |
|------|-------|
| STAFF_PERMISSION_BACKUP_READY | N/A_NO_STAFF_MUTATION |
| STAFF_PERMISSION_BACKUP_PATH | N/A |
| PRODUCTION_STAFF_PERMISSION_ROWS_MUTATED | 0 |
| ACTIVE_STAFF_LEGACY_MISSING_PERMS_POST | 1 (intentional fail-closed DENY; not operational elevation candidate) |

## OPS-DEF-0024 Support QA reconciliation

| Gate | Value |
|------|-------|
| LEGACY_SUPPORT_QA_FOUND | YES |
| LEGACY_SUPPORT_QA_IDENTITY_VERIFIED | YES (jp-dash-03 QA fixture mapping; UID omitted from public narrative beyond internal probe) |
| LEGACY_SUPPORT_QA_BACKUP_READY | YES |
| LEGACY_SUPPORT_QA_BACKUP_PATH | `/home/pkjetp/backups/final-nonlive-hardening-qa-perm-20261019T200907Z` |
| LEGACY_SUPPORT_QA_PERMISSION_RECONCILED | YES |
| PRODUCTION_QA_PERMISSION_ROWS_MUTATED | 1 |
| SUPPORT_QA_WALLET_PERMISSION | NO |
| SUPPORT_QA_LEDGER_MANAGE | NO |
| SUPPORT_QA_STAFF_MANAGE | NO |
| SUPPORT_QA_BOOKINGS_CREATE | NO |
| SUPPORT_QA_PAYMENTS_UPLOAD | NO |
| SUPPORT_QA_REPORTS_VIEW | NO |
| SUPPORT_ROLE_CONTRACT | PASS (view perm present; update/proof DENY) |
| CROSS_AGENCY_AUTH | DENY |
| PROD_STAFF_AUTH_CONTRACT | PASS (empty Staff DENY; explicit ops Staff ALLOW on intended gates) |

## Ops alert configuration

| Gate | Value |
|------|-------|
| OPS_ALERT_DESTINATION_PRESENT | NO |
| OPS_ALERT_DESTINATION_APPROVED | NO |
| OPS_EXTERNAL_ALERTS_ENABLED_PRE | false |
| OPS_EXTERNAL_ALERTS_ENABLED_POST | false |
| OPS_ALERT_QUEUE_DRY_RUN | PASS (queue_failure; log_only; delivered=NO) |
| OPS_ALERT_DISK_DRY_RUN | PASS (disk_pressure; log_only; delivered=NO) |
| OPS_ALERT_EMAIL_SENT_DURING_DRY_RUN | 0 |
| OPS_DEF_0022_STATUS | **PARTIAL_EXTERNAL_CONFIG** |

No operations mailbox invented.

## Release backup + deploy

| Gate | Value |
|------|-------|
| BACKUP_PATH | `/home/pkjetp/backups/final-nonlive-hardening-pre-deploy-20261019T200928Z` |
| ROLLBACK_READY | YES |
| PUBLIC_FRONTEND_ROLLBACK_READY | YES (`frontend-next-pre.tgz` in backup) |
| LARAVEL_RUNTIME_FILES_DEPLOYED | 11 |
| CONFIG_RUNTIME_FILES_DEPLOYED | 1 |
| SCRIPT_RUNTIME_FILES_DEPLOYED | 1 |
| FRONTEND_RUNTIME_FILES_DEPLOYED | 2 |
| BLADE_RUNTIME_FILES_DEPLOYED | 2 |
| UNEXPECTED_RUNTIME_FILES_DEPLOYED | 0 |
| PHP_CONFIG_SCRIPT_PARITY | **PASS** (17/17 SHA256) |
| PHP_SYNTAX_FAIL | 0 |
| LARAVEL_BOOTSTRAP | PASS |
| MIGRATION_RUN | NO |

### Runtime paths deployed (from protected main `1fa2f4ab`)

- `app/Console/Commands/JetpkOpsHealthAlertCommand.php`
- `app/Http/Controllers/Admin/CommunicationDeliveryLogController.php`
- `app/Policies/BookingPolicy.php`
- `app/Services/Communication/AdminReportMailerService.php`
- `app/Services/Reports/BookingReportService.php`
- `app/Support/Booking/StandardBookingJsonPresenter.php`
- `app/Support/Bookings/QaFixtureBookingScope.php`
- `app/Support/Communication/ActionableCommunicationLogScope.php`
- `app/Support/Ops/OpsExternalAlertRouter.php`
- `app/Support/Ops/OpsHealthAlertTriggers.php`
- `app/Support/Payments/PublicAbhiPayCheckoutPresenter.php`
- `config/ops.php`
- `frontend/features/guest-booking/components/GuestBookingDetailPage.tsx`
- `frontend/features/standard-booking/types/review-payment.ts`
- `resources/views/frontend/booking/partials/confirmation-body.blade.php`
- `resources/views/themes/frontend/jetpakistan/frontend/booking/partials/confirmation-body.blade.php`
- `scripts/jetpk/disk-health-check.sh`

Not deployed: `tests/`, `docs/evidence/`, phpunit artifacts, `.env`, `vendor`, `storage` contents, dashboard.

## Public frontend cutover

| Gate | Value |
|------|-------|
| FRONTEND_TYPECHECK | PASS |
| FRONTEND_LINT | PASS |
| PUBLIC_FRONTEND_BUILD | PASS |
| PUBLIC_BUILD_ID_PRE | `HNDspC9CoB4Bat_x0DwZ6` |
| PUBLIC_BUILD_ID_POST | `EFhHsRFz5_IhqhlXyHX9V` |
| DASHBOARD_BUILD_ID_PRE | `3z0ZMmB40wEnJ1kRq0f1M` |
| DASHBOARD_BUILD_ID_POST | `3z0ZMmB40wEnJ1kRq0f1M` (unchanged) |
| PM2 | `jetpk-public-frontend` restarted online |

## Post-deploy verification

| Gate | Value |
|------|-------|
| PROD_PRICING_CONTRACT | PASS |
| AUTO_TICKETING_IMPLIED_IN_UI | NO |
| OLD_NON_NOISE_QUEUED_REMAINS_ACTIONABLE | YES |
| LEGACY_NOISE_QUEUED_COUNT | 131 |
| OLD_NON_NOISE_ACTIONABLE_COUNT | 44 |
| DAILY_QA_MONEY_EXCLUDED | YES |
| WEEKLY_QA_MONEY_EXCLUDED | YES |
| MONTHLY_QA_MONEY_EXCLUDED | YES |
| PUBLIC_STORAGE_MUTATED | NO |
| OPS_DEF_0020_STATUS | CLOSED_SAFE_AS_IS |
| HTTP_UP_POST | 200 |
| HTTP_HOME_POST | 200 |
| HTTP_LOGIN_POST | 200 |
| HTTP_LOOKUP_POST | 200 |
| HTTP_AGENT_POST | 200 |
| HTTP_ADMIN_POST | 302 |
| FAILED_JOBS_POST | 8 |
| PENDING_DEFAULT_POST | 0 |
| DISK_HEALTH_SCRIPT | PASS (LEVEL=NORMAL USED=44% FREE_GB=53.84) |
| FAILED_JOBS_PROBE | PASS |
| PRIOR_SECURITY_FIXES_REGRESSION | PASS |

## Guest legacy token telemetry (since 20261017T120725Z)

| Aggregate | Value |
|-----------|-------|
| LEGACY_TELEMETRY_TOTAL_EVENTS | 0 |
| LEGACY_TELEMETRY_SUCCESS_EVENTS | 0 |
| LEGACY_TELEMETRY_DENIED_EVENTS | 0 |
| LAST_SUCCESSFUL_LEGACY_USAGE_AT | NONE |
| GUEST_TOKEN_SUNSET_DECISION | **KEEP_OBSERVATION_ACTIVE** |
| OPS_DEF_0011_STATUS | SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE |

No token/URL/IP/PII exposed. Routes not removed.

## Patch marker (scoped deployment model)

| Field | Value |
|-------|-------|
| PRODUCTION_RUNTIME_BASE_SHA | `922f15774ece177d8b3cc23207e98379b8a2755b` |
| PREVIOUS_PRODUCTION_PATCH_MARKER | `922f1577…+ops-def-0005-0008@79da5477…+ops-def-0011-0012@9a3f2ba2…+ops-def-0011-legacy-telemetry@9c5a0e8a…+airblue-zapways-finalization@ff148b1f…` |
| NEW_PRODUCTION_PATCH_MARKER | `922f1577…+…+airblue-zapways-finalization@ff148b1f…+final-nonlive-hardening@1fa2f4ab7edfe9a74fa038ed30114669fba3257d` |

Production is **not** a full Git checkout of protected main; scoped patch markers apply.

## Defect statuses after deploy

| DEFECT | STATUS |
|--------|--------|
| OPS-DEF-0009 | CLOSED_PROD |
| OPS-DEF-0010 | CLOSED_PROD |
| OPS-DEF-0026 | CLOSED_PROD |
| OPS-DEF-0025 | CLOSED_PROD |
| OPS-DEF-0027 | CLOSED_PROD_HISTORICAL_QA_DATA |
| OPS-DEF-0021 | CLOSED_PROD_HISTORICAL_RESIDUAL |
| OPS-DEF-0020 | CLOSED_SAFE_AS_IS |
| OPS-DEF-0024 | CLOSED_PROD |
| OPS-DEF-0022 | PARTIAL_EXTERNAL_CONFIG |
| OPS-DEF-0011 | SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE |

## Rollback

1. Restore the 17 runtime files from `BACKUP_PATH`.
2. Restore public frontend `.next` from `BACKUP_PATH/frontend-next-pre.tgz` and `pm2 restart jetpk-public-frontend`.
3. Restore Support QA `agent_permissions` from `…/final-nonlive-hardening-qa-perm-20261019T200907Z` if needed.
4. Revert patch marker files under `storage/app/*patch*.txt` to pre-deploy copies in backup.
5. Verify `/up` `/` `/login` `/lookup-booking` `/agent` `/admin`.

## Readiness

| Field | Value |
|-------|-------|
| PRODUCTION_OPERATIONAL_READY | NO |
| REMAINING_NON_DEFERRED_DEFECTS | OPS-DEF-0023 (root-owned cleanup); residual observability sources beyond disk/queue |
| DEFERRED_GO_LIVE_GATES | Sabre live PNR; AbhiPay live cert; AirBlue live Zapways cert |
| NEXT_REQUIRED_ACTION | Evidence PR merge; optionally configure approved `OPS_ALERT_EMAIL` then enable external alerts; continue 0011 observation; schedule supplier live UAT in launch window |

END
