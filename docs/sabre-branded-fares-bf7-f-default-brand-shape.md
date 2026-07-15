# SABRE-BRANDED-FARES-BF7-F — Promote object_content Brand Wire

**Date:** 2026-06-15  
**Scope:** Payload shape correction only. No public PNR enablement, no ticketing, no payment mutation, no env-flag changes.

---

## CERT evidence (BF7-E closure)

| PNR | Brand wire | Result |
|-----|------------|--------|
| **RQFUYD** | `Brand: [{ "content": "FL" }]` (`object_content`) | PNR exists; segment HK; fare family FREEDOM / FL; fare basis VOWFL; booking class V; baggage hint includes 30; ticketing false |
| **QJUAKV** | Brand omitted (`omit_brand`) | PNR exists; segment HK; fare family ECOLIGHT / LT fallback; fare basis VOWNBAG; booking class V; baggage hint includes 0; **does not preserve FREEDOM** |

**Conclusion:** The accepted Sabre REST JSON shape for branded-fare FREEDOM preservation on IATI-like CPNR v2.4 is:

```json
"Brand": [{ "content": "FL" }]
```

`omit_brand` must not be used when FREEDOM / FL tier preservation is required.

---

## Production default after BF7-F

| Aspect | Value |
|--------|-------|
| Wire style | `iati_like_cpnr_v2_4_gds` |
| Selector (gate off) | `object_content` via `SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR` |
| Brand node | `Brand: [{ "content": brandCode }]` |
| Traditional v2.5 | Brand still stripped (B59) — unchanged |
| Compare gate OFF | No `SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED` required |

Legacy `Brand: [{ "Code": "FL" }]` remains available only when compare gate is explicitly ON with variant `current_object_code` (BF7-D diagnostics).

---

## Inspect diagnostics

`sabre:inspect-booking-payload --airprice-brand-diagnostics --style=iati_like_cpnr_v2_4_gds` reports:

- `default_brand_node_shape` = `array_of_content_objects`
- `default_brand_shape_selector` = `object_content`
- `resolved_brand_code_for_wire` = `FL` (when FREEDOM selected)
- `active_brand_shape_selector` = `object_content` (gate off)
- `compare_gate_enabled` = `false`

---

## Safety flags (must remain OFF)

- `SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED=false`
- `SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED=false`
- `SABRE_TICKETING_ENABLED=false`
- `SABRE_BRANDED_FARES_PROBE_ENABLED=false`
- `SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED=false`

---

## Files changed (BF7-F)

| File | Change |
|------|--------|
| `app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php` | Default selector + wire shape |
| `config/suppliers.php` | Comment-only |
| `tests/Unit/SabreBrandedFareCpnrAirPriceAuditTest.php` | Contract tests |
| `tests/Unit/SabreIatiLikeCpnrV24GdsWireTest.php` | `Brand.0.content` |
| `scripts/bf7g-controlled-cert-default-brand-pnr.php` | BF7-G controlled CERT runner |
| `tests/Unit/Bf7gControlledCertDefaultBrandPnrTest.php` | CLI unit tests |

---

## BF7-F closure checklist

- [x] Gate-off default emits `Brand: [{ "content": "FL" }]`
- [x] `current_object_code` not default when gate off
- [x] Compare variants still work when gate ON
- [x] Selected fare context merge unchanged (BF7-B)
- [x] Traditional v2.5 Brand strip unchanged
- [x] No public PNR / ticketing / payment flag changes
- [x] Local unit tests pass

---

## BF7-G controlled final PNR validation

**Preflight (no HTTP):**

```bash
php scripts/bf7g-controlled-cert-default-brand-pnr.php --booking=51 --skip-send --allow-production-cert-controlled-send
```

**One controlled CERT create (production default, compare gate OFF):**

```bash
php scripts/bf7g-controlled-cert-default-brand-pnr.php --booking=51 --allow-production-cert-controlled-send
```

**Retrieve new PNR:**

```bash
php scripts/bf7e-retrieve-cert-pnr-summary.php --pnr={PNR} --booking=51 --allow-production-cert-controlled-retrieve
```

**Pass:** PNR created; retrieve shows FREEDOM/FL/VOWFL/V/30 KG; ticketing/payment absent; safety flags OFF.

**Do not retry Booking 43 or 46.**

---

## Rollback

Revert `SabreBookingPayloadBuilder.php` gate-off default to `current_object_code`; `php artisan cache:clear`.
