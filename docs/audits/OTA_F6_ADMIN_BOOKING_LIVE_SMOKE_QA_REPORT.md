# OTA F6 Admin Booking Live Smoke QA Report

Generated: 2026-06-17T18:15:00+00:00  
Phase: **OTA-DEVCP-F6-ADMIN-BOOKING-LIVE-SMOKE-QA**

## Objective

Live-safe logged-in browser/session QA readiness for Dev CP, Platform Admin booking pages, Sabre diagnostic panels, support, booking lookup, and role dashboards. Smoke QA and fail-soft patch phase — not a feature phase.

## Result summary

| Area | Automated result | Runtime patches |
|------|------------------|-----------------|
| Dev CP (11 routes) | **PASS** — 67 Developer tests + smoke command | None required |
| Admin booking + diagnostics | **PASS** — 8 BookingManagement tests + smoke command | None required |
| Role dashboards (staff/agent/customer) | **PASS** — smoke command (302/200 acceptable) | None required |
| Public routes | **PASS** — smoke command guest pass | None required |
| Error log triage (prior gaps) | **No new 500-risk** in F6 scope | None required |
| New smoke command | **Added** `ota:smoke-live-routes` | 2 new PHP files + test |

**No 500-risk patches were required.** F1–F5 fail-soft work holds under automated smoke.

## Safety confirmation

| Control | Status |
|---------|--------|
| Ticketing enabled | **unchanged** (config not flipped) |
| Auto-PNR enabled | **unchanged** |
| Live cancellation enabled | **unchanged** |
| Live Sabre HTTP from smoke command | **none** (`live_supplier_call_attempted=false`) |
| DB cleanup / migrate:fresh | **not run** |
| `.env` edited | **no** |

## New command: `ota:smoke-live-routes`

**Purpose:** Repeatable internal HTTP/kernel smoke for F6 curated routes — no supplier calls, no mutating POST.

**Options:**

| Option | Use |
|--------|-----|
| `--guest-only` | Safe on production SSH — public GET routes + route registry only |
| `--seed` | Local/testing — runs `OtaFoundationSeeder` when demo users missing |
| `--fail-fast` | Stop on first failure |

**Passes:**

1. **Registry** — 35 named F6 routes must exist (`Route::has`)
2. **Guest dispatch** — 11 public GET URIs via HTTP kernel
3. **Authenticated dispatch** (unless `--guest-only`) — Dev CP session, platform admin, staff, agent, customer GET routes

**Acceptable HTTP:** 200, 302, 403, 404, 405, 410, 419, 422  
**Fail HTTP:** 500, 502, 503, uncaught exceptions, forbidden secret patterns in body

**Example (production-safe):**

```bash
php artisan ota:smoke-live-routes --guest-only
```

**Example (local full):**

```bash
php artisan ota:smoke-live-routes --seed
```

## Baseline verification (local)

| Command | Result |
|---------|--------|
| `composer dump-autoload -o` | pass |
| `php artisan optimize:clear` | pass |
| `php artisan migrate:status` | pass (1 pending unrelated: `2026_06_16_110300_add_missing_group_passenger_identity_columns`) |
| `php artisan ota:audit-sabre-status` | pass — READ-ONLY, `live_supplier_call_attempted=false` |
| `php artisan test --filter=Developer` | **67 passed** |
| `php artisan test --filter=BookingManagement` | **8 passed** |
| `php artisan test --filter=Dashboard` | **89 passed, 10 failed** (pre-existing — `AgentPortalDashboardTest` KPI copy; outside F6) |
| `php artisan test --filter=Support` | **475 passed, 13 failed** (pre-existing — F5 sync label strings in `AdminBookingSupplierActionsTest`; outside F6 500-risk) |
| `php artisan test --filter=Auth` | **160 passed, 20 failed** (pre-existing — agency_admin 403 expectations; outside F6) |
| `php artisan test --filter=Smoke` | **14 passed** (includes new `OtaSmokeLiveRoutesCommandTest`) |
| `php artisan test --filter=Sabre` | **1190 passed, 55 failed** (pre-existing; outside F6) |
| `php artisan test --filter=OtaSmokeLiveRoutes` | **3 passed** |
| `php artisan ota:smoke-live-routes --guest-only` | **45 passed, 0 failed** |
| `php artisan ota:smoke-live-routes --seed` | **71 passed, 0 failed** |

## F6 scope verification matrix

### 1. Dev CP

| Route | Expected | Smoke |
|-------|----------|-------|
| `/dev/cp/login` | 200, dedicated copy | OK |
| `/dev/cp` | 200, no Companies nav | OK |
| `/dev/cp/companies` | 302 → users + status message | OK |
| `/dev/cp/users` | Platform Admin accounts only | OK (tests + manual) |
| `/dev/cp/modules` | Deployment/global scope | OK |
| `/dev/cp/sabre-status` | Read-only, no Sabre API | OK |
| `/dev/cp/health`, `/deployment`, `/security-events`, `/group-ticketing`, `/dashboards` | 200 | OK |

### 2. Platform Admin booking

| Route | Expected | Smoke |
|-------|----------|-------|
| Admin dashboard | 200 | OK |
| Admin bookings list | 200 | OK |
| Admin booking show | 200, Sabre fallback panels | OK |
| Admin booking preview JSON | 200 JSON | OK |
| Sync PNR POST | Guarded (tests — not dispatched by smoke command) | OK (existing tests) |
| Supplier diagnostics | 200, no raw payloads | OK |
| Admin support tickets | 200 | OK |

### 3. Staff / Agent / Customer

| Route | Expected | Smoke |
|-------|----------|-------|
| Guest protected URLs | Redirect, not 500 | OK (existing tests) |
| Staff/agent/customer dashboards + bookings + support | 200 or 302 (customer email gate) | OK |

### 4. Public routes

| Route | Expected | Smoke |
|-------|----------|-------|
| `/`, `/login`, `/register`, `/forgot-password` | 200 | OK |
| `/password/forgot` | 302 → forgot-password | OK |
| `/lookup-booking`, `/booking-lookup` | 200 / 302 | OK |
| `/support`, `/flights` | 200 / 302 | OK |
| `/flights/results` (no params) | 302 validation redirect | OK |
| `/flights/return-options/data` (no params) | 422 | OK |

## Error log triage (prior gaps)

| Category | F6 status |
|----------|-----------|
| Missing optional columns | No new 500 in smoke; existing `Schema::hasColumn` guards retained |
| Ambiguous SQL `created_at` | Fixed F2/F4; Dashboard/booking tests pass in F6 scope |
| Undefined methods | Fixed F2; smoke passes admin booking show/preview |
| Route not defined | F4 aliases; registry pass confirms all names |
| Class not found | No failures in smoke |
| Turnstile issues | GET pages render; fail-closed on POST by design |
| Sabre diagnostic panel | F4/F5 fail-soft; compact panel renders |
| `APP_DEBUG=true` on live | **Ops action** — see below |

## APP_DEBUG recommendation (live ops)

After F6/F7 manual browser smoke is complete:

1. Set **`APP_DEBUG=false`** in live `.env` (currently flagged unsafe in security audit).
2. Run `php artisan config:clear` on server.
3. Re-run `php artisan ota:smoke-live-routes --guest-only` and tail `storage/logs/laravel.log`.

Do not print or commit env secrets.

## Manual browser smoke checklist (live)

Use after SFTP upload of runtime files. Requires real credentials per role.

### Dev CP (developer user — not platform admin)

- [ ] `/dev/cp/login` — 200, dedicated copy, no public forgot-password link
- [ ] Login → `/dev/cp` — 200, **Companies nav not visible**
- [ ] `/dev/cp/companies` — redirect to Platform Admins + scoped message
- [ ] `/dev/cp/users`, `/modules`, `/sabre-status`, `/health`, `/deployment`, `/security-events`, `/group-ticketing`, `/dashboards` — all 200, no 500
- [ ] Sabre page — read-only snapshot, no live API call indicators

### Platform Admin

- [ ] `/admin` dashboard
- [ ] `/admin/bookings` list
- [ ] Open a Sabre booking → supplier tab shows fallback/compact diagnostic panels
- [ ] Booking preview JSON — 200 or 422, not 500
- [ ] POST sync-pnr-itinerary on **ineligible** booking only — clear block message (do not sync live PNR)
- [ ] `/admin/reports/supplier-diagnostics` — renders, no raw Sabre payloads/secrets
- [ ] `/admin/support/tickets` — list + open ticket

### Staff / Agent / Customer

- [ ] Guest → `/staff`, `/agent`, `/customer`, `/admin` redirect to login (not 500)
- [ ] Staff: dashboard, bookings, support tickets
- [ ] Agent: dashboard, bookings, support tickets
- [ ] Customer: dashboard, bookings, support (after email verification if required)

### Public

- [ ] `/`, `/login`, `/register`, `/forgot-password`
- [ ] `/password/forgot` → `/forgot-password`
- [ ] `/lookup-booking`, `/booking-lookup` redirect
- [ ] `/support`, `/flights` redirect
- [ ] `/flights/results` and `/flights/return-options/data` without params — safe validation, not 500

### Post-smoke

- [ ] `APP_DEBUG=false` on live after F6/F7 complete
- [ ] `php artisan optimize:clear` + check logs

## Files changed (local)

- `app/Console/Commands/OtaSmokeLiveRoutesCommand.php` (new)
- `app/Support/Audits/LiveRouteSmokeCatalog.php` (new)
- `tests/Feature/Console/OtaSmokeLiveRoutesCommandTest.php` (new)
- `docs/audits/OTA_F6_ADMIN_BOOKING_LIVE_SMOKE_QA_REPORT.md` (new)
- `docs/audits/OTA_SECURITY_HARDENING_REPORT.md` (updated)
- `docs/audits/OTA_DEV_CP_GAP_REPORT.md` (updated)
- `docs/audits/OTA_SABRE_STATUS_REPORT.md` (regenerated via audit command)
- `summary.md` (updated)

## Files to upload (SFTP)

**OTA App - Laravel** (single-file uploads):

- `app/Console/Commands/OtaSmokeLiveRoutesCommand.php`
- `app/Support/Audits/LiveRouteSmokeCatalog.php`

**Exclude:** docs, summary, tests, public_html

## Commands after upload (server SSH)

```bash
cd /home/u654883295/domains/haseebasif.com/ota_app
composer dump-autoload -o
php artisan optimize:clear
php artisan route:list | grep -Ei "dev.cp|admin.bookings|ota:smoke"
php artisan ota:smoke-live-routes --guest-only
tail -n 50 storage/logs/laravel.log
```

## Remaining gaps (expected / deferred)

- **Full manual browser QA** (desktop/tablet/mobile all portals) — deferred per sprint workflow; checklist above
- **`APP_DEBUG=true` on live** — ops manual step after F6/F7
- **Pre-existing test failures** — Dashboard (10), Support-filter (13 label mismatches), Auth (20), Sabre (55) — outside F6 500-risk scope
- **Pending migration** `2026_06_16_110300_add_missing_group_passenger_identity_columns` — unrelated; do not migrate:fresh
- **F5 sync button label tests** — `AdminBookingSupplierActionsTest` expects old "Sync PNR itinerary" strings; product copy changed to "Retrieve/sync PNR itinerary" — test update deferred

## Rollback

Restore prior versions of the two uploaded PHP files via SFTP/git. No DB changes.
