# JetPK Homepage CMS Final Acceptance (Current)

**Branch:** `integration/jetpk-homepage-cms-final`
**Baseline:** `624f3dd`
**HEAD:** `824ab74` (pre-merge evidence closure pending new commit)
**Date:** 2026-07-17

## Final verdict

### NOT_READY_FOR_MAIN

CMS integration scope is **functionally complete** and **CMS tests pass**, but merge approval requires unresolved evidence gates below.

## Acceptance counts (target zero unless deferred)

| # | Metric | Count | Status |
|---|--------|------:|--------|
| 1 | Editable frontend elements without Admin field | 0 | Pass |
| 2 | Homepage Admin fields ignored by frontend | 0 | Pass |
| 3 | Dead homepage Admin fields | 0 | Pass |
| 4 | Conflicting homepage keys | 0 | Pass |
| 5 | Stale aliases without migration | 0 | Pass |
| 6 | Section-enable mismatches | 0 | Pass |
| 7 | Unintended hardcoded editorial strings | 0 | Pass |
| 8 | Tenant-scope gaps | 0 | Pass |
| 9 | Publication-path gaps | 0 | Pass |
| 10 | Preview-path gaps | 0 | Pass |
| 11 | Reset/default unhandled keys | 0 | Pass |
| 12 | Mobile disconnected keys | 0 | Pass (Strategy 1) |
| 13 | INTRODUCED_BY_INTEGRATION PHPUnit (CMS) | 0 | Pass |
| 14 | INTRODUCED_REGRESSION Playwright (home) | 0 | Pass |
| 15 | Full PHPUnit suite completion | 1 blocker | **Fail** (Bf7e fatal pre-existing) |
| 16 | Full Playwright responsive suite | 37 failures | **Fail** (mostly pre-existing/stale selectors) |
| 17 | Production audit CLI without fixture | 2 | **Fail** (content/media profile_missing without DB seed) |

## Evidence documents (this closure pass)

| Document | Path |
|----------|------|
| Exact 63-file manifest | `docs/JETPK_HOMEPAGE_CMS_EXACT_RUNTIME_MANIFEST.md` |
| PHPUnit baseline comparison | `docs/JETPK_HOMEPAGE_CMS_FULL_TEST_BASELINE_COMPARISON.md` |
| Playwright baseline comparison | `docs/JETPK_HOMEPAGE_CMS_PLAYWRIGHT_BASELINE_COMPARISON.md` |
| Route security matrix | `docs/JETPK_HOMEPAGE_CMS_NEW_ROUTE_SECURITY_MATRIX.md` |
| SSH verification | `docs/JETPK_HOMEPAGE_CMS_SSH_VERIFICATION.md` |
| SFTP commands | `docs/JETPK_HOMEPAGE_CMS_SFTP_COMMANDS.txt` |

## Verification executed

- **88/88** CMS-focused PHPUnit tests pass (incl. content neutrality)
- **6/6** homepage Playwright viewports pass on integration
- `jetpk:homepage-customization-coverage-audit`: pass=27 fail=0
- `jetpk:canonical-business-email-audit`: fail_count=0 (with `MAIL_FROM_ADDRESS=ota@jetpakistan.pk`)
- `ota:route-page-health-audit --all --seed --env=testing`: fail=0
- `view:cache` / `view:clear`: success
- Migrations up/down locally: success
- Content neutrality test: pass

## Blockers before READY_FOR_MAIN_REVIEW

1. Complete full PHPUnit Feature+Unit comparison without parallel view-cache contention
2. Complete Playwright baseline on isolated server (baseline code on port 8001 vs integration 8000)
3. Run production content/media audits on server with live `jetpk` profile (or document passing `JetpkHomepageAuditFixtureTest` as CI gate)
4. Address or formally defer `public-search-dropdown` STALE_SELECTOR suite (10 failures)

## Deferred (explicit)

| Item | Reason | Owner |
|------|--------|-------|
| Footer/global/about/support full CMS | Task 18 follow-up | Product |
| Revision retention policy | Needs approval | Platform |
| `public-search-dropdown` JetPK selector update | Separate UI sprint | UI |
| Full responsive suite pre-existing failures | Not CMS-introduced | QA |

## Production deployment

**Not executed.** See SSH verification and SFTP manifest. Migration block is approval-gated.
