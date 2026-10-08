# JP-DASH-PROD Write Bridge Matrix

Architecture: `GET /api/dashboard/*` read-only; writes via Laravel admin/staff mutation routes and dashboard UI action clients.

Legend: **PROD_EXECUTED_PASS** = production browser executed with persistence proof. **NOT_EXECUTED** = not yet certified this phase. **READ_ONLY_BY_DESIGN** / **EXTERNAL_ACTION_GATED** / **HIDDEN_UNIMPLEMENTED** / **READ_ONLY_FOR_PROD_CERT_SAFETY** = no safe write required.

## Executed on production (baseline SHA 9e26779, pre booking-note deploy)

| UI_SURFACE | HTTP_METHOD | LARAVEL_ROUTE | CONTROLLER | QA_PROD_EXECUTED | PERSISTED_AFTER_RELOAD | DB/API_CONFIRMED | RESULT |
|------------|-------------|---------------|------------|------------------|------------------------|------------------|--------|
| Markup create | POST | `admin.markups.store` | `Admin\MarkupController` | YES | YES | YES (list reload) | PROD_EXECUTED_PASS |
| API connection create (sandbox fake) | POST | `admin.api-settings.store` | `Admin\ApiSettingsController` | YES | YES | YES (card visible) | PROD_EXECUTED_PASS |
| Booking internal note | POST | `admin.bookings.notes` | `Admin\BookingManagementController@storeNote` | YES | NO | NO (HTTP 404) | BROKEN |

Evidence: `write-certification-result.json` (JPQA-WRITE run on 2026-10-08).

## Safe write-capable modules — certification status

| UI_SURFACE | HTTP_METHOD | LARAVEL_ROUTE | CONTROLLER | QA_PROD_EXECUTED | PERSISTED_AFTER_RELOAD | DB/API_CONFIRMED | RESULT |
|------------|-------------|---------------|------------|------------------|------------------------|------------------|--------|
| Booking note | POST | `admin.bookings.notes` | `BookingManagementController@storeNote` | YES (failed) | NO | NO | BROKEN → fix pending deploy |
| Booking status transition | PATCH | `admin.bookings.status` | `BookingManagementController@updateStatus` | NO | — | — | NOT_EXECUTED |
| Booking assign staff | POST | `admin.bookings.assign-staff` | `BookingManagementController@assignStaff` | NO | — | — | NOT_EXECUTED |
| Payment verify | POST | `admin.bookings.payments.verify` | `BookingPaymentController` | NO | — | — | NOT_EXECUTED |
| Payment reject | POST | `admin.bookings.payments.reject` | `BookingPaymentController` | NO | — | — | NOT_EXECUTED |
| Customer update | PATCH | `admin.customers.update` | `CustomerController` | NO | — | — | NOT_EXECUTED |
| User create/update/suspend | POST/PATCH | `admin.users.*` | `UserManagementController` | NO | — | — | NOT_EXECUTED |
| Staff permission update | PATCH | `admin.staff.permissions` | `StaffController` | NO | — | — | NOT_EXECUTED |
| Agent field update | PATCH | `admin.agents.update` | `AgentController` | NO | — | — | NOT_EXECUTED |
| Agent staff permission | PATCH | `agent.staff.permissions` | `AgentStaffController` | NO | — | — | NOT_EXECUTED |
| Markup edit/toggle/delete | PATCH/DELETE | `admin.markups.*` | `MarkupController` | NO | — | — | NOT_EXECUTED |
| CMS page CRUD | POST/PATCH/DELETE | `admin.cms.pages.*` | `CmsPageController` | NO | — | — | NOT_EXECUTED |
| CMS media upload/delete | POST/DELETE | agency media endpoints | `AgencyMediaController` | NO | — | — | NOT_EXECUTED |
| Support ticket/reply | POST/PATCH | `admin.support.*` / portal | `SupportTicketController` | NO | — | — | NOT_EXECUTED |
| API connection edit/delete | PATCH/DELETE | `admin.api-settings.*` | `ApiSettingsController` | NO | — | — | NOT_EXECUTED |
| Cancellation approve/reject | POST | `admin.bookings.cancellations.*` | `BookingCancellationController` | NO | — | — | EXTERNAL_ACTION_GATED |
| Refund review | POST | `admin.bookings.refunds.*` | `BookingRefundController` | NO | — | — | EXTERNAL_ACTION_GATED |
| Supplier booking/ticket/cancel | POST | supplier execution routes | supplier adapters | NO | — | — | EXTERNAL_ACTION_GATED |
| Reports | GET | `/api/dashboard/reports/*` | `DashboardReportsController` | N/A | N/A | N/A | READ_ONLY_BY_DESIGN |
| Audit log | GET | `/api/dashboard/audit/*` | `DashboardAuditController` | N/A | N/A | N/A | READ_ONLY_BY_DESIGN |
| SMTP / Google OAuth status | GET | settings integrations read | settings presenters | N/A | N/A | N/A | READ_ONLY_BY_DESIGN |
| Global security / DNS settings | — | — | — | N/A | N/A | N/A | READ_ONLY_FOR_PROD_CERT_SAFETY |
| Commissions settlement | POST | commissions payout routes | `CommissionController` | NO | — | — | EXTERNAL_ACTION_GATED |

No `POST/PATCH/DELETE` added to `/api/dashboard/*` during certification.
