# Customer portal — Master OTA parity matrix

JetPakistan ports **behavior / protocol / ownership**, not legacy Blade styling.

| FEATURE | MASTER_OTA_REFERENCE | JETPAKISTAN_CURRENT | GAP | IMPLEMENTATION_ACTION | INTENTIONAL_DIFFERENCE | STATUS |
|---------|----------------------|---------------------|-----|----------------------|------------------------|--------|
| Session / auth | `routes/auth.php` + `account.type:customer` + email verified | Next session bootstrap + same Laravel middleware on `/laravel/customer*` | None structural | Keep | Next UI shell | PASS |
| Dashboard KPIs | `CustomerBookingController::dashboard` Blade | Next `DashboardOverviewPage` + JSON presenter | `status` vs `booking_status` crash | Fix presenter key | Next metrics cards | FIXED_PENDING_DEPLOY |
| Booking list | Scoped `customer_id` + filters | `CustomerBookingsPage` + `CustomerPortalBookingsPresenter` | None | Keep | Next table chrome | PASS |
| Booking detail | Gate `view` + `ensureCustomerOwnsBooking` | Same Laravel + Next detail page | Detail `ok` empty edge | Monitor | Next presenters | PASS |
| Itinerary / PNR / payment / ticket | Shared booking presenters | `StandardBookingJsonPresenter` + status cards | None | Keep | JetPakistan design system | PASS |
| Payment proof | `submitPaymentProof` | Customer portal payment panels | None | Keep | — | PASS |
| Cancellation request | `BookingCancellationController` request workflow | `BookingCancellationPanel` | None | Keep | Request not void | PASS |
| Support tickets | Creator ownership | Customer support pages | None | Keep | — | PASS |
| Saved travelers | `user_id` scoped | Customer travelers | None | Keep | — | PASS |
| Profile | Shared `ProfileController` | `/customer/profile` | None | Keep | — | PASS |
| IDOR | Dual Gate + explicit ownership abort 403 | Same + `CustomerBookingOwnershipTest` | None | Keep tests green | — | PASS |
| Guest lookup | Parallel guest token path | Guest lookup Next/Laravel | Out of this wave polish | — | Separate guest UX | N/A |
| Post-login default | Master prefers bookings index | JetPakistan dashboard overview | Destination preference | Keep JP dashboard as hub with bookings CTA | Intentional | INTENTIONAL |

## Notes

- Do **not** copy Master Blade themes into JetPakistan public/customer UI.
- Ownership rule remains: `booking.customer_id === auth.id` (never ID-only URL trust).
