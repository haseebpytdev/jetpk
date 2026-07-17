# JetPK Homepage CMS Final Acceptance (Current)

**Branch:** `integration/jetpk-homepage-cms-final`
**Baseline:** `624f3dd`
**HEAD:** `532fe45` (+ baseline comparison closure commit pending)
**Date:** 2026-07-17
**Phase:** JETPK-CMS-FINAL-BASELINE-COMPARISON-ONLY

## Final verdict

### NOT_READY_FOR_MAIN

CMS integration passes CMS-specific gates, search dropdown, and critical audits. Full isolated PHPUnit Feature suite and complete baseline Playwright server comparison remain incomplete. Homepage `desktop1440` Playwright check is flaky under multi-server load. `desktop-return-range-picker` uses stale OTA selectors.

## Gate summary

| Gate | Result |
|------|--------|
| `git diff --check 624f3dd..HEAD` | **FAIL** → trailing whitespace in docs (fixed in pending commit) |
| Conflict markers | **PASS** |
| 42 core CMS PHPUnit tests | **PASS** |
| 88 CMS package PHPUnit (prior) | **PASS** |
| Search dropdown Playwright | **10/10 PASS** |
| Homepage critical (5 vp isolated) | **4/5** (desktop1440 flaky) |
| flights-results @ mobile390 | **PASS** |
| Customization audit | pass=27 fail=0 |
| Canonical email audit | fail_count=0 |
| Content audit `--profile=jetpk` | fail_count=0 |
| Media audit `--profile=jetpk` | fail_count=0 |
| Route health `--all --seed --env=testing` | fail=0 server_errors=0 |
| view:cache / view:clear | **PASS** |
| Bf7d+Bf7e together (integration) | **14/15**, no redeclare fatal |
| Bf7e blocked JSON (both SHAs) | **PRE_EXISTING_IDENTICAL** |
| HaseebMaster safety test | **PRE_EXISTING_IDENTICAL** fail |
| `jetpk:local-audit-fixture` production | **REFUSES** (exit 1) |
| Fixture in SFTP/runtime manifest | **EXCLUDED** (not in `JETPK_HOMEPAGE_CMS_SFTP_COMMANDS.txt`) |
| Full Unit+Feature both worktrees | **INCOMPLETE** |
| Baseline Playwright isolated | **INCOMPLETE** |
| INTRODUCED_BY_INTEGRATION PHPUnit | **0** after `DefaultClientCanonicalRedirectTest` theme fix |
| Admin CMS functional (42-test batch) | **PASS** |

## Closure-pass changes (this phase)

- `JetpkLocalAuditFixtureCommand` — removed `--force`; refuses `production`; local/testing only
- `DefaultClientCanonicalRedirectTest` — fixture themes `v1-classic` → `jetpakistan` (fixes login 500)
- Evidence docs updated with isolated comparison results

## Evidence documents

| Document | Path |
|----------|------|
| Runtime manifest | `docs/JETPK_HOMEPAGE_CMS_EXACT_RUNTIME_MANIFEST.md` |
| PHPUnit baseline | `docs/JETPK_HOMEPAGE_CMS_FULL_TEST_BASELINE_COMPARISON.md` |
| Playwright baseline | `docs/JETPK_HOMEPAGE_CMS_PLAYWRIGHT_BASELINE_COMPARISON.md` |
| Route security | `docs/JETPK_HOMEPAGE_CMS_NEW_ROUTE_SECURITY_MATRIX.md` |
| SFTP commands | `docs/JETPK_HOMEPAGE_CMS_SFTP_COMMANDS.txt` |

## Recommended next action

1. Push closure commit on `integration/jetpk-homepage-cms-final`
2. Complete Feature suite with `php -d memory_limit=2G` on both worktrees
3. Finish baseline `:8001` Playwright matrix
4. Fix or skip `desktop-return-range-picker` JetPK trip-type selector
5. Rerun homepage `desktop1440` with single server / no parallel audits
6. Manual Admin Page Settings QA on staging before SFTP
