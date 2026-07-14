# BF7-J-PREP — Read-Only Controlled Public Auto-PNR Test Readiness

**Date:** 2026-06-16  
**Status:** **CLOSED** — BF7-J-OPS operational lane proven (Booking 55). Steady-state ops: [`bf8-a-operational-enablement.md`](bf8-a-operational-enablement.md)  
**Scope:** Read-only diagnostics, counters, go/no-go validation. No `.env` changes in PREP. No checkout submit. No Sabre HTTP.

---

## Executive summary

| Decision | Value |
|----------|-------|
| **BF7-J live candidate** | Fresh public checkout: **GF LHE→BAH→JED**, flights **767+171**, RBD **W/W**, fare **WDLIT3PK**, dates **2026-07-29/30**, branded fare tier, pay later |
| **Booking 53 (QR→QR, ECONVENIEN)** | **Diagnostic only** — not suitable for live Auto-PNR (`unknown_controlled_only`, no success evidence) |
| **Booking 44** | Evidence template only — PNR already exists, do not retry |
| **Code changes in PREP** | None (diagnostic tooling sufficient) |

---

## 1. Flag state (§4b)

### Local dev snapshot (2026-06-16)

```
verified_multiseg_auto_pnr_enabled=false
cpnr_connecting_same_carrier_public_checkout_enabled=false
cpnr_connecting_same_carrier_gds_enabled=false
ticketing_enabled=false
booking_enabled=true
booking_live_call_enabled=true
```

### Production — run on server

```bash
php artisan tinker --execute="
foreach ([
  'verified_multiseg_auto_pnr_enabled',
  'cpnr_connecting_same_carrier_public_checkout_enabled',
  'cpnr_connecting_same_carrier_gds_enabled',
  'ticketing_enabled',
  'booking_enabled',
  'booking_live_call_enabled',
] as \$k) {
  echo \$k.'='.(config('suppliers.sabre.'.\$k) ? 'true' : 'false').PHP_EOL;
}
"
```

**Expected PREP state:** first three `false`, `ticketing_enabled=false`.

---

## 2. Baseline counters (§4a)

See [`bf7-j-prep-before-counters.txt`](bf7-j-prep-before-counters.txt). Production ops paste server output into that file before BF7-J.

```bash
php artisan tinker --execute="
echo 'bookings='.\App\Models\Booking::count().PHP_EOL;
echo 'supplier_booking_attempts='.\App\Models\SupplierBookingAttempt::count().PHP_EOL;
echo 'create_pnr_attempts='.\App\Models\SupplierBookingAttempt::where('action','create_pnr')->count().PHP_EOL;
echo 'supplier_bookings='.\App\Models\SupplierBooking::count().PHP_EOL;
echo 'booking_tickets='.\App\Models\BookingTicket::count().PHP_EOL;
"
```

---

## 3. Booking 53 diagnostics (§4c) — diagnostic only

### Production commands

```bash
php artisan sabre:inspect-public-auto-pnr-eligibility --booking=53 --confirm=READONLY-PUBLIC-AUTO-PNR-ELIGIBILITY --reevaluate

php artisan sabre:diagnose-verified-auto-pnr-candidate --booking=53 --confirm=READONLY-EVIDENCE-DIAG

php artisan sabre:diagnose-verified-auto-pnr-candidate --booking=53 --confirm=READONLY-PRECHECKOUT-DRYRUN --precheckout
```

### BF7-I verified result (production Booking 53)

| Field | Value |
|-------|-------|
| `live_supplier_call_attempted` | false |
| `eligible` | false |
| `reason_code` | `auto_pnr_flag_disabled` |
| `selected_brand_code` | ECONVENIEN |
| `brand_shape` | object_content |
| `carrier_chain` | QR→QR |
| `payment_mode` | pay_later_booking_request |
| `failed_conditions` | auto_pnr_flag_enabled, public_flag_enabled |
| `pnr` | null |

### Local QR proxy validation (fixture id=2, 2026-06-16)

| Command | Key outputs |
|---------|-------------|
| BF7 inspect | `eligible=false`, `reason_code=auto_pnr_flag_disabled`, `selected_brand_code=ECONVENIEN`, `failed_conditions` includes flags |
| E5G diagnose | `evidence_status=unknown_controlled_only`, `recommended_action=manual_review`, `public_auto_pnr_allowed_now=false` |
| E5H precheckout | `recommended_checkout_action=continue_manual_safe`, `should_attempt_auto_pnr=false` |

**Conclusion:** Booking 53 confirms BF7-I wiring; **not** BF7-J live candidate.

---

## 4. GF draft go/no-go (§4d)

### Browser path (ops — stop at review before flag ON)

1. Public flights: **LHE → JED**, one-way, depart ~**2026-07-29**.
2. Select **Gulf Air (GF)** connecting via **BAH** (2 segments).
3. Confirm flights **767 + 171** (not 765/173).
4. Select a **branded fare tier** (brand code stored in meta).
5. Complete passenger + contact; pay later at review.
6. **Stop before submit** until PREP go/no-go passes.

### Production diagnostic commands (replace `{DRAFT_ID}`)

```bash
php artisan sabre:inspect-public-auto-pnr-eligibility --booking={DRAFT_ID} --confirm=READONLY-PUBLIC-AUTO-PNR-ELIGIBILITY --reevaluate

php artisan sabre:diagnose-verified-auto-pnr-candidate --booking={DRAFT_ID} --confirm=READONLY-EVIDENCE-DIAG

php artisan sabre:diagnose-verified-auto-pnr-candidate --booking={DRAFT_ID} --confirm=READONLY-PRECHECKOUT-DRYRUN --precheckout
```

### Go/no-go criteria (flags still OFF)

| Check | Required |
|-------|----------|
| `evidence_status` | `exact_success_evidence` |
| `matched_success_booking_id` | `44` |
| `recommended_action` | `auto_pnr_candidate` |
| `readiness_reason_code` | `feature_flag_disabled` |
| BF7 `failed_conditions` | **Only** `auto_pnr_flag_enabled` + `public_flag_enabled` |
| Segment flights | **767 / 171** (not 765/173) |
| Blockers | No `fresh_search_required`, `blocked_same_offer`, `host_noop_blocked` |

### Local GF proxy validation (fixture id=3, 2026-06-16)

| Command | Key outputs |
|---------|-------------|
| E5G diagnose | `evidence_status=exact_success_evidence`, `matched_success_booking_id=44`, `recommended_action=auto_pnr_candidate`, `readiness_reason_code=feature_flag_disabled` |
| E5H precheckout | `dry_run_status=exact_success_evidence`, `recommended_checkout_action=candidate_auto_pnr_later`, flights 767/171 |
| BF7 inspect | `reason_code=auto_pnr_flag_disabled`, `selected_brand_code=FL`; verify production draft has **only** flag failures in `failed_conditions` |

**Local note:** Fixture may also show `no_risky_itinerary_block` when `passenger_records_block_risky_itinerary_live=true` without multi-seg verifier pass. Production Booking 53 did not fail this — confirm on real GF draft before BF7-J.

---

## 5. BF7-J execution (separate ops approval)

**Do not run until:** GF draft go/no-go passes + ops signs below.

### Flags ON (requires `GDS` + `PUBLIC` + `VERIFIED_MULTISEG`)

```env
SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED=true
SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED=true
SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED=true
SABRE_TICKETING_ENABLED=false
```

```bash
php artisan config:clear
php artisan cache:clear
```

Verify all three Auto-PNR flags `true`, `ticketing_enabled=false`.

### Controlled checkout

1. Rerun §4a counters → save `BF7-J-before-counters.txt`.
2. Single GF draft review submit (pay later) only.
3. Post-submit: confirmation (no internal codes), admin BF7-I panel, §4a after counters.
4. `tail -n 80 storage/logs/laravel.log | grep -E 'sabre_public_auto_pnr_eligibility_evaluated|verified_multiseg|create_pnr|passenger_records'`
5. **Immediate rollback** (§6).

### Blast radius

When GDS+PUBLIC flags ON, **all** same-carrier 2-segment GDS checkouts may get `live_booking_allowed=true`. Keep window short; no parallel public traffic.

---

## 6. Rollback (§6)

```bash
# .env — set all Auto-PNR/public flags false:
# SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED=false
# SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED=false
# SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED=false
# SABRE_TICKETING_ENABLED=false

php artisan config:clear
php artisan cache:clear
```

Verify all four read `false`.

---

## 7. Risks and blockers

| Risk | Severity | Mitigation |
|------|----------|------------|
| GF767/171 not shop-available | Blocker | New search dates; precheckout must show `auto_pnr_candidate` |
| GF765/173 (Booking 46 pattern) | Blocker | Verify segment_summary |
| Public flag blast radius | High | Short window; single checkout |
| Missing `GDS_ENABLED` with public flag | Blocker | Enable all three flags together |
| Brand AirPrice 422 | Medium | Keep compare gate OFF |
| Booking 53 for live test | Blocker | Diagnostic only |
| Retry 44/43/46 | Blocker | New draft only |

---

## 8. Hard avoid patterns

| Pattern | Booking | Reason |
|---------|---------|--------|
| GF765+GF173, 2026-07-31/08-01 | 46 | `exact_failed_evidence` |
| PK LHE→KHI→JED | 43 | `host_noop_blocked` |
| IDs 43, 46 | — | BF7 blocklist |
| Booking 44 resubmit | 44 | `pnr_already_exists` |

---

## 9. Automated test validation (local, 2026-06-16)

```
php artisan test --filter=SabreVerifiedAutoPnrCandidateDiscoveryTest  → 8 passed
php artisan test --filter=SabreBrandedFarePublicAutoPnrEligibilityTest → 20 passed
```

Local fixture bootstrap (testing only): `php scripts/bf7-j-prep-local-fixtures.php`

---

## 10. PREP closure checklist

- [x] §4a before counters template saved (`docs/bf7-j-prep-before-counters.txt`)
- [x] §4b local flags documented (all Auto-PNR flags `false`)
- [x] §4c Booking 53 profile validated (QR = diagnostic only; local proxy + BF7-I production data)
- [x] §4d GF go/no-go criteria validated (local GF fixture → `exact_success_evidence`, `auto_pnr_candidate`)
- [x] §5–§6 BF7-J execution + rollback runbook documented
- [ ] **Production:** paste §4a/§4b server output into counter file
- [ ] **Production:** run §4c on Booking 53 via SSH
- [ ] **Production:** create GF draft; run §4d on `{DRAFT_ID}`
- [ ] **Ops approval** for BF7-J live window (sign-off below)

### Ops approval gate

| Role | Name | Date | Approved |
|------|------|------|----------|
| Ops lead | | | [ ] |
| Sabre cert owner | | | [ ] |

**BF7-J-PREP closed for engineering** when production §4c/§4d commands are run and ops approves §5 execution.

**BF7-J-OPS closed (2026-06-16):** Booking 55 operational proof. Follow **BF8-A** runbook for production enablement and smoke checks: [`docs/bf8-a-operational-enablement.md`](bf8-a-operational-enablement.md).

---

## Files

| File | Purpose |
|------|---------|
| `docs/bf7-j-prep-runbook.md` | This runbook |
| `docs/bf7-j-prep-before-counters.txt` | Counter snapshot template |
| `scripts/bf7-j-prep-local-fixtures.php` | Local-only diagnostic fixtures (not for production) |
