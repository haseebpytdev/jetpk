# OWNER UAT WAVE 2 — Architecture Audit

## Baseline

- Wave-1 frozen: `741f7d370518b5a4f32452851202653d0df9911f`
- Wave-2 branch: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`
- Presentation: Next `dashboard/` (Admin/Staff same shell, RBAC-derived)
- Domain: Laravel under `app/`

## Money pipeline (W2-01 / W2-02)

| Layer | Owner | Notes |
|---|---|---|
| Currency provenance | `BookingAuthoritativeCurrencyResolver` | meta.original_currency → offer → fareBreakdown → booking.currency |
| Presentation payload | `DashboardMoneyPresenter` | `resolved` / `unresolved`; `formatDisplayLabel()` → `Rs.` for PKR |
| Booking list/detail API | `DashboardBookingResource` / `DashboardBookingDetailResource` | Uses presenter |
| Overview recent bookings | `DashboardOverviewResource` | Uses `displayLabel` from presenter |
| Overview / Reports KPI | Overview + `DashboardReportResource` | Now use `formatDisplayLabel` |
| Dashboard UI format | `dashboard/lib/format.ts` `formatCurrency` | `Rs. XX,XXX.XX` for PKR; `{ISO} amount` otherwise |
| Money helper | `dashboard/lib/money.ts` | Respects currencyStatus; surfaces Amount unavailable |

**Disposition:** Do not invent FX. PKR display form is formatting-only when currency is PKR. Non-PKR resolved currencies remain truthful ISO display.

## Reports (W2-10)

| Mode | Path |
|---|---|
| Fixture | `report-service` → `buildReportModule` / operational fixture graph |
| Live | Laravel dashboard report routes → `transformReportModule` |

UI copy is live-mode aware. Fixture path remains for non-live dashboard mode only.

## Booking workspace (W2-05)

- Duplicate actions: `BookingDetailDrawerContent` included `BookingOperationalActions` while management page also mounted the sticky panel → fixed via `showOperationalActions={false}` on management page.
- Remaining: lifecycle eligibility (cancel/refund/payment visibility by state).

## Profile (W2-08)

- Header menu → `/profile`
- Next page + Laravel `/profile?format=json` for admin/staff safe fields
- Role/permissions not editable

## Fullscreen (W2-17)

Removed from `dashboard/components/dashboard/header.tsx`.

## Migration

No money display migration required for Rs. formatting. Historical PKR conversion storage not yet proven necessary; if later required → hard stop after other tasks.
