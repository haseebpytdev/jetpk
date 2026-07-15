# OTA F9Q Final Controlled PNR Retry Allowance After Green Readiness

Phase: **OTA-DEVCP-F9Q-FINAL-CONTROLLED-PNR-RETRY-ALLOWANCE-AFTER-GREEN-READINESS**

Generated: 2026-06-18

## Problem

After F9P final readiness is green and F9F/F9J/F9L one-shot retries are consumed, controlled PNR create remained blocked with `supplier_booking_retry_not_allowed`. F9P reported `new_explicit_retry_approval_required` but no command existed to write a safe one-shot allowance marker.

## Solution

1. **`SabreControlledFinalPnrRetryAllowanceGate`** — evaluates F9P readiness at execution time; writes/consumes meta `controlled_final_pnr_retry_allowance` only.

2. **CLI `sabre:allow-final-controlled-pnr-retry`** — dry-run read-only; live write requires production `--confirm=ALLOW-FINAL-CONTROLLED-PNR-RETRY-FOR-BOOKING-{id}`. No supplier HTTP.

3. **Preflight + `SabreBookingService`** — F9Q gate after F9L in retry bypass chain; `recordUsage()` immediately before supplier HTTP after local CPNR schema pass.

4. **`sabre:controlled-create-pnr --dry-run`** — outputs allowance presence/validity, F9P readiness fields, exact create confirm phrase.

5. **Config** — `ota.controlled_final_pnr_retry_allowance.max_minutes` (default **15**, env `OTA_CONTROLLED_FINAL_PNR_RETRY_ALLOWANCE_MAX_MINUTES`).

## Allowance meta shape (safe)

```php
controlled_final_pnr_retry_allowance = [
    'allowed' => true,
    'used' => false,
    'allowed_at' => ISO8601,
    'allowed_by' => 'controlled_command',
    'booking_reference' => '...',
    'reason' => 'final_readiness_green_after_fresh_strong_linkage',
    'final_readiness_checked_at' => ISO8601,
    'expires_at' => ISO8601,
    'requires_exact_create_confirm' => 'CREATE-PNR-FOR-BOOKING-{id}',
    'ticketing_enabled' => false,
    'cancellation_enabled' => false,
]
```

## Operator flow (Booking 53)

1. `sabre:controlled-pnr-final-readiness` — must be green and fresh (15 min window).
2. `sabre:allow-final-controlled-pnr-retry --dry-run` then live with exact confirm.
3. `sabre:controlled-create-pnr --dry-run` — verify allowance valid.
4. `sabre:controlled-create-pnr --confirm=CREATE-PNR-FOR-BOOKING-53` — **manual SSH only**; not from Cursor.

## Safety

- No PNR create, ticketing, public auto-PNR, checkout auto-PNR, or cancellation enablement.
- No broad retry bypass — only when F9F+F9J+F9L consumed + valid F9Q allowance + F9P green at execution.
- No raw supplier payloads/responses, credentials, or PII in command output.

## Tests

- `tests/Unit/Support/Bookings/SabreControlledFinalPnrRetryAllowanceGateTest.php`
- `tests/Feature/SabreAllowFinalControlledPnrRetryCommandTest.php`
- Updated `SabreControlledCreatePnrCommandTest`, `SabreControlledPnrFinalReadinessCommandTest`
