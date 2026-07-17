# JetPK Homepage CMS — Full Test Baseline Comparison

**Integration HEAD:** `1ff4658`  
**Baseline:** `624f3dd`  
**Date:** 2026-07-18  
**Phase:** JETPK-CMS-LAST-MERGE-GATE-CLOSURE

Evidence JSON: `storage/test-results/integration-feature-all-dirs.json`, `storage/test-results/baseline-feature-all-dirs.json`  
Classification: `storage/test-results/feature-failure-classification.json`

## Feature directory matrix (23 directories, sequential)

| Directory | Integration pass/fail/skip | Baseline pass/fail/skip | Classification |
|-----------|---------------------------:|------------------------:|----------------|
| Admin | 63/2/0 | 53/2/0 | PRE_EXISTING_IDENTICAL |
| Agent | 144/20/3 | 144/20/3 | PRE_EXISTING_IDENTICAL |
| Auth | 48/24/0 | 48/24/0 | PRE_EXISTING_IDENTICAL |
| Booking | 14/1/0 | 14/1/0 | PRE_EXISTING_IDENTICAL |
| Client | 44/45/6 | 19/52/6 | PRE_EXISTING_IDENTICAL (integration +25 pass / −7 fail; 0 asymmetric test names) |
| Communication | 23/5/0 | 23/5/0 | PRE_EXISTING_IDENTICAL |
| Console | 77/9/5 | 77/9/5 | PRE_EXISTING_IDENTICAL |
| Customer | 7/4/0 | 7/4/0 | PRE_EXISTING_IDENTICAL |
| Dashboard | 21/9/0 | 21/9/0 | PRE_EXISTING_IDENTICAL |
| Developer | 71/2/0 | 71/2/0 | PRE_EXISTING_IDENTICAL |
| Email | 17/0/0 | 17/0/0 | PASS |
| Finance | 232/7/0 | 232/7/0 | PRE_EXISTING_IDENTICAL |
| FlightSearch | 5/0/2 | 5/0/2 | PRE_EXISTING_IDENTICAL |
| GroupTicketing | 33/22/1 | 33/22/1 | PRE_EXISTING_IDENTICAL |
| Guest | 7/0/0 | 7/0/0 | PASS |
| Jetpk | 191/2/0 | 191/2/0 | PRE_EXISTING_IDENTICAL |
| Payments | 7/0/0 | 7/0/0 | PASS |
| Platform | 104/1/2 | 104/1/2 | PRE_EXISTING_IDENTICAL |
| Rbac | 53/9/0 | 53/9/0 | PRE_EXISTING_IDENTICAL |
| Reports | 2/0/0 | 2/0/0 | PASS |
| Sprint9E | 8/2/0 | 8/2/0 | PRE_EXISTING_IDENTICAL |
| Sprint9F | 8/3/0 | 8/3/0 | PRE_EXISTING_IDENTICAL |
| Support | 6/0/0 | 6/0/0 | PASS |
| Ui | 50/22/0 | 50/22/0 | PRE_EXISTING_IDENTICAL / EXPECTED_ARCHITECTURE_CHANGE (mobile home Strategy 1) |

**Totals:** integration **1235 pass / 189 fail / 19 skip** (1443 tests); baseline **1186 pass / 196 fail / 19 skip** (1401 tests).

## Changed-subsystem spot checks (individual baseline reruns)

| Test | Integration | Baseline individual | Classification |
|------|-------------|---------------------|----------------|
| `AdminAgentDepositVisibilityTest` (platform admin deposits) | FAIL | FAIL | PRE_EXISTING_IDENTICAL |
| `PlatformModuleControlTest` (settings hub card) | FAIL | FAIL | PRE_EXISTING_IDENTICAL |
| `JetpkHomepageCmsRecoveryTest` (support email) | FAIL | FAIL | PRE_EXISTING_IDENTICAL |
| `JetpkPageSettingsParityErrorShellTest` (asset version) | FAIL | FAIL | PRE_EXISTING_IDENTICAL |
| `MobileViewPreferenceTest::test_home_defaults_to_desktop_layout_without_preference_cookie` | FAIL | FAIL | EXPECTED_ARCHITECTURE_CHANGE |
| `MobileViewPreferenceTest::test_mobile_user_agent_auto_renders_mobile_home_without_preference_cookie` | FAIL | FAIL | EXPECTED_ARCHITECTURE_CHANGE |

## Gate counts

| Metric | Value |
|--------|------:|
| `INTRODUCED_BY_INTEGRATION` | **0** |
| `UNKNOWN` in Admin/Jetpk/FlightSearch/Ui/Client | **0** |
| Asymmetric failure test names (integration ∉ baseline batch) | **0** |

## Unit suite (accepted prior)

8 Unit failures on integration — all **PRE_EXISTING_IDENTICAL** on baseline (`Bf7d`/`Bf7e` redeclare fixed via `#[RunClassInSeparateProcess]` on integration only).

## Audits (integration `@1ff4658`)

| Audit | Result |
|-------|--------|
| `jetpk:homepage-customization-coverage-audit` | pass=27 fail=0 |
| `jetpk:canonical-business-email-audit` | fail_count=0 |
| `jetpk:homepage-content-audit --profile=jetpk` | fail_count=0 |
| `jetpk:homepage-media-audit --profile=jetpk` | fail_count=0 |
| `ota:route-page-health-audit --all` | fail=0 |
| `view:cache` / `view:clear` | PASS |
