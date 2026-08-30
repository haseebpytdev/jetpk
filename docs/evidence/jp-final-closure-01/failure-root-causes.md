# Failure / root causes — JP-FINAL-CLOSURE-01 (CURRENT = R6F)

Historical R1 checkpoint preserved as `failure-root-causes-r1-checkpoint.md`.

## Corrected R6 contract error

R6 incorrectly claimed `RUN_COMPLETED=YES` while `REVIEW_RESPONSIVE=NOT_REACHED`. R6F reaches Review and must not leave required gates as `NOT_REACHED`.

## Resolved in R6F

| Area | Status | Notes |
|---|---|---|
| Soft-nav primary Book Now | RESOLVED | `router.push`; hard assign fallback-only |
| Continuous timing across soft-nav | RESOLVED | sessionStorage wall-clock continuity |
| Segmented Return Book handoff | RESOLVED | affirmative `/booking/passengers` + draft id |
| Review responsive | RESOLVED | inert Laravel passengers save → Review screenshots |
| Accidental R5 evidence overwrite | RESTORED | exact-path `git restore --source=HEAD` |

## Current open engineering defect

### Book Now continuous T0→T9 outliers — OPEN (PERFORMANCE=FAIL)

**Soft-nav cert on `6a6c3b35` / `abYe4XmYEs6wOjNqRDNGX` (n=15/15):**

| Metric | Soft p50 | Soft p95 | Hard R6 p50 | Hard R6 p95 |
|---|---:|---:|---:|---:|
| SHELL | 3140 | 35384 | ~20450 | ~24571 |
| USABLE | 3864 | 36074 | 20965 | 25131 |
| OUTLIERS >15s | 4 | | 11 | |

**HARD_NAV_CAUSALITY=PROVEN** for the ~21s median regression.

**Remaining expanding interval (outliers):** primarily **T3→T6** (fare Accept / continue wait), not soft T7→T8.

Do not ship another blind navigation rewrite.

## External / non-blocking

- PIA NDC: SUPPLIER_AUTH_REJECT (Book harness prefers non-PIA)
- Sabre cancel posture unchanged
- OS/firewall/SSH maintenance still frozen
- Pre-existing dirty email files intentionally unstaged
