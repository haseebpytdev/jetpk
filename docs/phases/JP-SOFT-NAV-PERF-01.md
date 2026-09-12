# JP-SOFT-NAV-PERF-01

## Status
**BACKLOG / SEPARATE PERFORMANCE WORKSTREAM**

This item is **not** a blocker for Authority-06 closure (`AUTHORITY_06_STATUS=CLOSED_WITH_ACCEPTED_PERFORMANCE_RESIDUAL`).

## Objective
Reduce public marketing-route soft navigation usable latency so `TRUE_SOFT_NAV_WORST_USABLE_P95` meets the original binding target of **≤1500ms**.

## Baseline (Authority-06 production freeze)

| Field | Value |
|-------|-------|
| PRODUCTION_SHA | `8c50fc61967e51c5b576375b55ae7701d122c121` |
| WORST_USABLE_P95 | ≈2367ms (`home_to_support`) |
| TARGET | ≤1500ms (`SOFT_NAV_USABLE_P95_TARGET`) |

### Route breakdown at baseline

| Route | USABLE_P95 |
|-------|----------:|
| HOME_TO_LOGIN | ≈2294ms |
| HOME_TO_GROUPS | ≈2106ms |
| HOME_TO_ABOUT | ≈1412ms |
| HOME_TO_CONTACT | ≈1868ms |
| HOME_TO_SUPPORT | ≈2367ms |

## Prior investigation (carry forward — do not discard)

| Round | Finding |
|-------|---------|
| R3 | `HARNESS_VALID=YES`; `RSC_END_TO_ROUTE_COMMIT_P95≈1045ms`; `ROUTE_COMMIT_TO_USABLE_P95≈246ms`; dominant bucket **CHUNK_PARSE_EVAL + RSC/router transition** |
| R4 | `PREFETCH_CONTENTION=NOT_PROVEN`; `WHOLESALE_PREFETCH_REMOVAL=REJECTED` |

Evidence: `docs/evidence/jp-homepage-cms-authority-06/r3-diagnostic-summary.md`, `r4-prefetch-contention-summary.md`, `final-closure-accepted-residual.md`.

## In-scope remediation candidates

- Route chunk topology (split heavy route segments from shared shell)
- Chunk parse/eval cost (bundle analysis, dynamic import boundaries)
- Shared layout import boundaries (prevent marketing/auth chunk pollution)
- RSC/router transition cost (post-RSC commit stall)
- Bundle splitting where measurement evidence supports it

## Out of scope

- Reopening Authority-06 functional/CMS/booking UAT gates
- Supplier or payment mutations for perf proof
- Relabeling `SOFT_NAV_PERFORMANCE=PASS` without measured ≤1500ms worst usable P95

## Acceptance criterion

```
TRUE_SOFT_NAV_WORST_USABLE_P95 <= 1500ms
```

Measured on production (or production-parity build) with the same validated harness contract used in Authority-06 R3 (`HARNESS_ROUTER_WAIT_VALID=YES`, destination-specific interactive markers).

## Entry criteria

- Authority-06 closed with accepted residual
- Baseline SHA and route matrix recorded (this document)
- R3/R4 negative results preserved to avoid repeated dead hypotheses

## Evidence location

`docs/evidence/jp-soft-nav-perf-01/` (to be created when workstream starts)

## Parent closure

`docs/phases/JP-HOMEPAGE-CMS-AUTHORITY-06-SUMMARY.md`
