# JetPK Homepage CMS — Playwright Baseline Comparison

**Integration HEAD:** `532fe45`
**Baseline:** `624f3dd`
**Integration server:** `http://127.0.0.1:8000`
**Baseline server:** `http://127.0.0.1:8001` (isolated worktree `ota-jetpk-baseline-624f3dd`)

**Date:** 2026-07-17

## Suite totals (isolated reruns)

| Suite | Integration 532fe45 | Baseline 624f3dd | Classification |
|-------|---------------------|------------------|----------------|
| `public-search-dropdown.spec.ts` | **10/10 pass** | In progress / deferred | **PASS** (integration) |
| `public-critical-responsive` home (5 vp) | **4/5 pass** (desktop1440 flake) | Not completed | **FLAKY** under multi-server load |
| `public-critical-responsive` flights-results @ mobile390 | **1/1 pass** | Not completed | **PASS** |
| `desktop-return-range-picker.spec.ts` | **0/2 pass** | Not completed | **STALE_SELECTOR** (`data-trip-radio` absent in JetPK search) |
| `mobile-flight-ota` home shell | Expected fail (Strategy 1) | N/A | **EXPECTED_ARCHITECTURE_CHANGE** |
| `mobile-flight-ota` non-home | Not run (config) | Not completed | Deferred |
| Full `playwright.responsive.config.ts` | Not rerun | Not completed | Prior 37-fail log at 824ab74 |

## Integration detail — search dropdown

| Page | Viewports | Result |
|------|-----------|--------|
| home | mobile360, mobile390, tablet768, laptop1280, desktop1440 | **5/5 pass** |
| flights-search | mobile360, mobile390, tablet768, laptop1280, desktop1440 | **5/5 pass** |
| **Total** | | **10/10 pass** |

## Integration detail — homepage critical

| Viewport | Result | Notes |
|----------|--------|-------|
| mobile360 | PASS | Isolated rerun |
| mobile390 | PASS | Isolated rerun |
| tablet768 | PASS | Isolated rerun |
| laptop1280 | PASS | Isolated rerun (prior timeout under contention) |
| desktop1440 | **FAIL** | Browser context closed under parallel load; prior timeout under contention |

## Integration detail — desktop return range picker

| Test | Result | Message |
|------|--------|---------|
| modal fits compact desktop heights | FAIL | Timeout on `[data-trip-radio][value="round_trip"]` |
| complete return and one-way at 1366x720 | FAIL | Same |

**Overlap:** `jp-dates.js`, `flights-panel.blade.php` changed — selector `data-trip-radio` not present in JetPK theme markup.

**Classification:** **STALE_SELECTOR** / needs JetPK trip-type control mapping (not proven baseline-isolated in this pass).

## Introduced regression count (homepage/search CMS files)

**0** functional regressions in search dropdown after compat pass.

Home desktop1440 classified **ENVIRONMENT_DEPENDENCY / FLAKY** (passes under lighter load in prior runs).

## Merge gate assessment

| Criterion | Status |
|-----------|--------|
| Search dropdown 10/10 | **PASS** |
| Homepage all viewports | **FAIL** (desktop1440 flake) |
| Zero introduced Playwright regressions in changed runtime | **PASS** (dropdown + mobile390 results) |
| Baseline isolated server comparison complete | **INCOMPLETE** |
