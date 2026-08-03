# JP-OPS-05 Admin Staff Capability Matrix

| Capability key | Admin | Staff | Enforcement |
|----------------|-------|-------|-------------|
| can_view_booking | yes | permission | Gate + dashboard.permission |
| can_manage_booking | yes | staff.bookings.update_status | Policy |
| can_review_payment | yes | staff.payments.verify | Policy |
| can_review_deposit | yes | no | AgentDepositRequestPolicy |
| can_review_cancellation | yes | staff.cancellations.approve | Policy |
| can_review_refund | yes | staff.refunds.approve | Policy |
| can_view_ticketing | yes | bookings.view | Read-only |
| can_manage_agency | yes | no | Admin only |
| can_manage_platform_staff | yes | no | Admin only |
| can_view_supplier_health | yes | suppliers.view | Read-only |
| can_manage_support | yes | staff.support.reply | Policy |
| can_view_reports | yes | staff.reports.view | DashboardPermissionResolver |
| can_export_reports | yes | staff.reports.export | Policy |
| can_view_audit_logs | yes | staff.reports.view | DashboardPermissionResolver |
| can_retry_job | no | no | INTENTIONALLY_UNAVAILABLE |

Capabilities are serialized from `BackOfficeCapabilitiesPresenter` only. Dashboard TypeScript consumes server payload; no frontend RBAC registry.
