# JetPK Homepage CMS — Playwright Baseline Comparison

**Integration HEAD:** post-hygiene closure
**Baseline:** `624f3dd`
**Integration server:** `http://127.0.0.1:8000`
**Baseline server:** `http://127.0.0.1:8001` (worktree `ota-jetpk-baseline-624f3dd`)
**Date:** 2026-07-18

Runs executed **sequentially** with `--workers=1`, one Laravel server at a time.

## Suite totals

| Suite | Integration | Baseline `624f3dd` | Classification |
|-------|------------:|-------------------:|----------------|
| `public-critical-responsive.spec.ts` | **35/35** | **0/35** | Integration **PASS**; baseline **KEEP_AS_KNOWN_PRE_EXISTING** |
| `desktop-return-range-picker.spec.ts` | **4/4** | 0/4 | Integration **PASS**; baseline **STALE_SELECTOR** |
| `admin-page-settings-functional.spec.ts` | **6/6** | N/A | CMS absent on baseline |
| `non-home-mobile-scope.spec.ts` | **13/13** | N/A | Replaces retired `mobile-flight-ota.spec.ts` |
| `public-search-dropdown.spec.ts` | **10/10** | 0/10 | Baseline **STALE_SELECTOR** |

## Baseline `public-critical-responsive` — complete isolated rerun

**Command:** `LOCAL_OTA_URL=http://127.0.0.1:8001 npx playwright test -c playwright.public-critical.config.ts --workers=1`

**Result:** **0 passed, 35 failed** (log: `storage/test-results/pw-baseline-public-critical-clean.log`, gitignored)

**Root cause (not partial):** Integration Playwright helpers target post-CMS JetPK shell selectors (`[data-jp-search]`, mobile results chrome). Baseline worktree `624f3dd` serves pre-CMS markup; `gotoPublicPage` shell waits time out on every page (e.g. home @ mobile360 waiting for `[data-hero-search], main, .ota-main-nav`). This is an **expected baseline mismatch**, not an integration regression.

**Environment note:** An earlier concurrent run (integration + baseline Playwright + audits) produced the same 35/35 failure pattern; the clean isolated rerun above confirms the result without contention.

## Integration focused suites (`:8000`, `--workers=1`)

| Suite | Result |
|-------|--------|
| Admin Page Settings (fresh OTP) | 6/6 |
| Date picker functional contract | 4/4 |
| Non-home mobile scope | 13/13 |
| Public critical responsive | 35/35 |

## Legacy `mobile-flight-ota.spec.ts`

**Retired.** See `docs/JETPK_MOBILE_FLIGHT_OTA_RETIREMENT.md`. Replacement: `non-home-mobile-scope.spec.ts`.

## Introduced Playwright regressions

**0** on integration.
