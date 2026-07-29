# JP-FE-12 — Agent and Agent Staff Dashboard Bookings Wallet Payments Invoices Profile Support Notifications and RBAC

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-12-AGENT-AND-AGENT-STAFF-DASHBOARD-BOOKINGS-WALLET-PAYMENTS-INVOICES-PROFILE-SUPPORT-NOTIFICATIONS-AND-RBAC |
| Branch | `phase/jetpk-fe-12-agent-dashboard` |
| Baseline | `416541a` (JP-FE-11) |
| Feature commit | `d548c67` |
| Docs commit | `d548c67` (included in feature commit) |
| Merge commit | `8dab15b` |
| Final SHA documentation | `9a2d167` |
| Final status | COMPLETE |

## Objective

Deliver operational JetPakistan agent and agent-staff dashboard in Next.js backed by additive Laravel JSON on existing agent portal routes, with Laravel-authoritative RBAC, agency data scoping, and Blade fallback preserved.

## Included scope

- Agent dashboard JSON contracts (`?format=json` or `Accept: application/json`)
- Next.js routes under `/agent/*` with Laravel session + CSRF
- Agent shell (sidebar + mobile drawer) driven by Laravel capabilities/navigation
- Overview metrics, bookings list/detail, wallet, ledger, deposits, payments, invoices
- Profile, security (password), support create/list/detail/reply
- Honest notifications unavailable state
- Agent owner (`account_type=agent`) vs agent staff (`account_type=agent_staff`) with `meta.agent_permissions`
- Group ticketing bookings identified via `meta.group_booking_id` when present
- Public booking/ticket references in URLs (`booking_reference`, `ticket_reference` route binding)
- Route mapping: Laravel `/agent/ledger` → Next `/agent/wallet/ledger`; Laravel `/agent/deposits/create` → Next `/agent/deposits/new`
- New JSON endpoints: profile, payments, invoices, notifications
- Targeted Laravel + Playwright tests

## Excluded scope

- In-app notification inbox backend
- Agent staff management UI in Next.js (remains Blade at `/agent/staff`)
- Agency edit, commissions, reports, travelers, booking create flow in Next.js
- Admin/Staff dashboard
- Production deployment

## Investigation findings

- Agent portal Blade routes and RBAC middleware already existed in `routes/agent.php`
- Agent staff permissions stored in `User.meta.agent_permissions` as keys from `AgentPermission.php`
- Agent owner (`isAgentAdmin()`) implicitly has all permissions
- Wallet/ledger/deposits gated by permission keys and `platform.module:*` middleware
- No agent notification database inbox exists; customer phase established honest-unavailable pattern
- JP-FE-10 `StandardBookingJsonPresenter` reused for agent booking detail JSON

## Root causes

- Agent dashboard UI remained Blade-only while customer dashboard moved to Next.js in JP-FE-11
- Next.js needed Laravel-authoritative navigation/capabilities to avoid client-side permission drift
- Ledger and deposit create URLs differ between Laravel Blade paths and Next.js information architecture

## Files changed

### Laravel backend

**Controllers**
- `app/Http/Controllers/Agent/DashboardController.php`
- `app/Http/Controllers/Agent/AgentBookingController.php`
- `app/Http/Controllers/Agent/AgentWalletController.php`
- `app/Http/Controllers/Agent/AgentLedgerController.php`
- `app/Http/Controllers/Agent/AgentDepositController.php`
- `app/Http/Controllers/Agent/AgentPaymentController.php`
- `app/Http/Controllers/Agent/AgentInvoiceController.php`
- `app/Http/Controllers/Agent/AgentProfileController.php`
- `app/Http/Controllers/Agent/AgentNotificationController.php`
- `app/Http/Controllers/Agent/SupportTicketController.php`
- `app/Http/Controllers/Concerns/RespondsWithAgentPortalJson.php`

**Presenters**
- `app/Support/AgentPortal/AgentPortalCapabilitiesPresenter.php`
- `app/Support/AgentPortal/AgentPortalDashboardPresenter.php`
- `app/Support/AgentPortal/AgentPortalBookingsPresenter.php`
- `app/Support/AgentPortal/AgentPortalBookingDetailPresenter.php`
- `app/Support/AgentPortal/AgentPortalStatusPresenter.php`
- `app/Support/AgentPortal/AgentPortalWalletPresenter.php`
- `app/Support/AgentPortal/AgentPortalLedgerPresenter.php`
- `app/Support/AgentPortal/AgentPortalDepositsPresenter.php`
- `app/Support/AgentPortal/AgentPortalPaymentsPresenter.php`
- `app/Support/AgentPortal/AgentPortalInvoicesPresenter.php`
- `app/Support/AgentPortal/AgentPortalProfilePresenter.php`
- `app/Support/AgentPortal/AgentPortalSupportPresenter.php`
- `app/Support/AgentPortal/AgentPortalNotificationPresenter.php`

**Routes**
- `routes/agent.php` — profile, payments, invoices, notifications JSON endpoints

**Tests**
- `tests/Feature/Agent/AgentPortalJsonContractTest.php`

### Frontend

**App routes**
- `frontend/app/agent/page.tsx`
- `frontend/app/agent/dashboard/page.tsx`
- `frontend/app/agent/bookings/page.tsx`
- `frontend/app/agent/bookings/[reference]/page.tsx`
- `frontend/app/agent/wallet/page.tsx`
- `frontend/app/agent/wallet/ledger/page.tsx`
- `frontend/app/agent/deposits/page.tsx`
- `frontend/app/agent/deposits/new/page.tsx`
- `frontend/app/agent/payments/page.tsx`
- `frontend/app/agent/invoices/page.tsx`
- `frontend/app/agent/profile/page.tsx`
- `frontend/app/agent/security/page.tsx`
- `frontend/app/agent/support/page.tsx`
- `frontend/app/agent/support/[reference]/page.tsx`
- `frontend/app/agent/notifications/page.tsx`

**Feature module**
- `frontend/features/agent-dashboard/**` — shell, pages, types, API service
- `frontend/features/auth/server/agent-portal-access.ts`

**Tests**
- `frontend/tests/agent-dashboard.spec.ts`

**Documentation**
- `frontend/docs/AGENT-DASHBOARD-ARCHITECTURE.md`
- `frontend/docs/AGENT-AND-STAFF-RBAC-CONTRACT.md`
- `frontend/docs/AGENT-BOOKINGS-CONTRACT.md`
- `frontend/docs/AGENT-WALLET-LEDGER-AND-DEPOSITS-CONTRACT.md`
- `frontend/docs/AGENT-PAYMENTS-AND-INVOICES-CONTRACT.md`
- `frontend/docs/AGENT-PROFILE-AND-SECURITY-CONTRACT.md`
- `frontend/docs/AGENT-SUPPORT-AND-NOTIFICATIONS-CONTRACT.md`

## Routes changed

### Laravel (`routes/agent.php`)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/agent?format=json` | Dashboard overview + capabilities |
| GET | `/agent/profile?format=json` | Profile read (new JSON) |
| GET | `/agent/notifications?format=json` | Honest unavailable inbox (new JSON) |
| GET | `/agent/notifications/unread-summary?format=json` | Unread summary (new JSON) |
| GET | `/agent/payments?format=json` | Payment history (new JSON) |
| GET | `/agent/invoices?format=json` | Invoice list (new JSON) |
| GET | `/agent/bookings?format=json` | Bookings list |
| GET | `/agent/bookings/{booking:booking_reference}?format=json` | Booking detail |
| GET | `/agent/wallet?format=json` | Wallet overview |
| GET | `/agent/ledger?format=json` | Full ledger |
| GET | `/agent/deposits?format=json` | Deposit list |
| GET | `/agent/deposits/create?format=json` | Deposit create form |
| POST | `/agent/deposits` | Submit deposit |
| GET/POST | `/agent/support/tickets*` | Support list/create/detail/reply |

Blade HTML responses preserved on all routes when `format=json` is absent.

### Next.js

| Route | Laravel source |
|-------|----------------|
| `/agent` | Redirect to `/agent/dashboard` |
| `/agent/dashboard` | `GET /agent?format=json` |
| `/agent/bookings` | `GET /agent/bookings?format=json` |
| `/agent/bookings/[reference]` | `GET /agent/bookings/{booking_reference}?format=json` |
| `/agent/wallet` | `GET /agent/wallet?format=json` |
| `/agent/wallet/ledger` | `GET /agent/ledger?format=json` |
| `/agent/deposits` | `GET /agent/deposits?format=json` |
| `/agent/deposits/new` | `GET /agent/deposits/create?format=json` |
| `/agent/payments` | `GET /agent/payments?format=json` |
| `/agent/invoices` | `GET /agent/invoices?format=json` |
| `/agent/profile` | `GET /agent/profile?format=json` |
| `/agent/security` | `PUT /password` |
| `/agent/support` | Support tickets JSON |
| `/agent/support/[reference]` | Support detail JSON |
| `/agent/notifications` | `GET /agent/notifications?format=json` |

## Database changes

None.

## Backend changes

- Additive JSON branches on existing agent controllers via `RespondsWithAgentPortalJson`
- New presenter layer under `app/Support/AgentPortal/`
- Capabilities presenter emits permission flags, platform module flags, and Next.js navigation items
- Agency-scoped booking/wallet/support queries unchanged; JSON is a presentation layer
- Notifications return `available: false`; mark-read endpoints return 501-style unavailable payload

## Frontend changes

- New `agent-dashboard` feature module with shared shell and page components
- `requireAgentPortalAccess()` guards all `/agent/*` routes (agent + agent_staff only)
- API client in `agent-dashboard-api.ts` proxies to Laravel via `/laravel/*`
- Booking detail reuses JP-FE-10 standard booking confirmation types/components where applicable

## Tests executed

| Suite | Result |
|-------|--------|
| `npm run typecheck` | pass |
| `npm run lint` | pass |
| `npm run build` | pass |
| Playwright `agent-dashboard.spec.ts` | 5/5 |
| `php artisan test tests/Feature/Agent/AgentPortalJsonContractTest.php` | 7/7 |

## Assertion counts

- Playwright: 5 tests
- Laravel JSON contract: 7 tests

## Screenshots

TBD — capture during manual QA (desktop/tablet/mobile agent dashboard shell, bookings, wallet, RBAC denial).

## Responsive verification

Agent shell uses shared JetPakistan tokens with mobile drawer navigation; sidebar on desktop. Validated in Playwright at default viewport.

## Accessibility verification

- Skip link to `#main-content`
- `aria-current="page"` on active nav items
- Visible focus via `:focus-visible`
- One heading hierarchy per page via shell `title` prop

## Known limitations

- Notifications inbox not available (`available: false`); email remains authoritative
- Agent staff management, agency edit, commissions, reports, travelers remain Blade-only
- Invoice PDF download uses existing Laravel document download URL
- Group ticketing bookings appear in agent list when linked via `meta.group_booking_id` but detail UX follows standard booking presenter
- Authenticated agent support does not require Turnstile (matches Laravel policy)

## Risks

- Client must not infer permissions from route existence; Laravel middleware is authoritative
- Platform module flags can hide wallet/ledger/deposits/support even when permission keys are granted
- Misconfigured session proxy could redirect non-agents incorrectly

## Rollback instructions

1. Revert merge commit on integration branch
2. Or checkout baseline `416541a`
3. Remove `frontend/features/agent-dashboard/` and `frontend/app/agent/*` dashboard pages if partial rollback needed
4. Laravel JSON branches are additive; Blade fallback continues to work without Next.js

## No-deployment confirmation

Production untouched.

## Next phase

JP-FE-13-PUBLIC-CMS-DEEP-PAGES-CONTACT-SUPPORT-TURNSTILE-SEO-ACCESSIBILITY-PERFORMANCE-AND-NO-FALLBACK-CLOSURE
