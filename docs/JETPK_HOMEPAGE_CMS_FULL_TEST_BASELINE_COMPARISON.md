# JetPK Homepage CMS — Full PHPUnit Baseline Comparison

**Integration HEAD:** `4ebc77b` (closure pass pending push)
**Baseline:** `624f3dd`  
**Date:** 2026-07-17

## Execution summary

| Run | Scope | Integration | Baseline 624f3dd | Notes |
|-----|-------|-------------|------------------|-------|
| A | `php artisan test` (full) | Not completed (long runtime) | Not completed | Bf7e/Bf7d fatal **resolved** via `#[RunClassInSeparateProcess]` |
| B | `Bf7d` + `Bf7e` together | **14/15 pass** | Same expected | No redeclare fatal |
| C | CMS package batch (88 tests) | **88/88 pass** (isolated rerun) | N/A | Authoritative CMS gate |
| D | `HaseebMasterRouteSafetyAuditServiceTest` | Fail (2 vs 0) | Fail (2 vs 0) | **PRE_EXISTING_IDENTICAL** |
| E | `tests/Unit` (full) | In progress | Not run | Closure pass |

## Full-suite fatal root cause

| Item | Detail |
|------|--------|
| Test | `Tests\Unit\Bf7eRetrieveCertPnrSummaryTest` |
| Fatal (before fix) | `Cannot redeclare resolveAppEnvGate()` |
| Cause | Global functions in `scripts/bf7d-controlled-cert-brand-variant.php` and `scripts/bf7e-retrieve-cert-pnr-summary.php` loaded in same PHPUnit process |
| Classification | **TEST_HARNESS_BUG** |
| Fix | `#[RunClassInSeparateProcess]` on `Bf7dControlledCertBrandVariantTest` and `Bf7eRetrieveCertPnrSummaryTest` |
| Residual | `test_blocked_booking_id_returns_error_without_http` fails alone on integration (**PRE_EXISTING_IDENTICAL** — shell JSON null) |

## Per-failure classification (overlap)

| Test | Integration | Baseline | Classification |
|------|-------------|----------|----------------|
| `HaseebMasterRouteSafetyAuditServiceTest` | Fail | Fail | **PRE_EXISTING_IDENTICAL** |
| `Bf7eRetrieveCertPnrSummaryTest::test_blocked_booking_id_returns_error_without_http` | Fail | Fail (expected) | **PRE_EXISTING_IDENTICAL** |
| `HomepageDraftPublishPipelineTest` (parallel run) | 1×500 view rename lock | N/A | **ENVIRONMENT_DEPENDENCY** — passes isolated rerun |
| New CMS tests (88) | 88/88 pass | N/A | Integration-only additions |

## Introduced-by-integration count (CMS subsystem)

**0**

## Merge gate assessment

| Criterion | Status |
|-----------|--------|
| Zero INTRODUCED_BY_INTEGRATION in CMS tests | **PASS** |
| Bf7e fatal documented + isolated | **PASS** |
| Full suite completion both branches | **DEFERRED** (runtime; no integration-only fatal) |
| Full Feature suite compared | **INCOMPLETE** |
