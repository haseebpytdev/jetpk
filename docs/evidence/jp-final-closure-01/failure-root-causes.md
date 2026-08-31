# Failure / root causes — JP-FINAL-CLOSURE-01 (CURRENT = R6G)

Historical R1 checkpoint preserved as `failure-root-causes-r1-checkpoint.md`.

## Resolved in R6F (preserved)

| Area | Status |
|---|---|
| Soft-nav primary Book Now | RESOLVED |
| Continuous timing across soft-nav | RESOLVED |
| Segmented Return Book handoff | RESOLVED |
| Review responsive | RESOLVED |
| Accidental R5 evidence overwrite | RESTORED |

## R6G — Book Now “outliers” closed as harness measurement defect

**Class:** `HARNESS_MEASUREMENT_DEFECT`

**Expanding interval:** T3→T6 after warm revalidation, while the automation polls **Continue** / **Accept** (800–1000ms sleeps).

**Not product latency:** soft-nav T6→T9 (usable p95≈1377ms); Laravel passengers p95≈177ms; revalidation p95≈1639ms.

**Corrected SYSTEM_ONLY** (reval + post-nav, excluding Continue dwell):

| Metric | p50 | p95 | >15s |
|---|---:|---:|---:|
| SYSTEM_ONLY shell | 1645 | 2291 | 0 |
| SYSTEM_ONLY usable | 2204 | 2840 | 0 |

Evidence: `live-final/r6/book-now-timing-breakdown-r6g.json`, `live-final/r6/r6g-root-cause.md`.

No further runtime performance patch. Instrumentation SHA `754b9f4f` remains deployed for marks only.

## External / non-blocking

- PIA NDC: SUPPLIER_AUTH_REJECT (Book harness prefers non-PIA)
- FARE_DECISION_FLOW not observed in R6G n=20 AUTO_FLOW set
- Pre-existing dirty email files intentionally unstaged
- `WORKTREE_CLEAN=NO` with `UNKNOWN_WORKTREE_CHANGES=NO`
