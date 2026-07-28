# Laravel Read-Only Module Map — JETPK-DASH-11

Maps each dashboard module from fixture services to future Laravel read-only endpoints.

**Legend:** ✅ fixture live | 📐 architecture only | 🔜 future Laravel (Prompt 02–04)

## Summary

| Module | Fixture | Future endpoint | Migration prompt |
|--------|---------|-----------------|------------------|
| Session | session-service / mockUser | `GET /api/dashboard/session` | **02 ✅** |
| Overview | overview-service | `GET /api/dashboard/overview` | **02 ✅** |
| Bookings | booking-service | `GET /api/dashboard/bookings` | **02 ✅** |
| Payments | payment-service | `GET /api/dashboard/payments` | **02 ✅** |
| Customers | customer-service | `GET /api/dashboard/customers` | **02 ✅** |
| Suppliers | supplier-service | `GET /api/dashboard/suppliers` | **03 ✅** |
| Agents | agent-service | `GET /api/dashboard/agents` | **03 ✅** |
| PNRs | pnr-service | `GET /api/dashboard/pnrs` | **03 ✅** |
| Tickets | ticket-service | `GET /api/dashboard/tickets` | **03 ✅** |
| Reports | report-service | `GET /api/dashboard/reports/{section}` | **03 ✅** |
| CMS | cms-service | `GET /api/dashboard/cms/{resource}` | **04 ✅** |
| Users | user-service | `GET /api/dashboard/users` | **04 ✅** |
| Roles | role-service | `GET /api/dashboard/roles` | **04 ✅** |
| Permissions | permission-service | `GET /api/dashboard/permissions` | **04 ✅** |
| Settings | settings-service | `GET /api/dashboard/settings/{section}` | **04 ✅** |
| Audit | audit-service | `GET /api/dashboard/audit` | **04 ✅** |

---

## Session / auth summary

| Field | Value |
|-------|-------|
| Fixture source | `mocks/overview-fixtures.ts` → `mockUser` |
| Service | Header/sidebar display only |
| Future endpoint | `GET /api/dashboard/session` |
| Contract | `ReadOnlyResponseEnvelope<SessionSummary>` |
| Transform | Strip session id, CSRF, tokens |
| Sensitive excluded | password, session_id, csrf_token |
| Empty state | N/A (redirect unauthenticated) |
| Error state | `UnauthorizedState` |
| Stale state | N/A |
| Authorization | Authenticated session |
| Status | ✅ Prompt 02 |

## Overview

| Field | Value |
|-------|-------|
| Fixture source | `mocks/overview-fixtures.ts` |
| Service | `services/overview-service.ts` → `getOverviewData()` |
| Future endpoint | `GET /api/dashboard/overview` |
| Transform | Map `AgencyDashboardService` shape to overview DTOs |
| Sensitive excluded | PCC, LNIATA, credentials |
| Empty state | Unlikely; show zeroed metrics |
| Error state | `SanitizedErrorState` |
| Stale state | `StaleDataNotice` after 60s |
| Authorization | `dashboard.view` |
| Status | ✅ Prompt 02 |

## Bookings

| Field | Value |
|-------|-------|
| Fixture source | `mocks/booking-fixtures.ts` |
| Service | `services/booking-service.ts` |
| Future endpoint | `GET /api/dashboard/bookings` |
| Transform | `buildBookingsPage` filters → Laravel query params |
| Sensitive excluded | passenger identity documents |
| Empty state | `EmptyState` in workspace |
| Error state | `BookingsErrorPanel` → `ErrorState` |
| Stale state | `StaleDataNotice` in shell (Prompt 02) |
| Authorization | `bookings.view` |
| Status | ✅ Prompt 02 |

## Payments

| Field | Value |
|-------|-------|
| Fixture source | payment fixtures |
| Service | `services/payment-service.ts` |
| Future endpoint | `GET /api/dashboard/payments` |
| Sensitive excluded | card_number, pan |
| Authorization | `payments.view` |
| Status | ✅ Prompt 02 |

## Customers

| Field | Value |
|-------|-------|
| Fixture source | customer fixtures |
| Service | `services/customer-service.ts` |
| Future endpoint | `GET /api/dashboard/customers` |
| Sensitive excluded | national_id, passport_number |
| Authorization | `customers.view` |
| Status | ✅ Prompt 02 |

## Suppliers

| Field | Value |
|-------|-------|
| Fixture source | supplier fixtures |
| Service | `services/supplier-service.ts` |
| Future endpoint | `GET /api/dashboard/suppliers` |
| Sensitive excluded | pcc, lniata, supplier_credentials |
| Authorization | `suppliers.view` |
| Status | ✅ Prompt 03 |

## Agents

| Field | Value |
|-------|-------|
| Service | `services/agent-service.ts` |
| Future endpoint | `GET /api/dashboard/agents` |
| Authorization | `agents.view` |
| Status | ✅ Prompt 03 |

## PNRs / orders

| Field | Value |
|-------|-------|
| Service | `services/pnr-service.ts` |
| Future endpoint | `GET /api/dashboard/pnrs` |
| Transform | Preserve GDS PNR vs NDC order channel fields |
| Authorization | `pnrs.view` |
| Status | ✅ Prompt 03 |

## Tickets / documents

| Field | Value |
|-------|-------|
| Service | `services/ticket-service.ts` |
| Future endpoint | `GET /api/dashboard/tickets` |
| Authorization | `tickets.view` |
| Status | ✅ Prompt 03 |

## Reports

| Field | Value |
|-------|-------|
| Service | `services/report-service.ts` |
| Future endpoint | `GET /api/dashboard/reports/{section}` |
| Transform | `referenceTime`, currency per KPI |
| Authorization | `reports.view` |
| Status | ✅ Prompt 03 |

## CMS

| Field | Value |
|-------|-------|
| Service | `services/cms-service.ts` |
| Future endpoint | `GET /api/dashboard/cms/{resource}` |
| Authorization | `cms.view` |
| Status | ✅ Prompt 04 (overview/pages live; banners/notices/assets fixture-only) |

## Users / roles / permissions

| Field | Value |
|-------|-------|
| Services | user, role, permission services |
| Future endpoints | `/users`, `/roles`, `/permissions` |
| Sensitive excluded | mfa_secret, password_hash |
| Authorization | `users.view`, `roles.view`, `permissions.view` |
| Status | ✅ Prompt 04 |

## Settings

| Field | Value |
|-------|-------|
| Service | `services/settings-service.ts` |
| Future endpoint | `GET /api/dashboard/settings/{section}` |
| Sensitive excluded | integration_secrets |
| Authorization | `settings.view` |
| Status | ✅ Prompt 04 |

## Audit

| Field | Value |
|-------|-------|
| Service | `services/audit-service.ts` |
| Future endpoint | `GET /api/dashboard/audit`, `/audit/{event}` |
| Sensitive excluded | raw_ip, unrestricted metadata |
| Authorization | `audit.view` |
| Status | ✅ Prompt 04 |

## Shared transformation rules

1. Laravel serializer strips sensitive keys before JSON.
2. Dashboard adapter maps envelope → existing module DTOs (minimize UI churn).
3. `createReadOnlyService` wraps fixture and Laravel adapters per module.
4. On adapter failure: show error state — **never** substitute fixtures.
