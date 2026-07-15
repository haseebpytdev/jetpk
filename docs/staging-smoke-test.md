# Staging Smoke Test Checklist

Run after staging deploy on `https://ota.haseebasif.com`.

## Public Routes

- `GET /`
- `GET /flights/search`
- `GET /airports/search?q=lhe`
- `GET /flights/results?from=LHR&to=JFK&depart=<future-date>`
- `GET /support`
- `GET /contact`
- `GET /login`
- `GET /register`
- `GET /agent/register`

## Admin Routes

- Login with seeded/admin credentials
- Dashboard loads
- API settings page loads
- Duffel connection state visible (active/inactive)
- Bookings page loads
- Reports page loads
- Markups page loads
- Branding page loads

## Workflow Validation

- Flight search triggers AJAX results loading
- Pagination/load-more works
- Non-PKR with `conversion_missing` is visible but blocked from booking
- PKR / `same_currency` result allows booking continuation
- Booking appears in admin bookings list
- Manual payment flow behaves correctly
- Supplier booking action only available when booking is eligible

## Final Safety

- `APP_DEBUG=false` in staging env
- No stack traces exposed on public errors
- `php artisan ota:production-check` returns success

