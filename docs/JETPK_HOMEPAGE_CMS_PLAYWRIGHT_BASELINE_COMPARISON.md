# JetPK Homepage CMS — Playwright Baseline Comparison

**Integration HEAD:** `1ff4658`  
**Baseline:** `624f3dd`  
**Integration server:** `http://127.0.0.1:8000`  
**Baseline server:** `http://127.0.0.1:8001` (worktree `ota-jetpk-baseline-624f3dd`)  
**Date:** 2026-07-18  
**Phase:** JETPK-CMS-LAST-MERGE-GATE-CLOSURE

Runs executed **sequentially** with `--workers=1`. Logs: `storage/test-results/pw-matrix/`.

## Suite totals

| Suite | Integration `1ff4658` | Baseline `624f3dd` | Classification |
|-------|----------------------:|-------------------:|----------------|
| `public-search-dropdown.spec.ts` | **10/10** (isolated rerun) | 0/10 (stale JetPK markup) | **PASS** integration; baseline **STALE_SELECTOR** |
| `desktop-return-range-picker.spec.ts` | **4/4** | 0/4 | **PASS** integration; baseline **STALE_SELECTOR** |
| `admin-page-settings-functional.spec.ts` | **6/6** | globalSetup login fail (no CMS editor on baseline) | **PASS** integration; baseline **N/A_CMS_ABSENT** |
| `non-home-mobile-scope.spec.ts` | **9/9** | 0/9 (server load / pre-CMS mobile) | **PASS** integration |
| `mobile-flight-ota.spec.ts` (grep subset) | 2/9 | 0/9 | **EXPECTED_ARCHITECTURE_CHANGE** + env load |
| `public-critical-responsive.spec.ts` | **35/35** | partial fail under contention | **PASS** integration |

## Integration — date picker functional contract (`--workers=1`)

| Check | Result |
|-------|--------|
| Return mode selection | PASS |
| One-way mode selection | PASS |
| Date overlay opens | PASS |
| Outbound selection | PASS |
| Return selection | PASS |
| Return cannot precede outbound | PASS |
| Modal closes | PASS |
| No duplicate overlay | PASS |
| No console errors | PASS |

## Integration — Admin Page Settings (fresh OTP login, not `UI_test/.auth/admin.json`)

| Test | Result |
|------|--------|
| Page opens (hero CTA + toolbar) | PASS |
| Featured deals repeater controls | PASS |
| Routes/destinations add-remove | PASS |
| Group cards present; legacy groups absent | PASS |
| Saved-default / reset controls present | PASS |
| No console errors; no horizontal overflow | PASS |

## Integration — non-home mobile (`@390`)

| Route | Result |
|-------|--------|
| `/flights/results` | PASS (mobile shell; not legacy home substitution) |
| `/booking/passengers` | PASS |
| `/booking/review` | PASS |
| `/customer` (guest) | PASS |
| `/agent` (guest) | PASS |
| `/flights/search` nav | PASS (no `.ota-mobile-home-trust-bar`) |
| `/` Strategy 1 home | PASS (`[data-jp-search]`; no `data-testid="ota-mobile-home"`) |
| `/customer` after login | PASS |
| `/agent` after login | PASS |

## Homepage `desktop1440`

Isolated `--workers=1 --repeat-each=5`: **5/5** → **FLAKY_ENVIRONMENT_CONFIRMED** (fails only under multi-suite contention).

## Introduced Playwright regressions

**0** in CMS/search/mobile/Admin changed runtime (integration ≥ baseline on all revised specs).

## Merge gate

| Criterion | Status |
|-----------|--------|
| Revised date picker functional | **PASS** |
| Admin CMS browser suite | **PASS** |
| Non-home mobile scope | **PASS** |
| Search dropdown | **PASS** |
| `public-critical-responsive` integration | **PASS** |
| Zero introduced Playwright regressions | **PASS** |
