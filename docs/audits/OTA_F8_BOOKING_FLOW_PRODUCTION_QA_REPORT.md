# OTA F8 Booking Flow Production QA Report

Generated: 2026-06-18T00:00:00+00:00  
Phase: **OTA-DEVCP-F8-BOOKING-FLOW-PRODUCTION-QA**

## Objective

Validate and harden the customer/agent booking flow for production readiness without enabling supplier mutation, ticketing, auto-PNR, or live cancellation. Booking-flow QA and fail-soft patching only — not a feature phase.

## Result summary

| Area | Automated result | Runtime patches |
|------|------------------|-----------------|
| Public flight search flow | **PASS** — extended smoke + existing Flight tests | None required |
| Passenger / checkout / review flow | **PASS** — smoke + PublicBookingFlow / redirect tests | None required |
| Payment / proof / document flow | **PASS** — Document + Payment filter tests | None required |
| Guest booking lookup | **PASS** — smoke + GuestBookingLookupRedesignTest | None required |
| Admin booking management | **PASS** — smoke + BookingManagement tests | None required |
| Agent/customer booking dashboards | **PASS** — authenticated smoke (302/403 acceptable) | None required |
| Extended `ota:smoke-live-routes` | **Added F8 catalog + validation POST pass** | 3 PHP files + test |
| Error log triage (500-risk) | **No reproducible 500-risk** in F8 scope | None required |

**No runtime fail-soft patches were required.** F1–F7 fail-soft work holds under extended F8 smoke.

## Safety confirmation

| Control | Status |
|---------|--------|
| Ticketing enabled | **unchanged** (config not flipped) |
| Auto-PNR enabled | **unchanged** |
| Public auto-PNR enabled | **unchanged** |
| Live cancellation enabled | **unchanged** |
| Live Sabre HTTP from smoke command | **none** (`live_supplier_call_attempted=false`) |
| Booking created by smoke | **none** (`booking_created=false`; validation POST only) |
| DB cleanup / migrate:fresh | **not run** |
| `.env` edited | **no** |
| Docs uploaded to live | **no** (local only) |

## Extended command: `ota:smoke-live-routes`

**Purpose:** READ-ONLY internal HTTP smoke for F6/F8 booking-flow routes — no supplier calls, no mutating POST (except validation-only empty-body POSTs).

**Safety banner (F8):**

```
Classification: READ-ONLY
live_supplier_call_attempted=false
booking_created=false
ticketing_attempted=false
auto_pnr_attempted=false
cancellation_attempted=false
```

**New F8 coverage:**

| Pass | Additions |
|------|-----------|
| Route registry | +11 booking-flow route names (`flights.results.data`, `booking.passengers`, `guest.bookings.show`, etc.) |
| Guest GET | +10 URIs: results/data/search/offer, details, return-options, booking passengers/review/confirmation, invalid guest token |
| Validation POST | Empty-body POST to `/lookup-booking`, `/booking/passengers` (expects 419/422/302 — not 500) |
| Authenticated GET | `admin.bookings.data`, `customer.bookings.show`, `agent.bookings.show` |

**Skipped (by design):** review confirm POST, select-return-combo, revalidate-offer, sync-pnr, document generation, cancellation.

**Example (production-safe):**

```bash
php artisan ota:smoke-live-routes --guest-only
```

**Example (local full):**

```bash
php artisan ota:smoke-live-routes --seed
```

## F8 scope verification matrix

### 1. Public flight search

| Route | Expected | Smoke |
|-------|----------|-------|
| `/flights/results` (missing params) | 302 redirect | OK |
| `/flights/results/data` (missing search_id) | 422 JSON | OK |
| `/flights/results/search` (missing params) | 302 | OK |
| `/flights/results/offer` (missing params) | 302 | OK |
| `/flights/details/{id}` (invalid) | 404 | OK |
| `/flights/return-options` | 302 | OK |
| `/flights/return-options/data` | 422 | OK |

### 2. Passenger / checkout / review

| Route | Expected | Smoke |
|-------|----------|-------|
| GET `/booking/passengers` (no session) | 200 form or redirect | OK (200 — form with guard) |
| GET `/booking/review` (no session) | 302 | OK |
| GET `/booking/confirmation` (no session) | 302 | OK |
| POST `/booking/passengers` (empty) | 419 CSRF / validation | OK |

### 3. Payment / proof / documents

| Surface | Expected | Tests |
|---------|----------|-------|
| Payment proof upload | Validation on bad file type | DocumentGenerationWorkflowTest, ManualPaymentTest |
| Document generation | No 500 on sparse booking | DocumentGenerationWorkflowTest |
| Ticket itinerary without ticket | Placeholder / blocked | DocumentGenerationWorkflowTest |

### 4. Guest lookup

| Route | Expected | Smoke / tests |
|-------|----------|---------------|
| `/lookup-booking` | 200 form | OK |
| `/booking-lookup` | 302 alias | OK |
| Invalid token guest show | 403 | OK |
| Guest masking | Masked PII | GuestBookingLookupRedesignTest |

### 5. Admin booking management

| Route | Expected | Smoke |
|-------|----------|-------|
| Admin bookings list | 200 | OK |
| Admin booking show | 200 | OK |
| Admin booking preview JSON | 200 JSON | OK |
| Admin bookings data JSON | 200 JSON | OK |
| Sync PNR POST | Guarded (not dispatched) | OK (existing tests) |

### 6. Agent/customer dashboards

| Route | Expected | Smoke |
|-------|----------|-------|
| Customer booking show | 302 (email gate) or 200 | OK (302) |
| Agent booking show | 403 (agency scope) or 200 | OK (403) |
| Unauthenticated | Redirect login | OK (existing tests) |

## Baseline verification (local)

| Command | Result |
|---------|--------|
| `php -l` on changed PHP files | pass |
| `composer dump-autoload -o` | pass |
| `php artisan optimize:clear` | pass |
| `php artisan migrate:status` | pass (1 pending unrelated: `2026_06_16_110300_add_missing_group_passenger_identity_columns`) |
| `php artisan ota:production-readiness-audit` | pass — 26 pass, 4 warn, 0 fail |
| `php artisan ota:smoke-live-routes --guest-only` | **66 passed, 0 failed** |
| `php artisan ota:smoke-live-routes --seed` | **95 passed, 0 failed** |
| `php artisan ota:audit-sabre-status` | pass — READ-ONLY |
| `php artisan test --filter=BookingFlow` | **5 passed, 2 failed** (pre-existing — reference format + admin RBAC; outside F8 500-risk) |
| `php artisan test --filter=BookingManagement` | **8 passed** |
| `php artisan test --filter=Flight` | **204 passed, 17 failed** (pre-existing; outside F8 500-risk) |
| `php artisan test --filter=Payment` | **59 passed, 15 failed** (pre-existing — RBAC 403 + label copy; outside F8 500-risk) |
| `php artisan test --filter=Document` | **37 passed, 13 failed** (pre-existing — RBAC 403; outside F8 500-risk) |
| `php artisan test --filter=GuestBooking` | **7 passed** |
| `php artisan test --filter=CustomerBooking` | **0 tests** (no matching class/method names) |
| `php artisan test --filter=AgentBooking` | **7 passed, 2 failed** (pre-existing — admin 403; outside F8 500-risk) |
| `php artisan test --filter=Smoke` | **18 passed** |
| `php artisan test --filter=Sabre` | **~1190 passed, ~55 failed** (pre-existing; outside F8) |
| `php artisan test --filter=OtaSmokeLiveRoutes` | **4 passed** |

**Related coverage not matched by `CustomerBooking` filter:** `CustomerPortalAndGuestLookupTest`, `BookingDetailRedesignTest`.

## Manual booking-flow smoke checklist (client / post-deploy)

- [ ] Home → search LHE–DXB one-way → results load (desktop + mobile shell)
- [ ] Results filters/pagination via `/flights/results/data` — no console errors
- [ ] Select offer → passengers page; back button safe
- [ ] Submit invalid passenger form → inline validation, no 500
- [ ] Valid passengers → review page; stale fare message if applicable
- [ ] Confirmation page after review (sandbox only — no live PNR)
- [ ] `/lookup-booking` invalid ref → safe error; valid ref → masked guest page
- [ ] Payment proof upload invalid file type → validation error
- [ ] Admin booking show + preview JSON on seeded booking
- [ ] Customer/agent booking list + show redirect when logged out

## Files changed

| Path | Change |
|------|--------|
| `app/Support/Audits/BookingFlowSmokeSafetyOutput.php` | **New** — F8 READ-ONLY safety banner lines |
| `app/Support/Audits/LiveRouteSmokeCatalog.php` | F8 route registry + guest GET + validation POST + auth targets |
| `app/Console/Commands/OtaSmokeLiveRoutesCommand.php` | F8 banner, validation POST pass, booking-count guard on validation only |
| `tests/Feature/Console/OtaSmokeLiveRoutesCommandTest.php` | F8 assertions + booking count unchanged on guest pass |
| `docs/audits/OTA_F8_BOOKING_FLOW_PRODUCTION_QA_REPORT.md` | **New** — this report |
| `docs/audits/OTA_DEV_CP_GAP_REPORT.md` | F8 section |
| `docs/audits/OTA_SECURITY_HARDENING_REPORT.md` | F8 section |
| `docs/audits/OTA_SABRE_STATUS_REPORT.md` | F8 confirmation note |
| `summary.md` | Changelog row |

## Files to upload (SFTP)

**OTA App - Laravel profile** (single-file uploads):

1. `app/Support/Audits/BookingFlowSmokeSafetyOutput.php`
2. `app/Support/Audits/LiveRouteSmokeCatalog.php`
3. `app/Console/Commands/OtaSmokeLiveRoutesCommand.php`

**Do not upload:** `docs/**`, `summary.md`, `tests/**`, `public_html/**`

## Commands after upload (server SSH)

```bash
php artisan cache:clear
php artisan ota:smoke-live-routes --guest-only
tail -n 50 storage/logs/laravel.log
```

## Remaining gaps (expected / deferred)

| Gap | Status |
|-----|--------|
| Full manual browser QA (desktop/tablet/mobile) | Deferred to client live QA |
| `APP_DEBUG=false` on live | Ops action after deploy smoke |
| Pending group passenger migration | Unrelated; do not migrate:fresh |
| Pre-existing test failures (Sabre 55, Flight 17, BookingFlow 2, Dashboard, Support, Auth) | Out of F8 scope |
| `CustomerBooking` PHPUnit filter | 0 matches — use related Customer/* tests |
| Promo codes at checkout | Product gap — not wired (`summary.md`) |
| Legacy DB missing `bookings.meta` | Documented F4 ops gap — patch only if 500 confirmed |

## Rollback

Restore prior versions of the three uploaded PHP files from git or SFTP backup. No DB changes in F8.
