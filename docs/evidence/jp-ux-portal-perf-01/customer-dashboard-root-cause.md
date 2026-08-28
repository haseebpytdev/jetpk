# Customer dashboard root cause — JP-UX-PORTAL-PERF-01

## CUSTOMER_DASHBOARD_ROOT_CAUSE

```text
SCHEMA_MISMATCH: Laravel CustomerPortalDashboardPresenter::presentBookingSummary
emitted key `status` while Next DashboardOverviewPage / CustomerBookingListItem
expected `booking_status`. StatusBadge then read undefined.code → uncaught
render throw → app/error.tsx "Something went wrong" whenever recent_bookings
was non-empty.
```

## Evidence chain

| Layer | Path | Behavior |
|-------|------|----------|
| Laravel presenter | `app/Support/CustomerPortal/CustomerPortalDashboardPresenter.php` | Was: `'status' => bookingStatus(...)`. Fixed: `'booking_status' => ...` |
| Bookings list (already correct) | `CustomerPortalBookingsPresenter` | Already emitted `booking_status` |
| Next overview | `frontend/features/customer-dashboard/overview/DashboardOverviewPage.tsx` | `<StatusBadge status={booking.booking_status} />` |
| Types | `frontend/features/customer-dashboard/types/index.ts` | `CustomerBookingListItem.booking_status` |
| Error surface | `frontend/app/error.tsx` | Generic "Something went wrong" |

## Why empty dashboards looked fine

Crash only occurs when `recent_bookings.length > 0`. Empty-state customers saw metrics without StatusBadge.

## Fix

1. Align presenter key to `booking_status` (same as bookings index).
2. Harden `StatusBadge` to no-op when status is null/undefined (defense in depth).
3. Contract test asserts `booking_status` present and `status` absent on dashboard recent bookings.

## Not the root cause

- Auth/session bootstrap (would redirect to login)
- Platform module gate (403 soft error)
- OLS rewrite (would not selectively crash on badge)
- Next route missing (would 404)

## Status

`CUSTOMER_DASHBOARD_ROOT_CAUSE=PROVEN` (code-level). Live production retest required after deploy of engineering SHA.
