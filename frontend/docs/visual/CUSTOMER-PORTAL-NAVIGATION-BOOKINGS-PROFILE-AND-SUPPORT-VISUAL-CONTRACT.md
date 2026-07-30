# Customer Portal — Navigation, Bookings, Profile, and Support Visual Contract (JP-UI-05)

## Scope

Customer dashboard shell and primary routes under `/customer/*`. Visual shell parity using shared portal primitives.

## Module

- `frontend/features/portal/` — shared shell primitives
- `frontend/features/customer-dashboard/shell/CustomerDashboardShell.tsx`

## Shell hierarchy

```
PortalShell (data-testid="customer-dashboard-shell")
├── PortalTopbar (mobile menu toggle)
├── PortalSidebar (desktop lg+)
│   ├── Identity: Account / displayName
│   ├── buildPortalNav(NAV_ITEMS)
│   └── PortalSidebarFooter
├── PortalMobileDrawer (lg:hidden)
└── PortalContent
    ├── PortalPageHeader
    └── Page content
```

## Navigation items

| Code | Label | Route |
|------|-------|-------|
| overview | Overview | `/customer/dashboard` |
| bookings | My Bookings | `/customer/bookings` |
| payments | Payments | `/customer/payments` |
| invoices | Invoices | `/customer/invoices` |
| profile | Profile | `/customer/profile` |
| security | Security | `/customer/security` |
| support | Support | `/customer/support` |
| notifications | Notifications | `/customer/notifications` |

Active route: highlighted nav item via `buildPortalNav`. Notifications badge when `unreadNotifications > 0`.

## Route surfaces

| Route | testId | States covered |
|-------|--------|----------------|
| `/customer/dashboard` | `customer-dashboard-overview` | overview light/dark/system, mobile, zoom |
| `/customer/bookings` | `customer-bookings-list` | list, empty |
| `/customer/bookings/[ref]` | `customer-booking-detail` | detail, forbidden |
| `/customer/payments` | `customer-payments-list` | payment history |
| `/customer/invoices` | `customer-invoices-list` | available, unavailable empty |
| `/customer/profile` | `customer-profile-form` | edit, validation errors |
| `/customer/support` | `customer-support-list` | support tickets/requests |

## Error and loading states

| Component | testId | Use |
|-----------|--------|-----|
| `CustomerDashboardErrorState` | `customer-dashboard-error` | Session expired, API error, forbidden booking |
| Loading shell | `customer-dashboard-shell` | Skeleton while data loads |
| `CustomerDashboardEmptyState` | `customer-empty-state` | Empty bookings/invoices |

## Responsive behavior

| Viewport | Behavior |
|----------|----------|
| `lg+` | Fixed sidebar + content column |
| `< lg` | Topbar + hamburger; `PortalMobileDrawer` overlay |
| 390 mobile | Drawer width `w-72`; content full width |

Drawer: `role="dialog"`, `aria-modal="true"`, escape key closes, overlay click closes, body scroll locked.

## Theme support

- Light, dark, system-light, system-dark on overview scenarios
- All surfaces use `jp-*` tokens consistent with public shell (JP-UI-02)

## Data ownership

| Content | Owner |
|---------|-------|
| Nav labels | B (UI vocabulary) |
| Booking/payment/invoice rows | D (Laravel API) |
| Profile field values | D (Laravel API) |
| Error messages | D (Laravel) or B (generic fallback) |

## Accessibility

- Page title via `PortalPageHeader` with `id="customer-page-title"`
- Nav links: visible focus ring
- Empty/error states: heading + actionable retry where applicable
- Status badges use `StatusBadge` with semantic tone

## Related scenarios

Customer family (20): overview themes/mobile/zoom, bookings list/empty/detail/forbidden, payments, invoices available/unavailable, profile/validation, support, session-expired, loading, api-error.

## JP-UI-05A updates

- Customer portal layout: `robots: { index: false, follow: false }`
- Ownership tests: `frontend/tests/jp-ui-05a-customer-ownership.spec.ts` (4/4 PASS)
- See `JP-UI-05A-CUSTOMER-OWNERSHIP-AND-PRIVATE-ROUTE-QA.md`
