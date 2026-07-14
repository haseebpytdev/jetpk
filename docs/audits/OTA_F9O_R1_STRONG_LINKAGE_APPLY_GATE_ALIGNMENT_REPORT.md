# OTA F9O-R1 Strong Linkage Apply Gate Alignment

Phase: **OTA-DEVCP-F9O-R1-STRONG-LINKAGE-APPLY-GATE-ALIGNMENT**

Generated: 2026-06-18

## Problem

Booking 53 (`PAR-4JWVIB37`) after F9N fresh context apply:

- F9O diagnostic: `strong_revalidation_candidate=true`, `recommended_lane=strong_revalidation_apply_required`
- F9O apply gate blocked: `stale_context_risk`, `sellability_lane_not_weak_revalidation`
- F9M sellability lane: `refresh_required_before_retry` (10-minute offer stale threshold)

The apply command incorrectly used F9M sellability as a hard gate instead of F9O strong-linkage diagnostic.

## Root cause

`SabreControlledStrongRevalidationLinkageApply::evaluateEligibility` required:

1. F9M `stale_context_risk=false` (600s threshold — fails at ~26 min)
2. F9M `weak_revalidation_risk=true`
3. F9M lane `selected_offer_not_strongly_revalidated`

When F9M stale lane fired first (`refresh_required_before_retry`), apply blocked even though F9O matrix was complete and recommended `strong_revalidation_apply_required`.

## Fix

1. **F9O diagnostic as source of truth** — apply requires F9O `recommended_lane=strong_revalidation_apply_required`, full strong-linkage matrix, and `strong_revalidation_candidate=true` with empty blockers. F9M sellability lane is informational only (`sellability_lane_used_as_hard_gate=false`).

2. **Controlled stale window** — `config('ota.controlled_strong_linkage_apply.max_minutes_after_fresh_context_apply')` default 180 minutes. `stale_context_risk_hard_blocker=true` only when F9N apply absent, context timestamps missing/unparseable, or age exceeds window. F9M `stale_context_risk` remains a warning in output.

3. **Clearer apply output** — `f9o_diagnostic_recommended_lane`, `sellability_recommended_lane`, `stale_context_risk_hard_blocker`, `strong_linkage_blockers`, `formal_revalidation_linkage_complete_before_apply`, `would_apply`.

4. **No retry allowance** — post-apply writes only `controlled_strong_revalidation_linkage_apply`; `controlled_pnr_retry_after_fresh_context_apply_requires_new_approval` stays true.

## Not changed

- No PNR create, ticketing, cancellation, or retry bypass
- No public/checkout auto-PNR enablement
- No raw supplier payload/response/PII in CLI output

## Verification (local)

```bash
php artisan test --filter=StrongRevalidationLinkage
php -l app/Support/Bookings/SabreControlledStrongRevalidationLinkageApply.php
```

## Live booking 53 ops (after deploy)

1. Strong-linkage diagnostic:

```bash
php artisan sabre:inspect-controlled-pnr-strong-revalidation-linkage \
  --reference=PAR-4JWVIB37 \
  --confirm=READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-LINKAGE
```

2. Apply dry-run (expect `would_apply=true`, `stale_context_risk_hard_blocker=false`):

```bash
php artisan sabre:controlled-apply-strong-revalidation-linkage \
  --reference=PAR-4JWVIB37 \
  --dry-run
```

3. Live apply once (only if dry-run passes):

```bash
php artisan sabre:controlled-apply-strong-revalidation-linkage \
  --reference=PAR-4JWVIB37 \
  --confirm=APPLY-STRONG-REVALIDATION-LINKAGE-FOR-BOOKING-53
```
