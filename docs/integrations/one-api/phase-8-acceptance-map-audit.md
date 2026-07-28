# Phase 8 — Acceptance map audit

## Correction (Phase 7 → Phase 8)

Phase 7 incorrectly marked several matrices as covered while only a subset of tests existed. Phase 8 restores:

- `OneApiAcceptanceRequiredIdRegistry` — immutable ID list with mandatory=true
- `OneApiAcceptanceRequirementMap` — per-ID status with `source_phase`, `approval_required`
- `OneApiAcceptanceRegistryIntegrityTest` — fails on removed/renamed/downgraded IDs
- `OneApiAcceptanceRequirementGateTest` — fails while any **mandatory** row has `status=missing`

## Status rules (enforced)

| Status | Gate impact |
|--------|-------------|
| covered | Requires test_class + test_method evidence |
| missing | **Fails gate** when mandatory |
| vendor-fixture-blocked | Does not fail (e.g. FLY-001) |
| genuinely not applicable | Does not fail (e.g. COMM-004 hold email policy) |

## Known mandatory gaps after Phase 8 pass (honest)

Run `OneApiAcceptanceRequirementMap::mandatoryMissing()` after tests — expect non-zero until AUTH-004+, ADM-002+, HOLD-002, READ-001, PAY-002, COMM-005/006/008, and COR-003/004/008/010/011/014 tests land.

## Downgrade prohibition

The registry integrity test prevents lowering `mandatory` or removing IDs without updating `OneApiAcceptanceRequiredIdRegistry` (explicit review).
