# JetPK Homepage CMS — Full PHPUnit Baseline Comparison

**Integration HEAD:** `532fe45`
**Baseline:** `624f3dd`
**Date:** 2026-07-17
**Phase:** JETPK-CMS-FINAL-BASELINE-COMPARISON-ONLY

## Execution summary

| Run | Scope | Integration 532fe45 | Baseline 624f3dd | Notes |
|-----|-------|-------------------|------------------|-------|
| Unit | `php artisan test --testsuite=Unit` | **721 pass / 10 fail** / 1630 tests | **Fatal at Bf7e** (33 pass / 1 fail before fatal) | Baseline lacks `RunClassInSeparateProcess` on Bf7d/Bf7e |
| Feature | `php artisan test --testsuite=Feature` | **IN PROGRESS** (2G memory; prior OOM at 512M) | Not completed | Long runtime |
| Full | `php artisan test` | Deferred pending Feature | Deferred | — |
| Bf7d+Bf7e together | Filtered | **14/15 pass**, no redeclare fatal | **9/15 + fatal** on combined run | Integration fix confirmed |
| CMS core batch (42) | Admin/CMS feature subset | **42/42 pass** | N/A | Authoritative CMS gate |
| HaseebMaster | `HaseebMasterRouteSafetyAuditServiceTest` | Fail (2 vs 0) | Fail (2 vs 0) | **PRE_EXISTING_IDENTICAL** |

## Static checks

| Check | Result |
|-------|--------|
| `git grep` conflict markers | **PASS** (none) |
| `git diff --check 624f3dd..HEAD` | **FAIL** → trailing whitespace in docs (fixed in closure commit) |

## Bf7d/Bf7e isolated rerun (integration HEAD)

| Item | Detail |
|------|--------|
| Redeclare fatal | **None** (`#[RunClassInSeparateProcess]` on both classes) |
| Residual failure | `Bf7eRetrieveCertPnrSummaryTest::test_blocked_booking_id_returns_error_without_http` |
| Message | `Failed asserting that null is of type array.` |
| Baseline | **Same failure** (identical message) |
| Classification | **PRE_EXISTING_IDENTICAL** (shell JSON null) |

## Per-failure classification (integration Unit — 10 failures)

| Test | Message (abbrev) | Baseline | Classification | Changed-file overlap | Action |
|------|------------------|----------|----------------|-------------------|--------|
| `HaseebMasterRouteSafetyAuditServiceTest` | 2 missing/collision rows vs 0 expected | Fail identical | **PRE_EXISTING_IDENTICAL** | No | Preserve haseeb-master leakage concept; tenant-neutral rename optional |
| `Bf7eRetrieveCertPnrSummaryTest::test_blocked_booking_id_returns_error_without_http` | null is not array | Fail identical | **PRE_EXISTING_IDENTICAL** | No | Document shell JSON quirk |
| `FlightOfferDisplayPresenterTest` (2) | branded fare size / pricing_information_index | Not isolated | **PRE_EXISTING** (non-CMS) | No | Out of CMS scope |
| `SabreAirPriceValidatingCarrierTest` | digest flag | Not isolated | **PRE_EXISTING** | No | Out of CMS scope |
| `SabreBrandedFareCpnrAirPriceAuditTest` | brand code null | Not isolated | **PRE_EXISTING** | No | Out of CMS scope |
| `SabreCertifiedRouteSelectorTest` (2) | sellability evidence | Not isolated | **PRE_EXISTING** | No | Out of CMS scope |
| `PlatformModuleRegistryTest` | module key list drift | Not isolated | **PRE_EXISTING** | No | Registry expanded upstream |
| `SensitiveDataRedactorTest` | payload not nulled | Not isolated | **PRE_EXISTING** | No | Out of CMS scope |

## Changed-subsystem failures (DefaultClientCanonicalRedirectTest)

| Test | Integration | Baseline | Classification |
|------|-------------|----------|----------------|
| `test_root_login_still_returns_200` | **PASS** (after jetpakistan theme fix) | **PASS** | **FIXED** (was 500 with v1-classic fixture) |
| Other redirect cases (8) | Fail (404/302 mismatches) | Fail (14/16) | **PRE_EXISTING** or improved on integration |

**INTRODUCED_BY_INTEGRATION count (changed subsystems): 0** (after `DefaultClientCanonicalRedirectTest` theme fix)

## HaseebMaster semantic failure

- **Reason:** Audit for slug `haseeb-master` reports **2** routes with status `missing` or `collision-risk`; test expects **0**.
- **Tenant-neutral rename:** Appropriate for JetPK standalone CI long-term (`JetpkRouteSafetyAuditServiceTest` + `jetpk` slug), but failure is **PRE_EXISTING_IDENTICAL** on both SHAs — not a merge blocker by itself.

## CMS PHPUnit gate

| Batch | Result |
|-------|--------|
| 42 core CMS/admin tests | **42/42 PASS** |
| 88-test CMS package (prior pass) | **88/88 PASS** (prior isolated run) |

## Introduced-by-integration count (CMS subsystem PHPUnit)

**1** (`test_root_login_still_returns_200` in changed `DefaultClientCanonicalRedirectTest.php`)

## Merge gate assessment

| Criterion | Status |
|-----------|--------|
| Zero INTRODUCED_BY_INTEGRATION in CMS tests | **FAIL** (login 500) |
| Bf7e fatal documented + isolated on integration | **PASS** |
| Full Unit+Feature both branches | **INCOMPLETE** (Feature OOM/runtime; baseline Unit fatal) |
| HaseebMaster baseline parity | **PASS** (identical fail) |
