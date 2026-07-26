# Phase 18H — Controlled Live Search and Revalidation Probe Plan

**Status:** PLAN ONLY — **NOT EXECUTED** in Phase 18 local closure.
**Phase:** SABRE-GDS-SEARCH-CACHE-REVALIDATION-FINAL-CLOSURE-18H
**Baseline commit:** `29f21e5` (Phase 17F safety closure)
**Related defect:** DEF-18-012 (requires approved live probe)

---

## 1. Purpose

Validate post-Phase-18 search cache isolation, stale-offer gates, and authoritative revalidation linkage against the live Sabre cert environment **without** Create PNR, ticketing, cancellation, payment, email, or protected-record mutation.

---

## 2. Preconditions

| Requirement | Value |
|-------------|-------|
| Operator approval phrase | `APPROVE-LIVE-SABRE-GDS-SEARCH-REVALIDATION-PROBE-18H` |
| Environment | Sabre **cert** credentials only (`SupplierConnection` active, agency `asif-travels`) |
| `SABRE_TICKETING_ENABLED` | **false** (must remain false) |
| `suppliers.sabre.booking_live_call_enabled` | true (search/revalidation only) |
| `suppliers.sabre.revalidate_before_booking` | true |
| Protected bookings | IDs **1–3** untouched |
| Protected supplier attempts | IDs **4, 5, 7, 8, 9** untouched |
| Max wall-clock | 30 minutes |
| Retries | **0** (no automatic retry on any supplier outcome) |

---

## 3. Exact routes and UI paths (read-only / revalidation only)

| Step | Route / path | Method | Purpose |
|------|--------------|--------|---------|
| 1 | `/flights/search` | GET | Load JetPakistan search shell |
| 2 | `/flights/results` | GET | Submit search criteria (see matrix below) |
| 3 | `/flights/results/data?search_id={uuid}` | GET | Read cached offers + freshness meta |
| 4 | `/flights/results/revalidate-offer` | POST | Selected-offer revalidation (JSON) |

**Forbidden routes during probe:** `/booking/passengers` POST continuation to Create PNR, `/booking/review` submit, admin PNR commands, `sabre:controlled-create-pnr`, ticketing, cancel, void, refund, payment webhooks.

---

## 4. Search matrix (maximum 6 live shop calls)

| # | Trip | Origin | Destination | Depart | Return | Adults | Children | Infants | Cabin | Direct | Nearby | Flexible ±1d | Max shop HTTP |
|---|------|--------|-------------|--------|--------|--------|----------|---------|-------|--------|--------|--------------|---------------|
| S1 | one_way | LHE | DXB | +21d | — | 1 | 0 | 0 | economy | no | no | no | 1 |
| S2 | one_way | LHE | DXB | +21d | — | 1 | 0 | 0 | economy | yes | no | no | 1 |
| S3 | one_way | LHE | DXB | +21d | — | 1 | 0 | 0 | economy | no | yes | no | 1 |
| S4 | one_way | LHE | DXB | +21d | — | 1 | 0 | 0 | economy | no | no | yes | 1 |
| S5 | round_trip | LHE | DXB | +21d | +28d | 2 | 1 | 0 | economy | no | no | no | 1 |
| S6 | one_way | LHE | JED | +30d | — | 1 | 0 | 0 | business | no | no | no | 1 |

**Total maximum Sabre shop (BFM/v4 shop) requests:** **6**

---

## 5. Revalidation matrix (maximum 3 live revalidate calls)

After each successful search S1, S5, S6:

1. Select first bookable Sabre offer from `/flights/results/data`.
2. POST `/flights/results/revalidate-offer` with `search_id` + `offer_id`.
3. Record `offer_freshness.revalidation_status`, segment count, validating carrier, fare basis per segment.
4. **Do not** navigate to passengers/review submit.

| Probe | Source search | Max revalidate HTTP |
|-------|---------------|---------------------|
| R1 | S1 one-way LHE–DXB | 1 |
| R2 | S5 round-trip LHE–DXB | 1 |
| R3 | S6 one-way LHE–JED business | 1 |

**Total maximum Sabre revalidation requests:** **3**

**Grand maximum supplier HTTP (shop + revalidate + token):** **6 shop + 3 revalidate + ≤9 token** (token cached; expect ≤3 token calls).

---

## 6. Permitted endpoint categories

- `POST /v2/auth/token` (or configured `suppliers.sabre.token_path`)
- Sabre shop / BFM search (`v4/offers/shop`, `v4/shop/flights`, or agency-configured shop path)
- Sabre revalidation (`/v4/shop/flights/revalidate` or configured `suppliers.sabre.revalidate_path`)

---

## 7. Forbidden endpoint categories

- Create PNR / Trip Orders `createBooking`
- Passenger Records create/update
- AirTicket / ticketing / EMD
- Void / refund / cancel
- Payment gateway / AbhiPay
- Outbound email / SMS
- PIA NDC endpoints
- Any admin-only destructive command

---

## 8. Database pre/post snapshots

Capture before and after probe (read-only compare):

```sql
SELECT COUNT(*) AS bookings FROM bookings;
SELECT COUNT(*) AS supplier_bookings FROM supplier_bookings;
SELECT COUNT(*) AS supplier_booking_attempts FROM supplier_booking_attempts;
SELECT COUNT(*) AS communication_logs FROM communication_logs;
SELECT id, status FROM bookings WHERE id IN (1,2,3);
SELECT id, status FROM supplier_booking_attempts WHERE id IN (4,5,7,8,9);
```

**Pass criteria:** counts unchanged; protected row statuses unchanged; **no new** `supplier_booking_attempts` with `create` intent.

---

## 9. Protected-record verification

After probe:

```bash
php artisan tinker --execute="dump(\\App\\Models\\Booking::query()->whereIn('id',[1,2,3])->pluck('status','id')->all());"
php artisan tinker --execute="dump(\\App\\Models\\SupplierBookingAttempt::query()->whereIn('id',[4,5,7,8,9])->pluck('status','id')->all());"
```

Expected: identical to pre-probe snapshot.

---

## 10. Cache cleanup scope

Remove only keys created during probe:

- `flight_search:{search_id}` for each probe `search_id`
- `flight_search_criteria:{fingerprint}` if criteria cache was written
- Nearby strip keys matching probe dates (if strip invoked)

```bash
php artisan cache:forget flight_search:<uuid>   # per probe search_id
# or targeted Redis SCAN flight_search:* created after probe start timestamp
```

Do **not** `cache:clear` globally on production.

---

## 11. Stop conditions (immediate halt)

1. Any HTTP call to forbidden endpoint category
2. Any `bookings` / `supplier_bookings` / `supplier_booking_attempts` row inserted or updated
3. Any communication log row created
4. Protected booking/attempt status change
5. Revalidation success with segment count mismatch vs selected offer
6. Supplier 5xx twice on same step
7. Operator abort
8. Elapsed time > 30 minutes

On stop: capture logs, run post snapshot, execute cache cleanup, file incident note. **No retry.**

---

## 12. Log and evidence collection

| Artifact | Location |
|----------|----------|
| Laravel log excerpt | `storage/logs/laravel.log` (grep `flight_search`, `sabre.checkout.selected_offer_revalidation`) |
| Probe journal | `docs/phases/evidence/PHASE18H-LIVE-PROBE-JOURNAL.md` (operator-filled) |
| HTTP summaries | Sanitized HAR or redacted `Http::` log lines (no tokens, no PII) |
| search_id list | UUIDs used per matrix row |
| Freshness meta | JSON from `/flights/results/data` per search |

---

## 13. Explicit non-goals

- No Create PNR
- No `SupplierBooking` mutation
- No cancellation
- No ticketing
- No email
- No payment
- No PIA NDC
- No production data repair

---

## 14. Rollback requirements

If probe code paths are deployed separately (not part of Phase 18 local closure):

1. Revert Phase 18 runtime files per `PHASE18-ROLLBACK-PLAN.md`
2. Clear probe cache keys (section 10)
3. Verify DB snapshots match pre-probe
4. Confirm `SABRE_TICKETING_ENABLED=false`
5. Re-run `php artisan ota:route-page-health-audit --all` → `fail=0`

---

## 15. CLI alternative (revalidation-only, no UI)

For revalidation linkage spot-check without UI shop matrix:

```bash
php artisan sabre:gds-live-revalidation-only-probe \
  --connection=<cert_connection_id> \
  --origin=LHE \
  --destination=JED \
  --departure-date=YYYY-MM-DD \
  --payload-style=bfm_revalidate_v1 \
  --endpoint-path=/v4/shop/flights/revalidate \
  --passenger-json=/path/to/private/passenger.json \
  --mode=plan \
  --confirm-production=APPROVE-LIVE-SABRE-GDS-REVALIDATION-ONLY-PROBE
```

Plan mode: **zero** revalidation HTTP. Send mode requires separate approval and counts toward revalidation quota above.

---

## 16. Execution status

| Field | Value |
|-------|-------|
| Plan authored | Phase 18H |
| Executed | **NO** |
| Operator | — |
| Execution date | — |
| Evidence folder | — |

**Do not execute without explicit operator approval.**
