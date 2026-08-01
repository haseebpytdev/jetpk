# JP-OPS-01 Frontend–Backend Contract Matrix (JP-OPS-01A)

**Unique operational contracts:** 31 (denominator for contract classifications)

## Contract classification summary (sum 31)

| Classification | Count |
|----------------|------:|
| OPERATIONAL_CONNECTED | 19 |
| PARTIALLY_CONNECTED | 5 |
| FRONTEND_WITHOUT_BACKEND_CONTRACT | 6 |
| SUPPLIER_CAPABILITY_BLOCKED | 1 |

## Connected contracts (19) — CODE_CONNECTED_AND_LOCALLY_VERIFIED

Public B2C OC-001–OC-010, Customer OC-011–OC-015, Agent OC-018–OC-021.

## Partially connected (5) — BLADE_OPERATIONAL_NEXT_MIGRATION_PENDING

| ID | FE | Laravel | Notes |
|----|-----|---------|-------|
| OC-026 | overview-service.ts | GET /api/dashboard/overview | Read connected; mutations on Blade |
| OC-027 | booking-service.ts | GET /api/dashboard/bookings | Same |
| OC-028 | payment-service.ts | GET /api/dashboard/payments | Same |
| OC-029 | session-service.ts | GET /api/dashboard/session | Live mode uses Laravel |
| OC-030 | dashboard/features/* | admin/staff mutations | Blade operational |

## Frontend without backend contract (6)

| ID | Missing FE | Laravel backend |
|----|-----------|-----------------|
| OC-016 | Customer cancellation UI | POST customer.bookings.cancellations.store |
| OC-017 | Customer travelers UI | customer.travelers.* |
| OC-022 | Agent staff UI | agent.staff.* |
| OC-023 | Agent reports UI | GET /agent/reports |
| OC-024 | Agent commissions UI | GET /agent/commissions |
| OC-025 | Agent booking create UI | agent.bookings.create |

## Supplier blocked (1)

OC-031 — IATI authentication EXTERNAL_DEPENDENCY_BLOCKED.

## Contract C-01 correction (mockUser)

**Downgraded from P0.** Header `session ?? mockUser` is presentation fallback; authorization enforced server-side on all API routes. See GAP-002 (P3).

## Contract C-04 (dashboard mutations)

Dashboard visible actions must not imply mutation success without server endpoint — 157 Blade mutations have no Next API equivalent (GAP-001 P1).
