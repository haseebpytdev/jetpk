# Owner Retest V3 Remediation — Engineering Summary

## Status

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_V3  
OWNER_RETEST_V3=FAILED_REMEDIATION_REQUIRED (engineering in progress — owner rerun pending)  
ADMIN_FULL_MANAGEMENT_SYSTEM=NO (until production proof complete)

## Branch

`phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`

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
