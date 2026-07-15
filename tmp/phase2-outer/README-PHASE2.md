# Phase 2 — Customer Dashboard Home & Booking Index

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION
**Baseline:** `claude/ui-master` @ `6fbfae4637bb00e4a35b8edf3170a150d529b0b2` (re-verified
unchanged). **Depends on Phase 1** (canonical shell + foundation assets) being integrated.
**Status:** proposed package for Cursor. **No repository file was modified, committed, pushed,
or deployed.**

## 1. Investigation summary (fresh read of baseline)

- **`customer.dashboard`** is served by `CustomerBookingController@dashboard`, which computes a
  rich payload — `kpis{total,pending_payment,pnr_confirmed,cancellation_activity}`,
  `recentBookings`, `hasPendingPaymentBooking`, `firstPendingPaymentBooking`, `upcomingBooking`,
  `upcomingCount`, `supportTicketsCount` — then renders `mobile.dashboard.customer` (mobile) or
  `client_view('dashboard','customer')` (desktop).
- **The desktop home was a 23-line stub** that ignored that payload (extended the Tabler monolith
  layout and showed only a placeholder + "Search flights"). **The mobile home already uses the
  full payload** (greeting, four stat cards, featured/upcoming booking, quick actions) and is at
  parity. → the redesign brings the **desktop** home up to that data-rich parity, on the canonical
  customer-account portal shell (consistent with the bookings page and Phase 1).
- **`customer.bookings.index`** (`@index`) passes `bookings` (paginator, 15/pg, with contact +
  documents) and `filter`. The desktop view was already solid (filter tabs, desktop table + mobile
  card list, status/payment badges, pagination) but computed the payment display twice and
  computed two variables (`$needsPayment`, `$invoiceDoc`) it never rendered.

## 2. Pages covered & route mapping (unchanged)

| Route | Verb | Controller@action | Desktop view | Mobile view | Disposition |
|---|---|---|---|---|---|
| `customer.dashboard` | GET | `CustomerBookingController@dashboard` | `dashboard/customer/index` | `mobile/dashboard/customer` | FULL REDESIGN (desktop) |
| `customer.bookings.index` | GET | `CustomerBookingController@index` | `dashboard/customer/bookings/index` | `mobile/customer/bookings/index` | VISUAL NORMALIZATION |

No route name, URI, method, controller, middleware, permission, or module gate is changed. The
`customer.dashboard` desktop view now extends the **customer-account** layout (portal shell)
instead of the Tabler monolith — this is the only structural change, and it is UI-only.

## 3. Files in this package

```
resources/views/dashboard/customer/index.blade.php            REPLACE  desktop home (full redesign)
resources/views/dashboard/customer/bookings/index.blade.php   REPLACE  bookings index (normalized)
public/css/ota-customer-dashboard.css                          NEW      home CSS (portal-scoped, tokenised)
tests/proposed-safe-tests/customer-dashboard.spec.ts           NEW      proposed safe spec (local fixtures)
```

**Mobile parity:** `mobile/dashboard/customer.blade.php` and
`mobile/customer/bookings/index.blade.php` are already data-complete and well-built; they are
**left unchanged** (rewriting risks regression). The desktop redesign mirrors the mobile
information architecture (stats → featured/upcoming → recent → quick actions) so both shells match.

## 4. Data-contract notes (no backend change)

Both views consume **only** the variables the controller already provides (listed in §1); no new
controller variable, no invented view model. Booking fields used are all present on the model:
`display_reference` (accessor), `route`, `travel_date` (date cast), `status` (enum → status-badge),
`payment_status`. Payment operational label/badge uses the same
`App\Support\Bookings\PaymentOperationalStatus::fromValue()` and match() mapping as the baseline.

## 5. Asset-version changes

| Asset | Action | Cache-bust |
|---|---|---|
| `public/css/ota-customer-dashboard.css` | ADD (linked via `ui_asset()` in the home's `@push('styles')`) | set initial `?v=` if repo convention requires; bump on future edits |

No existing asset content changed by Phase 2 → no existing `?v=` bump required. (Phase 1's
`ota-dashboard-foundation.css/js` are assumed already linked via the Phase 1 shell.)

## 6. Proposed tests

`tests/proposed-safe-tests/customer-dashboard.spec.ts` (local fixtures only; excluded from
`*-live.config.ts`): home renders four KPI stats + quick actions; no horizontal overflow at
360/390/desktop; bookings filter tabs render and preserve the active filter; bookings page always
shows either rows or the empty state (never blank). Wire `loginAsCustomer()` to the repo's customer
session fixture before running. The integration gate remains `ota:route-page-health-audit --all`
(`fail=0`) + existing PHPUnit + local Playwright.

## 7. Known limitations

- **Depends on Phase 1** being integrated (customer-account shell + `ota-dashboard-foundation.css`).
- The desktop home moving onto the customer-account layout is intentional; confirm the tenant's
  `client_layout('customer-account','customer')` resolution during integration.
- Portal `ota-account-*` classes are assumed styled by `ota-public.css` / `ota-portal-console.css`
  (verified in the baseline bookings page). The new home-specific classes are styled by
  `ota-customer-dashboard.css`.
- Generated statically (no `artisan`/Vite/Playwright here) — unverified by execution; the
  integration manifest runs the real gates.
- Quick-action links are guarded with `Route::has()`, so a tenant missing a route (e.g.
  `booking.lookup`) simply hides that action rather than erroring.
