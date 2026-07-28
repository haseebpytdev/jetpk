# SABRE-GDS-CUSTOMER-AGENT-BOOKING-LIFECYCLE-WIRING-MATRIX-AND-ADMIN-DASHBOARD-500-FORENSIC-AUDIT-17

## Phase metadata

| Field | Value |
|--------|--------|
| Phase name | SABRE-GDS-CUSTOMER-AGENT-BOOKING-LIFECYCLE-WIRING-MATRIX-AND-ADMIN-DASHBOARD-500-FORENSIC-AUDIT-17 |
| Branch | `phase/JETPK-HERO-VISUAL-CLARITY-FIX` (working tree; branch per workflow should be cut from `claude/ui-master` before merge) |
| Objective | Prove customer/agent checkout uses the same Sabre GDS lifecycle as operator QA; audit route continuity; reproduce/tolerate admin dashboard production data shapes; diagnose HTTP 500 without live supplier calls |
| Final status | **Engineering closure complete for in-scope code/tests**; production dashboard exception **not captured in logs** (see § Admin dashboard) |

## Included scope

- Customer/agent route-to-service trace (source + tests)
- Authoritative revalidation handoff (documented)
- Route-continuity fixture regression (descriptor misalignment + disconnected)
- Admin dashboard JetPakistan theme aligned with canonical overview Blade
- Read-only forensic Artisan diagnostic
- Production-shape dashboard feature test (Bookings A/B/C analogues)

## Excluded scope

- Live Sabre shopping/revalidation/create/retrieve/cancel
- Production DB mutation, migrations, seeders on production
- Rewriting Bookings 1–3, attempts 4/5/7/8, Booking 2, FEZJFP
- Manual browser booking
- Claiming carrier-live proof for all airlines

## Investigation findings

### Customer and guest checkout (shared)

| Step | Method | URI | Route name | Handler |
|------|--------|-----|------------|---------|
| Search | GET/POST | `/flights/search` | `flights.search` | `FlightController` |
| Results | GET | `/flights/results` | `flights.results` | `FlightController` |
| Passengers | GET/POST | `/booking/passengers` | `booking.passengers` | `Frontend\BookingController@passengers` |
| Review | GET/POST | `/booking/review` | `booking.review` | `Frontend\BookingController@review` |
| Confirmation | GET | `/booking/confirmation` | `booking.confirmation` | `Frontend\BookingController@confirmation` |

**Final PNR dispatch (customer/guest/agent-shared):** `POST booking.review` → `revalidateCheckoutBeforeConfirmation` → Sabre branch → `SabreBookingService::runPublicReviewDryRun` → `BookingService::submitBookingRequest` → redirect `booking.confirmation`. Idempotency: `Cache::lock('public-booking-review-submit:{booking_id}')`, draft/`submitted_at` guards.

**Admin/staff supplier create (separate path):** `POST admin|staff/bookings/{booking}/supplier-booking` → `BookingProviderRouter::createSupplierBooking` → `SabreBookingService::createSupplierBooking` (not the public review dry-run entry).

### Agent

Agents use the **same** public routes (`booking.passengers`, `booking.review`) after `agent.bookings.create` sets `AgentBookingContext` session. No parallel Sabre create implementation in `AgentBookingController`.

### Legacy/bypass paths

- `BookingProviderRouter` blocks unknown providers; Sabre delegates only to `SabreBookingService`.
- No Parwaaz/master checkout fallback in `BookingController` review path (client theme resolver is JetPakistan).
- Duffel/IATI/PIA/AirBlue routed separately by `supplier_provider` meta.

### Route continuity (`route_continuity_failed`)

Implemented in `SabreFlightSearchNormalizer`: leg/schedule descriptor resolution → `resolveSegmentOrderWithOptionalReverse` → `segmentModelsRouteContinuity` → optional RT whole-itinerary rebuild. Production log ordering (e.g. DXB→JED before LHE→KHI) is consistent with **descriptor/array order** before correction; valid chains are reconstructed when reversal or leg refs prove continuity; disconnected fixtures remain rejected.

### Admin dashboard HTTP 500

- Captured `production.ERROR` for `--carrier` is **Artisan CLI**, not `/admin`.
- No stack trace for dashboard in provided evidence → **root cause not yet captured on production**.
- **Likely production gap identified in code:** `themes.admin.jetpakistan.index` was a **reduced** dashboard while tests and canonical UI live in `dashboard.admin.index` — misalignment could cause missing components or runtime errors on some data shapes. **Fix:** JetPakistan theme index now mirrors canonical overview with `client_route()` links.

## Files changed

| File | Change |
|------|--------|
| `resources/views/themes/admin/jetpakistan/index.blade.php` | Replaced with canonical admin overview; `client_route()` for prefixed URLs |
| `app/Console/Commands/AdminDashboardForensicDiagnosticCommand.php` | **New** read-only diagnostic |
| `tests/Feature/SabreGdsCustomerAgentBookingLifecycleWiringMatrixPhase17Test.php` | **New** phase tests (7) |
| `docs/phases/...-17-SUMMARY.md` | This document |

## Tests executed

```text
php artisan test tests/Feature/SabreGdsCustomerAgentBookingLifecycleWiringMatrixPhase17Test.php
→ 7 passed, 17 assertions, 0 live HTTP (Http::fake in suite)
```

## Safety confirmations

- Zero live Sabre calls in phase test suite
- Bookings 1–3 on production **not modified** (local-only seeds in tests)
- Attempts 4/5/7/8 **not modified**
- `SABRE_TICKETING_ENABLED` unchanged

## Read-only SSH verification (prepare only — do not run destructively)

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
project=/home/pkjetp/jetpk_app
cd "$project"

$PHP artisan route:list --name=admin.dashboard
$PHP artisan route:list --name=booking.review
$PHP $project/artisan ota:admin-dashboard-forensic-diagnostic --correlation=operator-manual-$(date +%s) --render-view

$PHP artisan tinker --execute="echo json_encode(['cancelled'=>\App\Models\Booking::where('status','cancelled')->count(),'pending'=>\App\Models\Booking::where('status','pending')->count()]);"

grep -E 'admin\.dashboard\.forensic_diagnostic|admin dashboard' storage/logs/laravel.log | tail -n 30
```

## SFTP runtime manifest

- `resources/views/themes/admin/jetpakistan/index.blade.php`
- `app/Console/Commands/AdminDashboardForensicDiagnosticCommand.php`

## Production cache activation (after upload)

```bash
$PHP artisan view:clear
$PHP artisan route:clear
$PHP artisan config:clear
# optional if opcache stale: touch bootstrap/cache or php-fpm reload per host policy
```

## Rollback

Restore previous `themes/admin/jetpakistan/index.blade.php` from git; remove diagnostic command if undesired; `view:clear`.

## Final classification

| Bucket | Items |
|--------|--------|
| **A. Engineering-verified** | Shared `booking.review` → `SabreBookingService::runPublicReviewDryRun`; router → `createSupplierBooking`; route continuity fixtures; dashboard production-shape render test; forensic command |
| **B. Structurally covered** | One-way/return matrix via existing fixtures + normalizer (not every carrier live) |
| **C. Later acceptance** | Authenticated customer/agent UI E2E booking |
| **D. Later live supplier** | New PNR shapes not represented in sanitized fixtures |

## Commit SHA

_Not committed in this pass (user did not request commit)._
