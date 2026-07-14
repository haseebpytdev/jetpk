# OTA F9P Controlled PNR Final Readiness After Strong Linkage

Phase: **OTA-DEVCP-F9P-CONTROLLED-PNR-RETRY-AFTER-FRESH-STRONG-LINKAGE-READINESS**

Generated: 2026-06-18

## Problem

After F9N (fresh context apply) and F9O-R1 (strong BFM linkage apply), Booking 53 has strong revalidation linkage but F9M still reports `stale_context_risk=true` because generic offer freshness (10-minute stale lane) expired. There was no dedicated final pre-PNR readiness gate, and F9N could not be safely re-run (`fresh_context_already_applied` blocker).

## Solution

1. **`SabreControlledPnrFinalReadinessDiagnostics`** — read-only compose of F9M sellability, F9O linkage inspect, payload digest, F9N/F9O markers, and retry-consumption flags.

2. **CLI `sabre:controlled-pnr-final-readiness`** — production requires `--confirm=READONLY-CONTROLLED-PNR-FINAL-READINESS`; no supplier HTTP, no DB mutation.

3. **F9P final freshness window** — `ota.controlled_final_pnr_freshness.max_minutes` (default **15**, env `OTA_CONTROLLED_FINAL_PNR_FRESHNESS_MAX_MINUTES`). Independent of F9M `stale_context_risk`. Blocker: `final_refresh_required`.

4. **F9N final fresh re-run** — existing `sabre:controlled-apply-fresh-pnr-context` allows re-run when F9N+F9O applied, final freshness expired, no PNR/ticket/cancel, strong linkage not `recheck_required`. Post-apply: `preserveOrInvalidateAfterFreshRerun()` on F9O marker.

## Final readiness outputs (summary)

- `strong_revalidation_linkage_ready`, `final_freshness_ready`, `final_pnr_retry_ready`
- `final_freshness_blockers`, `final_pnr_retry_blockers`
- `existing_retry_allowances_consumed`, `new_explicit_retry_approval_required` (informational; does not grant retry allowance)
- Safety: `live_supplier_call_attempted=false`, `pnr_create_attempted=false`

## Not changed

- No PNR create, ticketing, cancellation, or retry bypass on `sabre:controlled-create-pnr`
- No public/checkout auto-PNR enablement
- No raw supplier payload/response/PII in CLI output

## Verification (local)

```bash
php artisan test --filter=ControlledPnrFinalReadiness
php artisan test --filter=SabreControlledApplyFreshPnrContext
php artisan test --filter=StrongRevalidationLinkage
php artisan sabre:controlled-pnr-final-readiness --help
```

## Live booking 53 ops (after deploy)

1. Final readiness (read-only):

```bash
php artisan sabre:controlled-pnr-final-readiness \
  --reference=PAR-4JWVIB37 \
  --confirm=READONLY-CONTROLLED-PNR-FINAL-READINESS \
  --json
```

Expected when context is stale (~53 min): `final_freshness_ready=false`, `final_pnr_retry_ready=false`, blocker `final_refresh_required`.

2. F9N final fresh re-run dry-run:

```bash
php artisan sabre:controlled-apply-fresh-pnr-context \
  --reference=PAR-4JWVIB37 \
  --dry-run \
  --json
```

3. F9N live re-run (only if dry-run `would_apply=true`):

```bash
php artisan sabre:controlled-apply-fresh-pnr-context \
  --reference=PAR-4JWVIB37 \
  --confirm=APPLY-FRESH-CONTEXT-FOR-BOOKING-53 \
  --json
```

4. Re-check final readiness within 15 minutes of step 3.

5. If `strong_linkage_recheck_required=true`, re-run F9O apply before next PNR retry approval phase.
