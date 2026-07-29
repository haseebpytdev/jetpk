# Customer Dashboard Architecture (JP-FE-11)

## Ownership

- **Laravel:** authentication, authorization, ownership, metrics, bookings, payments, invoices, profile, password, support, notifications contract.
- **Next.js:** presentation shell and pages under `frontend/features/customer-dashboard/`.
- **Blade:** preserved fallback at `/customer/*` Laravel routes.

## Next.js routes

| Route | Purpose |
|-------|---------|
| `/customer` | Redirect to `/customer/dashboard` |
| `/customer/dashboard` | Overview metrics and quick actions |
| `/customer/bookings` | Bookings list |
| `/customer/bookings/[reference]` | Booking detail (JP-FE-10 reuse) |
| `/customer/payments` | Payment history |
| `/customer/invoices` | Invoice list |
| `/customer/profile` | Profile form |
| `/customer/security` | Password change |
| `/customer/support` | Support cases + create |
| `/customer/support/[reference]` | Support case detail + reply |
| `/customer/notifications` | Honest unavailable inbox state |

## Laravel JSON contract

All customer portal JSON uses `?format=json` or `Accept: application/json` on existing routes:

- `GET /customer?format=json` — dashboard overview
- `GET /customer/bookings?format=json` — bookings list
- `GET /customer/bookings/{booking_reference}?format=json` — booking detail
- `GET /customer/payments?format=json` — payment history
- `GET /customer/invoices?format=json` — invoice list
- `GET /customer/invoices/{booking_reference}?format=json` — invoice detail
- `GET /customer/profile?format=json` — profile read
- `PATCH /profile` (JSON) — profile update
- `PUT /password` (JSON) — password update
- `GET/POST /customer/support/tickets*` — support list/create/detail/reply
- `GET /customer/notifications?format=json` — unavailable inbox (email-only today)

## Role access

- Customer role only via `account.type:customer` middleware stack.
- Agent/Admin/Staff redirected to Laravel `dashboard_url`.
- Unauthenticated users redirected to `/login`.

## Group ticketing

Standard `Booking` records only. Group Ticketing (`GroupBooking`) remains on `/groups/*` and is not merged into customer dashboard history.

## Notifications

No Laravel database notification inbox exists yet. API returns `available: false` and `unread_count: 0`. Email notifications remain authoritative.

## Support Turnstile

Authenticated customer support submissions do **not** require Turnstile (Laravel `StoreSupportTicketRequest`). Public support/contact still requires Turnstile (JP-FE-10A module).

## Component inventory

- `CustomerDashboardShell`, overview, bookings, payments, invoices, profile, security, support, notifications pages
- Services: `customer-dashboard-api.ts`
- Types: `features/customer-dashboard/types`
