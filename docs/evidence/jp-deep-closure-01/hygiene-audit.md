# JP-DEEP-CLOSURE-01 — Hygiene audit (no SAFE_TO_DELETE)

## Metrics (local sandbox)

```
TRACKED_FILE_COUNT=7915
SUMMARY_CONTEXT_BYTES=567831
PLAYWRIGHT_CONFIGS=43
DEAD_CONFIRMED_COUNT=0 (deletable files)
DEAD_REMOVED_COUNT=0
LEGACY_RETIRED_CONFIRMED_COUNT=0 (already removed historically: Mock flight suppliers)
LEGACY_RETIRED_REMOVED_COUNT=0
DUPLICATE_CONFIRMED_COUNT=2 (Booking vs Bookings services; Playwright config sprawl)
DUPLICATES_CONSOLIDATED_COUNT=0
DEAD_NEEDS_PROOF_COUNT=7+ (unused-looking Playwright configs; 11k-s2-upload)
REFACTOR_CANDIDATES_REMAINING=BookingController, FlightController, summary.md, Booking vs Bookings taxonomy
```

## SAFE_TO_DELETE

None proven in this pass. Mock flight suppliers already retired. Playwright configs need exhaustive script/CI/doc grep before deletion.

## AGENTS.md

References eight `.cursor/rules/*.mdc` paths that are **absent** on disk in this clone. Doc restore/canonicalization deferred (COMMIT E docs only points here; no mass rewrite without owner review).

## Hotspots

| Hotspot | Lines (approx) | SAFE_EXTRACTION |
|---|---|---|
| BookingController | ~4412 | passengers / review / confirmation / fare accept services |
| FlightController | ~3152+ | progressive search / revalidate / return-options already partially service-backed |

No controller split in this phase (behavior risk).
