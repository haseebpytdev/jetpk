# OTA F9M Sabre Host NO FARES/RBD/CARRIER Sellability Diagnostics

Phase: **OTA-DEVCP-F9M-SABRE-HOST-NO-FARES-RBD-CARRIER-SELLABILITY-DIAGNOSTICS**

Generated: 2026-06-18

## Problem

Booking 53 (`PAR-4JWVIB37`) passes F9H/F9I/F9K/F9L payload and schema gates (`post_f9i_payload_digest_clean=true`, `hard_no_fares_rbd_carrier_risk=false`) but Sabre host still returns:

- `ERR.SP.PROVIDER_ERROR — Unable to perform air booking step`
- `WARN.SWS.HOST.ERROR_IN_RESPONSE — EnhancedAirBookRQ: *NO FARES/RBD/CARRIER`

F9G/F9H expose application errors and wire shape. F9M adds **sellability classification**: context freshness, segment/brand/fare matrices across stored sources vs rebuilt CPNR wire, and recommended fix lanes — without live PNR create, retry bypass, or DB mutation.

## Solution

1. **`SabreControlledPnrSellabilityDiagnostics`** — composes F9G application digest, F9H payload digest, safe refresh, pricing linkage, and freshness into:
   - Context freshness (`revalidation_linkage_strength`, `minutes_since_*`, legacy signal)
   - Segment sellability matrix (selected / validated / safe-refresh / payload)
   - Fare/brand matrix (cross-source brand and fare-basis consistency)
   - Risk flags (`host_sellability_risk`, `stale_context_risk`, etc.)
   - Recommended lane A–G

2. **CLI `sabre:inspect-controlled-pnr-sellability`** — read-only; production requires `--confirm=READONLY-CONTROLLED-PNR-SELLABILITY`.

3. **Optional `--probe-fresh-revalidate`** — live fresh shop dry-run via `SabreBookingOfferRefreshService::refresh($booking, false)` (no DB write); production requires `--confirm=READONLY-CONTROLLED-PNR-SELLABILITY-FRESH-PROBE`. Outputs safe match summary only (flight/RBD/fare-basis; brand probe not supported).

## Recommended lanes

| Lane | Code | When |
|------|------|------|
| A | `refresh_required_before_retry` | Stale context or fresh probe `match_found=false` |
| B | `selected_offer_not_strongly_revalidated` | Legacy/weak revalidation linkage |
| C | `rbd_or_fare_basis_not_sellable` | Fare-basis or segment RBD mismatch |
| D | `brand_qualifier_requires_adjustment` | Brand inconsistency across contexts |
| E | `pricing_qualifier_missing_or_unsupported` | Missing pricing/offer/fare-component refs |
| F | `host_inventory_or_pcc_entitlement_issue` | Clean payload + unresolved host NO FARES/RBD/CARRIER |
| G | `no_safe_retry_recommended` | F9F/F9J/F9L retries consumed + host still unresolved |

## Not changed

- No failure bypass or additional controlled retry allowance
- No ticketing, cancellation, public auto-PNR, or checkout auto-PNR enablement
- No raw Sabre payloads, credentials, or passenger PII in output
- No live PNR create from this command

## Verification (local)

```bash
php artisan test --filter=ControlledPnrSellability
php artisan sabre:inspect-controlled-pnr-sellability --help
```

## Live booking 53 triage (ops — after deploy)

1. Sellability (read-only):

```bash
php artisan sabre:inspect-controlled-pnr-sellability \
  --reference=PAR-4JWVIB37 \
  --confirm=READONLY-CONTROLLED-PNR-SELLABILITY
```

2. Pair with F9H + F9G:

```bash
php artisan sabre:inspect-controlled-pnr-payload-digest \
  --reference=PAR-4JWVIB37 \
  --confirm=READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST

php artisan sabre:inspect-controlled-pnr-application-error \
  --reference=PAR-4JWVIB37 \
  --confirm=READONLY-CONTROLLED-PNR-APPLICATION-ERROR
```

3. Optional fresh shop probe (live HTTP, no DB mutation):

```bash
php artisan sabre:inspect-controlled-pnr-sellability \
  --reference=PAR-4JWVIB37 \
  --probe-fresh-revalidate \
  --confirm=READONLY-CONTROLLED-PNR-SELLABILITY-FRESH-PROBE
```

4. **Do not retry live create** when lane is `no_safe_retry_recommended` or host sellability unresolved without staff re-shop decision.

## Remaining gaps

- Host `NO FARES/RBD/CARRIER` may persist when wire + context are clean (true Sabre inventory/PCC sellability)
- Fresh probe does not validate brand qualifier on wire
- Full-itinerary BFM revalidate probe remains local/testing only (`sabre:inspect-booking-revalidate`)
