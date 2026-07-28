# Admin Action Inventory

Phase: **JETPK-DASH-01**  
Mutating routes from [`routes/admin.php`](../../routes/admin.php) and [`routes/staff.php`](../../routes/staff.php). **Do not invoke from `/testdash` preview.**

## Bookings — status & notes

| Name | Method | URI | Risk | Permission |
|------|--------|-----|------|------------|
| `admin.bookings.status` | PATCH | `/admin/bookings/{booking}/status` | high | Booking policy |
| `admin.bookings.notes` | POST | `/admin/bookings/{booking}/notes` | medium | policy |
| `admin.bookings.assign-staff` | PATCH | `/admin/bookings/{booking}/assign-staff` | high | assignStaff gate |

## Bookings — supplier / PNR (module: `supplier_booking`)

| Name | Method | Risk |
|------|--------|------|
| `admin.bookings.supplier-booking` | POST | **critical** — live supplier |
| `admin.bookings.prepare-supplier-pnr-context` | POST | **critical** |
| `admin.bookings.manual-pnr` | POST | high |
| `admin.bookings.sync-pnr-itinerary` | POST | **critical** |
| Provider-specific sync (IATI, PIA NDC, AirBlue, etc.) | POST | **critical** |

Staff parallels under `staff.bookings.*` with `staff.permission` + module gates.

## Payments

| Name | Method | Risk |
|------|--------|------|
| `admin.bookings.payments.store` | POST | high |
| `admin.bookings.payments.verify` | PATCH | high |
| `admin.bookings.payments.reject` | PATCH | high |

Staff: verify/reject require `StaffPermission::PaymentsVerify` / `PaymentsReject`.

## Cancellations & refunds

| Name | Method | Risk |
|------|--------|------|
| `admin.bookings.cancellations.store` | POST | high |
| `admin.bookings.cancellations.approve` | PATCH | **critical** |
| `admin.bookings.cancellations.process` | PATCH | **critical** |
| `admin.bookings.refunds.store` | POST | high |
| Refund approve / mark-paid / reject | PATCH | **critical** |

## Ticketing (module: `ticketing`)

| Name | Method | Risk |
|------|--------|------|
| `admin.bookings.issue-ticket` | POST | **critical** |
| Staff `staff.bookings.issue-ticket` | POST | **critical** + `TicketingIssue` |

## Documents

| Name | Method | Risk |
|------|--------|------|
| `admin.bookings.documents.*` | POST | medium |
| `admin.bookings.documents.download` | GET | low |

## Communications

| Name | Method | Risk |
|------|--------|------|
| `admin.bookings.communication.send` | POST | medium — email/SMS |
| `admin.bookings.communication.resend` | POST | medium |

## Agent / finance admin actions

| Area | Examples | Risk |
|------|----------|------|
| Commissions | approve, reject, payout, statement | high |
| Agent deposits | approve, reject | high |
| Agent applications | approve, reject, needs-more-info | high |
| Wallet audit | archive | high |
| Finance adjustments | store, reverse | **critical** |
| Supplier connections | store, update, delete, test, toggle | **critical** |
| Group bookings | verify-payment, reject-payment | high |
| Support | reply, assign, forward, status | medium |

## Settings / CMS mutations

Branding, homepage, media, templates, notification events, promo codes, markups, CMS CRUD, page-settings publish — all **high** or **critical**; admin-only except page-settings (staff with explicit permission).

## Dashboard overview actions (current Blade)

Overview **does not POST**; action cards link to filtered booking queues or module index routes (`client_route(...)`). Next preview must keep the same **navigation intent** without calling Laravel.

## Operational queue keys (link targets)

From `AgencyDashboardService` / themed index action cards:

- `admin.agent-deposits.index` (status submitted)
- `admin.agent-applications.index`
- `admin.bookings` with `queue`: `payment_review`, `supplier_pnr`, `manual_review`, `ticketing`, `cancellations`, `refunds`, `needs_action`
- Failed notifications / supplier failures → booking queues or diagnostics

## `/testdash` rule

All buttons in DASH-01 show preview-only feedback or link to **planned** Next routes — zero Laravel mutation.
