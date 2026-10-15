# OPS-DEF-0004 — Viewer Cancellation Auth Remediation

**Date:** 2026-10-15  
**Branch:** `fix/ops-def-0004-viewer-cancellation-auth`  
**Base main SHA:** `6a09855411f2063d1d0db556ee6f1f8689a337b1`  
**Production runtime SHA (unchanged):** `cc21dc9261dd8124b0b9744ee2f6c6a0a1c3b7bf`  
**Status:** `FIX_READY_NOT_DEPLOYED`

## Summary

| Field | Value |
|-------|-------|
| ROOT_CAUSE | `BookingCancellationPolicy::request` allowed agent staff with only `agent.bookings.view` when agency/agent ownership matched |
| VULNERABLE_POLICY | `app/Policies/BookingCancellationPolicy.php::request` |
| VULNERABLE_PRESENTER | `AgentPortalBookingDetailPresenter` gated `request_cancellation` on `view` |
| NEW_PERMISSION | `agent.cancellations.request` (`AgentPermission::CancellationsRequest`) |
| AGENT_STAFF_REQUIRED_PERMISSIONS | `BookingsView` **AND** `CancellationsRequest` + agency/agent ownership |
| OPS_DEF_0004_STATUS | FIX_READY_NOT_DEPLOYED |
| SUPPLIER_EXECUTION | 0 |
| PRODUCTION_MUTATIONS | 0 |

## Role template decisions

| Role | CancellationsRequest | Rationale |
|------|----------------------|-----------|
| Owner | YES (via `AgentPermission::all()`) | Owner retains full portal capability |
| Manager | YES | Operational manager lifecycle |
| SupportStaff | YES | Support handles cancel-request workflows |
| SalesAgent | YES | Adjacent to booking create lifecycle |
| TicketingStaff | NO | View-scoped ticketing; assignable manually if needed |
| Accountant | NO | Finance read/manage; not cancel requestor |
| Viewer | NO | Read-only; primary defect closure |

No production `meta.agent_permissions` JSON auto-migration. Existing staff without the new key become DENY for cancel request (security-safe).

## Policy change

Agent portal branch of `request()`:

1. If agent staff: require `BookingsView` and `CancellationsRequest`, else false.
2. Then enforce existing agency + `booking.agent_id == agent.id`.
3. Owners/admins skip the staff dual-gate (implicit owner capability).
4. Customer / platform staff / admin / approve / reject / process unchanged.

## Presenter / UI

- Presenter uses `Gate::allows('request', [BookingCancellationRequest::class, $booking])`.
- Blade `detail-cancellation` already uses `@can('request', …)`.
- Next.js `AgentBookingCancellationPanel` consumes server `actions[]` (`request_cancellation`); no JS permission inference added.

## Local test proof

| Gate | Result |
|------|--------|
| VIEWER_NEGATIVE_TEST | Policy DENY + POST 403; DB/audit unchanged |
| AUTHORIZED_STAFF_POSITIVE_TEST | Policy ALLOW + POST creates request; status `requested` |
| CROSS_AGENCY_TEST | DENY / 403 |
| WRONG_AGENT_TEST | DENY |
| OWNER_REGRESSION | ALLOW |
| CUSTOMER_REGRESSION | CancellationRefundWorkflow customer path PASS |
| STAFF_ADMIN_REGRESSION | CancellationRefundWorkflow staff/admin approve/process PASS |
| UI_CAPABILITY_TEST | Viewer action absent; authorized staff `available=true` |

**Targeted suite (in-scope):** `54` passed / `0` failed / `232` assertions  
Files: `OpsDef0004ViewerCancellationAuthTest`, `CancellationRefundWorkflowTest`, `AgentStaffPermissionTest`, `AgentPortalPermissionMatrixFinalTest`  
Artifacts: `_raw-ops-def-0004-targeted-suite.txt`, `_raw-ops-def-0004-phpunit-junit.xml`

Pre-existing unrelated failures (also fail on base SHA `6a098554`; out of OPS-DEF-0004 scope):
- `AgencyStaffPermissionAssignmentTest::test_staff_with_staff_manage_can_update_another_staff_permissions` → 403
- `AgentPortalPermissionMatrixTest::test_staff_a2_bookings_create` / `test_commissions_are_agent_admin_only` → Blade UI string/`data-testid` drift

## Production

Read-only only. No cancel POST, no supplier cancel, no payment/wallet mutation.  
Production remains vulnerable until this code is deployed; local/test proves fix.

## Exit

`OPS_DEF_0004_STATUS=FIX_READY_NOT_DEPLOYED`  
Next: production code deployment / closure after green PR review.
