# JP-FE-11 — Customer Dashboard Bookings Payments Invoices Profile Support Notifications and Turnstile Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-11-CUSTOMER-DASHBOARD-BOOKINGS-PAYMENTS-INVOICES-PROFILE-SUPPORT-NOTIFICATIONS-AND-TURNSTILE-CLOSURE |
| Branch | `phase/jetpk-fe-11-customer-dashboard` |
| Baseline | `1e244c4` |
| Feature commit | `fc05518` |
| Docs commit | `6ffd79f` |
| Merge commit | `c9d344b` |
| Final SHA documentation | `daf2dab` |
| Final status | COMPLETE |

## Objective

Deliver operational JetPakistan customer dashboard in Next.js backed by additive Laravel JSON on existing customer portal routes.

## Included scope

- Customer dashboard JSON contracts (`?format=json`)
- Next.js routes: overview, bookings, detail, payments, invoices, profile, security, support, notifications
- Customer shell (sidebar + mobile drawer)
- JP-FE-10 booking detail component reuse
- Support create/list/detail/reply (authenticated, no Turnstile per Laravel policy)
- Honest notifications unavailable state
- Booking/support public references in URLs
- Targeted Laravel + Playwright tests

## Excluded scope

- Group Ticketing in customer bookings union
- In-app notification inbox backend
- Agent/Admin/Staff dashboard
- Production deployment
- Support Turnstile for authenticated customers (preserved Laravel policy)

## Tests executed

| Suite | Result |
|-------|--------|
| `npm run typecheck` | pass |
| `npm run lint` | pass |
| `npm run build` | pass (41 routes) |
| Playwright `customer-dashboard.spec.ts` | 5/5 |
| Playwright `customer-portal-routes.spec.ts` | 5/5 |
| `php artisan test tests/Feature/Customer/CustomerPortalJsonContractTest.php` | 5/5 |

## Known limitations

- Notifications inbox not available (`available: false`); email remains authoritative
- Group bookings not listed in customer dashboard
- Invoice detail page uses list + Laravel download; full print view reuses booking invoice components in future polish
- Authenticated support does not use Turnstile (matches Laravel)

## No-deployment confirmation

Production untouched.

## Next phase

JP-FE-12-AGENT-AND-AGENT-STAFF-DASHBOARD-BOOKINGS-WALLET-PAYMENTS-INVOICES-PROFILE-SUPPORT-NOTIFICATIONS-AND-RBAC
