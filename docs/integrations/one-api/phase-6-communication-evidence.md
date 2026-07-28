# Phase 6 — Communication integration evidence

**Status:** **Incomplete** for hold / hold-payment; **partial** for paid.

## Architecture (unchanged)

- **Paid supplier booking:** `SupplierBookingService` → successful adapter → persist supplier state → `BookingCommunicationService::sendSupplierBookingCreated` (idempotent).
- **One API adapter:** `OneApiBookingService` / `OneApiSupplierBookingAdapter` — **no** direct mail/XML comms.

## Paid booking

| Criterion | Evidence | Result |
|-----------|----------|--------|
| Supplier state before communication | `SupplierBookingService` ordering (Phase 5 map) | **Documented** |
| PNR/TID/tickets persist first | `OneApiPaidBookingIntegrationTest` (PNR parse) | **Partial** |
| Paid/confirmed event once | `supplierBookingCreatedAlreadyLogged` in platform service | **Not PHPUnit-proven for One API** |
| Tickets only when present | Platform templates | **Not One API–specific test** |
| Duplicate submission / queue retry / reconciliation | No dedicated One API comm mocks | **Gap** |

## On-hold booking

| Criterion | Evidence | Result |
|-----------|----------|--------|
| Hold status/deadline persist | `OneApiHoldLifecycleIntegrationTest` | **Pass** (fixture) |
| Hold/pending comm if policy supports | `BookingCommunicationService` has **no** hold-specific sender | **N/A — not implemented** |
| Never paid/ticketed comm on hold | No hold comm wired | **Pass by absence** |
| Duplicate hold / ambiguous hold comm | No tests | **Gap** |

## Hold payment

| Criterion | Evidence | Result |
|-----------|----------|--------|
| Paid state after modify + re-read | `OneApiHoldLifecycleIntegrationTest` modify leg | **Partial** |
| Communication once | Not wired to `BookingCommunicationService` for hold-pay path | **Gap** |
| No duplicate hold message | Not tested | **Gap** |
| Ambiguous / failed read → no success comm | Not tested | **Gap** |

## Structural tests added (Phase 6)

- `OneApiCommunicationRoutingTest` — asserts `OneApiBookingService` does not reference `BookingCommunicationService`; `SupplierBookingService` does.

## Conclusion

**Communication integration is not acceptance-complete.** Paid path relies on existing orchestration without mocked idempotency proofs; hold and hold-payment communication remain **out of scope** of current `BookingCommunicationService` APIs.
