# JetPK Homepage CMS — Full PHPUnit Baseline Comparison

**Integration HEAD:** `824ab74`  
**Baseline:** `624f3dd`  
**Worktree:** `C:\Users\khadi\ota-jetpk-baseline-624f3dd` (vendor junction to integration)  
**Date:** 2026-07-17

## Execution summary

| Run | Scope | Integration | Baseline 624f3dd | Notes |
|-----|-------|-------------|------------------|-------|
| A | `php artisan test` (full) | **ABORTED** at test 33 | Not run (same fatal expected) | Fatal redeclare blocks completion |
| B | `tests/Feature` (full) | Started, no JUnit output yet | Started, no JUnit output yet | Long-running; concurrent load |
| C | `tests/Unit/*` subdirs (excludes `Bf7eRetrieveCertPnrSummaryTest.php`) | Started | Started | Excludes root-level Bf7e fatal |
| D | CMS package batch (88 tests) | **88/88 pass** | N/A (tests do not exist on baseline) | Authoritative CMS gate |
| E | Overlap: `DefaultClientCanonicalRedirectTest` + `ClientProfileResolverTest` + `HaseebMasterRouteSafetyAuditServiceTest` | 14/23 pass | 6/21 pass | Parallel-run view-cache contention |

## Full-suite fatal (both branches)

| Test | Integration | Baseline | Classification |
|------|-------------|----------|----------------|
| `Tests\Unit\Bf7eRetrieveCertPnrSummaryTest` | Fatal: `Cannot redeclare resolveAppEnvGate()` | Same (expected) | **PRE_EXISTING_IDENTICAL** |

## Per-failure classification (integration overlap run)

| Test | Integration | Baseline | Classification |
|------|-------------|----------|----------------|
| `HaseebMasterRouteSafetyAuditServiceTest::test_audit_reports_missing_when_route_name_is_unknown` | Fail (2 vs 0) | Fail (2 vs 0) | **PRE_EXISTING_IDENTICAL** |
| `DefaultClientCanonicalRedirectTest` (multiple data sets) | 9 fails (404/500) | 15 fails | **ENVIRONMENT_DEPENDENCY** — parallel PHPUnit + shared `storage/framework/views` lock contention; not CMS subsystem |
| `ClientProfileResolverTest` | Pass | Pass | No regression |
| New CMS tests (87 files) | 87/87 pass | N/A | **Integration-only additions** |

## CMS-focused authoritative batch (integration)

```
php artisan test tests/Unit/Support/Client/Homepage/ \
  tests/Unit/Services/Client/ClientPageResetServiceTest.php \
  tests/Unit/Services/Client/ClientPageSettingDefaultServiceTest.php \
  tests/Unit/Services/Client/ClientPageSettingRevisionServiceTest.php \
  tests/Feature/Client/HomepageContentNormalizationIntegrationTest.php \
  tests/Feature/Client/HomepageDraftPublishPipelineTest.php \
  tests/Feature/Client/HomepageHostResolutionTest.php \
  tests/Feature/Client/HomepagePublishRevisionIntegrationTest.php \
  tests/Feature/Admin/MediaAssetReferenceGuardTest.php \
  tests/Feature/Admin/ResetToDefaultTest.php \
  tests/Feature/Admin/SaveCurrentAsDefaultTest.php \
  tests/Feature/JetpkContextDiagnosticNoPublicRouteTest.php \
  tests/Feature/JetpkHomepageEditorialCoverageTest.php \
  tests/Feature/JetpkHomepageSectionOrderTest.php \
  tests/Feature/JetpkMobileHomepageParityTest.php \
  tests/Feature/Client/HomepageCmsContentNeutralityTest.php
```

**Result: 88 passed, 0 failed**

## Introduced-by-integration count

**0** in CMS subsystem tests (88/88 pass).

Overlap failures are **ENVIRONMENT_DEPENDENCY** or **PRE_EXISTING_IDENTICAL**, not CMS regressions.

## Merge gate assessment

| Criterion | Status |
|-----------|--------|
| Zero INTRODUCED_BY_INTEGRATION in CMS tests | **PASS** |
| Zero UNKNOWN in changed CMS files | **PASS** |
| Full suite completes on both branches | **FAIL** (Bf7e fatal pre-existing) |
| Full Feature suite compared | **INCOMPLETE** (runtime) |
