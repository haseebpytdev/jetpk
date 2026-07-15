# SABRE-BRANDED-FARES-BF7-E — CERT PNR Retrieve Closure

**Date:** 2026-06-15  
**Scope:** Retrieve-only CERT inspection for BF7-D PNRs. No ticketing, no payment mutation, no env-flag changes.

---

## BF7-D context

| Variant | Brand wire | PNR |
|---------|------------|-----|
| `object_content` | `Brand: [{ "content": "FL" }]` | **RQFUYD** |
| `omit_brand` | Brand omitted | **QJUAKV** |

**Booking:** 51 / PAR-K8W5SVKP  
**Expected branded context:** FREEDOM / FL / VOWFL/V / V / 30 KG

---

## Files added

| File | Purpose |
|------|---------|
| `scripts/bf7e-retrieve-cert-pnr-summary.php` | One-shot CERT retrieve + safe JSON summary |
| `tests/Unit/Bf7eRetrieveCertPnrSummaryTest.php` | CLI parse, gates, extractor unit tests |

---

## Upload (single-file SFTP)

Upload to OTA App remote root (`ota_app`):

1. `scripts/bf7e-retrieve-cert-pnr-summary.php`
2. `tests/Unit/Bf7eRetrieveCertPnrSummaryTest.php` (optional; tests only)

No Artisan cache clears required (script-only; no Blade/routes/config).

---

## Server SSH commands

```bash
cd /home/u654883295/domains/haseebasif.com/ota_app

php -l scripts/bf7e-retrieve-cert-pnr-summary.php

php scripts/bf7e-retrieve-cert-pnr-summary.php --pnr=RQFUYD --booking=51 --allow-production-cert-controlled-retrieve

php scripts/bf7e-retrieve-cert-pnr-summary.php --pnr=QJUAKV --booking=51 --allow-production-cert-controlled-retrieve

tail -n 80 storage/logs/laravel.log
```

Optional preflight (no HTTP):

```bash
php scripts/bf7e-retrieve-cert-pnr-summary.php --pnr=RQFUYD --booking=51 --skip-send
```

**Safety flags must remain OFF** (script asserts before send):

- `SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED=false`
- `SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED=false`
- `SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED=false`
- `SABRE_TICKETING_ENABLED=false`
- `SABRE_BRANDED_FARES_PROBE_ENABLED=false`

---

## Local verification (completed)

```bash
php -l scripts/bf7e-retrieve-cert-pnr-summary.php
php artisan test --filter=Bf7eRetrieveCertPnrSummary
```

All 7 unit tests passed.

---

## Live retrieve status

**Blocked from agent environment:** SSH to production host requires interactive password (`Permission denied (publickey,password)`). Local DB has no booking 51.

**Closure:** Run the two server commands above after upload; paste JSON output into the comparison table below.

---

## Comparison template (fill after server run)

### RQFUYD (`object_content`)

| Field | Value |
|-------|-------|
| `pnr_exists` | |
| `retrieve_success` | |
| `best_endpoint` | |
| `segment_count` | |
| `itinerary_segments` | |
| `fare_context.fare_basis_codes` | |
| `fare_context.brand_or_family` | |
| `fare_context.booking_classes` | |
| `fare_context.baggage_hints` | |
| `expected_match` | |
| `ticketing_present` | |
| `payment_present` | |
| `pnr_active_in_cert` | |

### QJUAKV (`omit_brand`)

| Field | Value |
|-------|-------|
| `pnr_exists` | |
| `retrieve_success` | |
| `best_endpoint` | |
| `segment_count` | |
| `itinerary_segments` | |
| `fare_context.fare_basis_codes` | |
| `fare_context.brand_or_family` | |
| `fare_context.booking_classes` | |
| `fare_context.baggage_hints` | |
| `expected_match` | |
| `ticketing_present` | |
| `payment_present` | |
| `pnr_active_in_cert` | |

### BF7-F recommendation (decision matrix)

| Outcome | Recommendation |
|---------|----------------|
| RQFUYD matches expected branded fields better than QJUAKV | Adopt permanent wire `Brand: [{ "content": "FL" }]` (`object_content`) |
| Both identical on branded fields | Brand node may not be required; preservation may rely on fare basis/RBD |
| Both lack branded context on retrieve | Do not enable public checkout; alternate verification needed |
| Either PNR not retrievable | Classify `error` field; keep BF7-E open |

---

## Rollback

Delete `scripts/bf7e-retrieve-cert-pnr-summary.php` and `tests/Unit/Bf7eRetrieveCertPnrSummaryTest.php` if abandoning BF7-E.
