# OTA F9O Sabre Strong BFM Revalidation Linkage Before Controlled PNR

Phase: **OTA-DEVCP-F9O-SABRE-STRONG-BFM-REVALIDATION-LINKAGE-BEFORE-CONTROLLED-PNR**

Generated: 2026-06-18

## Problem

Booking 53 (`PAR-4JWVIB37`) after F9N fresh context apply still reports:

- `revalidation_linkage_strength=legacy`
- `weak_revalidation_risk=true`
- `recommended_lane=selected_offer_not_strongly_revalidated`

F9N removed stale context risk and refreshed offer snapshots (itinerary/leg/fare-component refs updated), but legacy revalidation success alone is insufficient for a safe controlled PNR retry. Strong BFM pricing/offer linkage must be inspected and, when safe, persisted before any further controlled retry.

## Solution

1. **`SabreControlledPnrStrongRevalidationLinkageDiagnostics`** — read-only strong-linkage matrix (itinerary/pricing/segment refs, BFM policy, risk lanes). Optional shop refresh probe (`probe_type=shop_refresh_not_true_revalidate`; not true Sabre revalidate).

2. **CLI `sabre:inspect-controlled-pnr-strong-revalidation-linkage`** — read-only; production confirm `READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-LINKAGE`; optional `--probe-revalidate` + `READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-PROBE`.

3. **`SabreControlledStrongRevalidationLinkageApply`** + CLI **`sabre:controlled-apply-strong-revalidation-linkage`** — dry-run or live apply via `SabreStoredPricingContextDigest::rebuildSnapshotPricingLinkage()`; meta marker `controlled_strong_revalidation_linkage_apply`; production confirm `APPLY-STRONG-REVALIDATION-LINKAGE-FOR-BOOKING-{id}`.

4. **F9M sellability update** — recognizes `controlled_strong_revalidation_linkage_apply.applied` as strong linkage (alongside formal ref complete).

## Apply eligibility (summary)

- Sabre booking, no PNR/supplier_reference, not ticketed/cancelled
- F9N `controlled_fresh_pnr_context_apply.applied=true`
- `stale_context_risk=false`, `weak_revalidation_risk=true`, sellability lane `selected_offer_not_strongly_revalidated`
- Strong linkage candidate (BFM or formal readiness + segment/RBD/fare/brand/VC match)
- No unresolved price change without F9E acceptance

## Not changed

- No PNR create, ticketing, cancellation, or retry bypass
- No public/checkout auto-PNR enablement
- No raw supplier payload/response/PII in CLI output
- `controlled_pnr_retry_after_fresh_context_apply_requires_new_approval` remains true after apply

## Verification (local)

```bash
php artisan test --filter=StrongRevalidationLinkage
php artisan sabre:inspect-controlled-pnr-strong-revalidation-linkage --help
php artisan sabre:controlled-apply-strong-revalidation-linkage --help
```

## Live booking 53 ops (after deploy)

1. Strong-linkage diagnostic (read-only):

```bash
php artisan sabre:inspect-controlled-pnr-strong-revalidation-linkage \
  --reference=PAR-4JWVIB37 \
  --confirm=READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-LINKAGE
```

2. Optional shop probe (not true revalidate):

```bash
php artisan sabre:inspect-controlled-pnr-strong-revalidation-linkage \
  --reference=PAR-4JWVIB37 \
  --probe-revalidate \
  --confirm=READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-PROBE
```

3. Apply dry-run:

```bash
php artisan sabre:controlled-apply-strong-revalidation-linkage \
  --reference=PAR-4JWVIB37 \
  --dry-run
```

4. Live apply (once, only if dry-run `would_apply=true`):

```bash
php artisan sabre:controlled-apply-strong-revalidation-linkage \
  --reference=PAR-4JWVIB37 \
  --confirm=APPLY-STRONG-REVALIDATION-LINKAGE-FOR-BOOKING-53
```

5. Post-apply sellability (expect `weak_revalidation_risk=false`, linkage strength strong):

```bash
php artisan sabre:inspect-controlled-pnr-sellability \
  --reference=PAR-4JWVIB37 \
  --confirm=READONLY-CONTROLLED-PNR-SELLABILITY
```
