# OTA F9C Controlled Manual Review Approval — PNR Burn-in Gate

Generated: 2026-06-18  
Phase: **OTA-DEVCP-F9C-CONTROLLED-MANUAL-REVIEW-APPROVAL-PNR-BURN-IN-GATE**  
Classification: **Operator approval meta only** (no supplier HTTP, no PNR create, no ticketing, no cancellation)

## Objective

Replace automatic bypass of `manual_review_required` with an explicit operator approval marker on booking meta. Controlled PNR create proceeds only after `sabre:approve-controlled-pnr` with exact confirm.

## Commands

### Approve (dry-run — no DB mutation)

```bash
php artisan sabre:approve-controlled-pnr --booking=53 --dry-run \
  --reason="GF LHE-BAH-JED burn-in" --approved-by="platform_ops"
```

### Approve (writes meta only)

```bash
php artisan sabre:approve-controlled-pnr --booking=53 \
  --confirm=APPROVE-CONTROLLED-PNR-FOR-BOOKING-53 \
  --reason="GF LHE-BAH-JED burn-in" --approved-by="platform_ops"
```

### Readiness after approval

```bash
php artisan sabre:controlled-pnr-readiness --booking=53 \
  --confirm=READONLY-CONTROLLED-PNR-READINESS
```

### Controlled create dry-run (after approval)

```bash
php artisan sabre:controlled-create-pnr --booking=53 --dry-run
```

### Controlled create live (do not run until ops approves burn-in)

```bash
# php artisan sabre:controlled-create-pnr --booking=53 --confirm=CREATE-PNR-FOR-BOOKING-53
```

## Meta shape

`meta.controlled_pnr_manual_review` with `approved`, `approved_at`, `approved_by`, `approval_source`, `approval_reason`, `approval_booking_reference`, `approved_for=controlled_pnr_create`.

## Rollback

Remove or set `approved=false` on `meta.controlled_pnr_manual_review` only with ops approval; `php artisan optimize:clear` if needed.
