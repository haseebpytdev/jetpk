# JetPK Homepage CMS Final Acceptance (Current)

**Branch:** `integration/jetpk-homepage-cms-final`
**Baseline:** `624f3dd`
**Previous HEAD:** `4ebc77b`
**Closure pass:** committed (search compat + audit fixture + Bf7e isolation)
**Date:** 2026-07-17

## Final verdict

### NOT_READY_FOR_MAIN

CMS integration is functionally complete and CMS gates pass. Full Playwright responsive baseline and isolated worktree PHPUnit comparison remain incomplete. Closure pass adds search-dropdown compatibility and local audit fixture.

## Gate summary

| Gate | Result |
|------|--------|
| 88 CMS PHPUnit tests | **PASS** (isolated) |
| Homepage Playwright 6 viewports | **PASS** (prior integration run at 824ab74) |
| Customization audit | pass=27 fail=0 |
| Canonical email audit | fail_count=0 (with `MAIL_FROM_ADDRESS=ota@jetpakistan.pk`) |
| Content audit `--profile=jetpk` | fail_count=0 (after `jetpk:local-audit-fixture --seed`) |
| Media audit `--profile=jetpk` | fail_count=0 |
| Route health `--all --seed --env=testing` | fail=0 server_errors=0 |
| view:cache / view:clear | **PASS** |
| Migrations up/down | **PASS** (prior) |
| Bf7e full-suite fatal | **RESOLVED** (`RunClassInSeparateProcess`) |
| Full Playwright responsive 4 configs | **INCOMPLETE** |
| Search dropdown Playwright | **FIX IMPLEMENTED** — rerun pending |
| HaseebMaster safety test | **PRE_EXISTING_IDENTICAL** fail |

## Closure-pass runtime changes

- `passenger-selector.blade.php` — legacy `data-pax-picker`, OTA class aliases, compat `<select>` sync
- `passengers.js` — `open` attribute, compat select wiring
- `jp-search.css` — compat select visually-hidden styles
- `home.blade.php` / `results.blade.php` — asset `v=37`
- `jetpk:local-audit-fixture --seed` — local JetPK profile for CLI audits

## Evidence documents

| Document | Path |
|----------|------|
| Runtime manifest | `docs/JETPK_HOMEPAGE_CMS_EXACT_RUNTIME_MANIFEST.md` |
| PHPUnit baseline | `docs/JETPK_HOMEPAGE_CMS_FULL_TEST_BASELINE_COMPARISON.md` |
| Playwright baseline | `docs/JETPK_HOMEPAGE_CMS_PLAYWRIGHT_BASELINE_COMPARISON.md` |
| Route security | `docs/JETPK_HOMEPAGE_CMS_NEW_ROUTE_SECURITY_MATRIX.md` |
| SSH verification | `docs/JETPK_HOMEPAGE_CMS_SSH_VERIFICATION.md` |
| SFTP commands | `docs/JETPK_HOMEPAGE_CMS_SFTP_COMMANDS.txt` |

## Recommended next action

1. Commit closure pass; push `integration/jetpk-homepage-cms-final`
2. Complete isolated worktree PHPUnit Unit+Feature on both SHAs
3. Rerun Playwright 4 configs (integration `:8000`, baseline `:8001`)
4. Rerun `public-search-dropdown.spec.ts` after compat deploy
5. Manual Admin Page Settings QA on staging
6. Approve migrations; SFTP runtime bundle; SSH clears; post-deploy audits
