# JP-UI-05A — Admin and Platform Staff Feature Page and Permission QA

## Dashboard test file

`dashboard/tests/jp-ui-05a-rbac.spec.ts`

## Command

```bash
cd dashboard
npm run build
npx playwright test tests/jp-ui-05a-rbac.spec.ts -c playwright.config.ts
```

## Results (JP-UI-05A)

| Test | Result |
|------|--------|
| Platform Staff permitted route (`/staff/dashboard/bookings`) | Pass — `bookings-filters` visible |
| Platform Staff forbidden route (`/staff/dashboard/users?dataSourcePreview=forbidden`) | Pass — Access denied heading + preview gate |
| Dashboard private routes noindex | Pass — `metadata.robots` on root layout |

## Admin navigation

Admin scenarios capture overview, bookings, payments, agents, users, PNR queue, planned cancellations stub, empty customers, API error. Each waits for module-specific test IDs (`bookings-filters`, `payments-filters`, `users-workspace`, etc.) — not shell-only.

## Platform Staff restrictions

- Permitted: bookings list (visual scenario `platform-staff-permitted-route`)
- Forbidden: users module with `dataSourcePreview=forbidden` shows `ForbiddenState` via preview gate
- Navigation omission is not the security boundary; direct URL shows access denied preview stack

## Laravel

JP-UI-05B adds Laravel-authoritative evidence in `tests/Feature/Jetpk/PortalPermissionBoundaryTest.php` (5 methods) covering `agent.commissions.index` (`agent.admin` middleware), `agent.wallet.show` (`AgentPermission::WalletView`), `staff.bookings.index`, and `/admin/page-settings/home` (`StaffPermission::PageSettingsManage` gate).

## KPI authority

Overview operational queue cards use fixture KPI names (`pending_deposits`, `payment_review`, `supplier_pnr_pending`, etc.). Charts labelled preview/mock. No invented production KPI values.
