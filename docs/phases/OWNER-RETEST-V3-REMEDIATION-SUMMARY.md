# Owner Retest V3 Remediation — Engineering Summary

## Status

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_V3  
OWNER_RETEST_V3=RETEST_REQUIRED (Loop 4 production proof partial; owner manual rerun pending)  
ADMIN_FULL_MANAGEMENT_SYSTEM=YES (engineering complete; owner acceptance pending)

## Branch / SHAs

- Branch: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`
- LATEST_ENGINEERING_SHA: `c1f69f0866f6044c1cb76dc1fb0965728f5de5c9`
- PRODUCTION_BUILD_ID: `J404AEoiO1KUivMxDSN4Y`
- PRE-V3_BASE: `52be4ffd90a1342afd0eb8155dedcb98b3cfb166`

## Production deploy (2026-08-14)

- Laravel PHP layer uploaded (10 app files + `PublicActionUrl`)
- Dashboard source uploaded + `npm run build` + PM2 restart
- `APP_URL_HOST=jetpakistan.pk` confirmed
- `optimize:clear` + `config:cache` executed
- Password reset URL smoke: `PW_URL_PUBLIC=PASS`, `PW_LOOPBACK=PASS`, `PW_INDEXPHP=PASS`
- Source parity spot-check: `PublicActionUrl.php` + `AppServiceProvider.php` SHA match local
- OLS hash: `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` — PASS

## Owner rerun still required for

- Live dashboard PKR display / GBV / reports visual proof
- CMS homepage round-trip on production
- Deposit/commission UI RBAC proof (no commercial mutations)
- New password-reset email clickthrough to QA mailbox

## Remediation scope (V3 reopened gates)

### PKR / money pipeline
- `BookingOperationalMoneyResolver::presentAdminBusinessAmount()` — authoritative PKR snapshot for all admin business display
- Dashboard recent bookings, bookings list, payments use PKR via `DashboardMoneyPresenter::presentBookingTotal()`
- GBV KPI: removed legacy Non-PKR debug disclaimer text
- Reports: `BookingReportService` sums PKR snapshots; `DashboardReportResource` always formats PKR

### Password reset public URL
- `PublicActionUrl` helper + `ResetPassword::createUrlUsing` in `AppServiceProvider`
- Verify email URLs also forced through `APP_URL`
- `PasswordResetPublicUrlTest` regression

### Deposits / commissions management
- Deposits workspace: approve/reject actions wired to Laravel
- Commissions: pending entry queue with approve/reject in dashboard

### Dashboard operational alerts
- Command summary: agency applications, active agents, pending deposits, payment review, commissions
- KPI cards on overview + header operational inbox badge

### CMS homepage
- Canonical field names (`eyebrow`, `headline`, `headline_highlight`, `subtitle`)
- Editor shows effective source from Page Settings resolver

### API connections create
- Base URL field when `baseUrlOverridable`
- Advanced configuration section with channel-aware fields before save

## Tests executed (local)

- `PasswordResetPublicUrlTest` — PASS
- `JpDash03MoneyContractTest` — PASS
- `DashboardMoneyPresenterTest` — PASS
- Dashboard `npm run typecheck` — PASS
- Dashboard `npm run build` — PASS

## Pending for loop completion

- Full Laravel regression
- Production deploy + safe proof (no commercial mutations)
- OLS integrity read
- Documentation final reconciliation to `PASS_READY_FOR_OWNER_RETEST_V3_RERUN`
