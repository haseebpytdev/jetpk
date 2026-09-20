# PERFORMANCE CERTIFICATION — BLOCKED

## Live tip

| Field | Value |
|-------|-------|
| LOCAL/RUNTIME SHA | `4f1836c16afc8c521c2d6c6adff26863fb1f53e1` |
| PUBLIC_BUILD_ID | `9uCDdSnCJsWGlCp9_mFEN` |
| ROLLBACK | `8793cc9f…` |

## Gate matrix

| Gate | Status | Best evidence |
|------|--------|----------------|
| Soft-nav all routes APP P95 ≤1500 | **FAIL** (best 8/10 on 8793cc9f; tip 6/10) | `docs/evidence/jp-final-perf-cert-8793cc9f/01-soft-nav/` |
| Traveler APP P95 ≤2000 | **PASS** 1022 | `docs/evidence/jp-final-perf-cert-e5eead09/03-traveler/` |
| Return post-supplier P95 ≤1000 | **PASS** 831 | `docs/evidence/jp-final-perf-cert-e5eead09/04-return-pair/` |
| Pair↔segmented switch | **PASS** | same |
| Same-SHA full suite | **PENDING** | traveler/return not yet re-run on tip |

## PERFORMANCE_CERTIFICATION

**FAIL** — open gate: **soft-nav** (support_home/home_login near-miss on 8793cc9f; tip still FAIL).

Phases 7–19 blocked per prompt §37.

## Commercial

All mutation flags = NO.
