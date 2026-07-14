# BF8-A — Production Enablement + Sabre Operational Cleanup

**Date:** 2026-06-16  
**Status:** Steady-state operational runbook (post BF7-J-OPS proof)  
**Scope:** Env/docs alignment, admin wording, read-only smoke check. No ticketing, payment mutation, or PNR-create logic changes.

---

## Executive summary

| Item | Value |
|------|-------|
| **Production proof** | Booking 55 — PNR `TQMNEV`, `create_pnr` + `pnr_retrieve` success, sync `synced`, ticketing OFF |
| **Operational master switch** | `SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED` + GDS + PUBLIC (all three required) |
| **Verified diagnostics** | `unknown_controlled_only` is diagnostic-only — does not block operational PNR when `SabreOperationalPnrReadiness::wouldAttemptPnr()` is true |
| **Smoke check** | `php artisan sabre:operational-pnr-smoke-check --booking={id}` |

**Supersedes:** BF7-J-PREP execution window docs for steady-state ops. BF7-J-OPS / FIX1–FIX4 are closed.

---

## 1. Operational flag matrix

| Flag | Production operational | Repo template default |
|------|------------------------|----------------------|
| `SABRE_BOOKING_ENABLED` | `true` | `false` |
| `SABRE_BOOKING_LIVE_CALL_ENABLED` | `true` | `false` |
| `SABRE_TICKETING_ENABLED` | **`false`** (hard rule) | `false` |
| `SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED` | `true` | `false` |
| `SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED` | `true` | `false` |
| `SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED` | `true` (master switch) | `false` |
| `SABRE_CPNR_ALLOW_NN_HALT_ON_STATUS_CERT_OPERATIONAL` | `true` (CERT window only) | `false` |

### Master switch note

`SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED` retains a legacy name (E5E/BF7-J) but after **BF7-J-OPS** it acts as the **operational auto-PNR master switch**. It requires:

- `SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED=true`
- `SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED=true`

It does **not** require verified-route historical evidence. Structural gate: `App\Support\Bookings\SabreOperationalPnrReadiness`.

### unknown_controlled_only

- **Verified panel** / `SabreVerifiedAutoPnrReadiness` may show `unknown_controlled_only` — **diagnostics only**.
- **Operational lane** uses `SabreOperationalPnrReadiness::wouldAttemptPnr()` — `unknown_controlled_only` does **not** block when structure + flags pass.

---

## 2. Booking 55 reference profile (safe fields)

| Field | Value |
|-------|-------|
| reference | `PAR-POR6MG6I` |
| pnr | `TQMNEV` |
| supplier_reference | `TQMNEV` |
| supplier_booking_status | `pending_payment_or_ticketing` |
| payment_status | `unpaid` |
| ticketing_status | `pending` |
| create_pnr attempt | id=87, success |
| pnr_retrieve attempt | id=88, success |
| sync status | `synced` |
| endpoint | `/v1/trip/orders/getBooking` |
| segments | GF765 LHE→BAH class N HK; GF500 BAH→DXB class N HK |
| is_ticketed | false |
| is_cancelable | true |
| airline_locator | `TTPZIC` |

---

## 3. Sync PNR wiring (verified)

| Layer | Name |
|-------|------|
| Route | `POST admin/bookings/{booking}/sync-pnr-itinerary` → `admin.bookings.sync-pnr-itinerary` |
| Controller | `Admin\BookingManagementController@syncPnrItinerary` |
| Trait | `HandlesSabrePnrItinerarySync::syncPnrItinerary` |
| Service | `SabrePnrItinerarySyncService::sync()` |
| Admin UI | "Sync PNR itinerary" / "Re-sync PNR itinerary" |

Staff parity: `Staff\BookingController@syncPnrItinerary` (same trait).

---

## 4. Smoke check command

### Local / testing

```bash
php artisan sabre:operational-pnr-smoke-check --booking=55
php artisan sabre:operational-pnr-smoke-check --booking=55 --json
```

### Production (read-only, no Sabre HTTP)

```bash
php artisan sabre:operational-pnr-smoke-check --booking=55 --confirm=READONLY-OPERATIONAL-PNR-SMOKE
```

### Pass criteria (post-checkout operational success)

- PNR present
- Latest `create_pnr` attempt success
- Latest `pnr_retrieve` attempt success
- `meta.pnr_itinerary_sync.status` = `synced`
- `SABRE_TICKETING_ENABLED=false`
- No issued tickets / `is_ticketed=false`
- Safe `pnr_itinerary_snapshot` segments populated

Output includes `smoke_check_passed=true|false`.

### Related read-only commands

```bash
php artisan sabre:inspect-operational-pnr-readiness --booking={id} --confirm=READONLY-OPERATIONAL-PNR-READINESS
```

---

## 5. Final status matrix

| Check | Expected (operational success) |
|-------|--------------------------------|
| `SABRE_BOOKING_ENABLED` | true |
| `SABRE_BOOKING_LIVE_CALL_ENABLED` | true |
| `SABRE_TICKETING_ENABLED` | **false** |
| `SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED` | true |
| `SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED` | true |
| `SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED` | true |
| `SABRE_CPNR_ALLOW_NN_HALT_ON_STATUS_CERT_OPERATIONAL` | true (CERT only) |
| Booking PNR | present |
| `supplier_booking_status` | `pending_payment_or_ticketing` or booked equivalent |
| `create_pnr` attempt | success |
| `pnr_retrieve` attempt | success |
| `pnr_itinerary_sync.status` | synced |
| Segments | HK, safe snapshot populated |
| `is_ticketed` | false |
| Customer/agent UI | PNR in timeline; PNR/airline itinerary; no ticket numbers |
| Admin UI | success alert; sync panel synced; no staff_review |

---

## 6. Production upload list (code)

1. `app/Support/Bookings/AdminBookingSupplierActions.php`
2. `resources/views/dashboard/admin/bookings/show.blade.php`
3. `app/Console/Commands/SabreOperationalPnrSmokeCheckCommand.php`

Env changes on server `.env` directly (not SFTP from repo secrets).

---

## 7. Post-upload SSH commands

```bash
php artisan optimize:clear
php artisan view:clear
php artisan route:list --name=bookings.sync-pnr-itinerary
php artisan sabre:operational-pnr-smoke-check --booking=55 --confirm=READONLY-OPERATIONAL-PNR-SMOKE
tail -n 40 storage/logs/laravel.log
```

After `.env` flag changes:

```bash
php artisan config:clear
php artisan cache:clear
```

---

## 8. Rollback

```bash
# .env — disable operational auto-PNR window:
# SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED=false
# SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED=false
# SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED=false
# SABRE_CPNR_ALLOW_NN_HALT_ON_STATUS_CERT_OPERATIONAL=false
# SABRE_TICKETING_ENABLED=false

php artisan config:clear
php artisan cache:clear
```

Revert uploaded PHP/Blade if wording/command causes issues.

---

## Files

| File | Purpose |
|------|---------|
| `docs/bf8-a-operational-enablement.md` | This runbook |
| `docs/bf7-j-prep-runbook.md` | BF7-J prep (closed; see BF8-A for steady state) |
| `.env.example` / `.env.production.example` | Flag documentation |
| `app/Console/Commands/SabreOperationalPnrSmokeCheckCommand.php` | Smoke check CLI |
