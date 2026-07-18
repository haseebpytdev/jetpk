# JetPK mobile-flight-ota.spec.ts — retirement record

**Phase:** JETPK-CMS-PRE-MERGE-REPOSITORY-HYGIENE-AND-LEGACY-TEST-CLOSURE  
**Date:** 2026-07-18  
**Replacement:** `tests/visual/non-home-mobile-scope.spec.ts`  
**Config removed:** `playwright.mobile-non-home.config.ts`

## Why retired

JetPK homepage CMS uses **Strategy 1**: canonical responsive JetPK home (`config/ota-mobile.php` → `mobile_pages.home = false`). Legacy tests assumed `mobile.home` substitution (`.ota-mobile-home-trust-bar`, `.ota-mobile-bottom-bar` on home, v1 OTA field classes). Those assumptions are obsolete on integration.

## Per-test disposition (grep subset that ran in merge gate)

| # | Test title | Route | Expected architecture | Integration (last) | Strategy 1 invalidates? | Duplicates non-home-mobile? | Action |
|---|------------|-------|----------------------|--------------------|-------------------------|----------------------------|--------|
| 1 | standalone search @ mobile360 | `/flights/search` | Mobile shell search form | FAIL (stale selectors) | No | Yes (`flights/search` @390) | **RETIRE_AS_OBSOLETE** |
| 2 | standalone search @ mobile390 | `/flights/search` | Mobile shell search form | FAIL | No | Yes | **RETIRE_AS_OBSOLETE** |
| 3 | standalone search @ mobile414 | `/flights/search` | Mobile shell search form | FAIL | No | Yes | **RETIRE_AS_OBSOLETE** |
| 4 | standalone search @ mobile430 | `/flights/search` | Mobile shell search form | FAIL | No | Yes | **RETIRE_AS_OBSOLETE** |
| 5 | results mobile chrome @ mobile360 | `/flights/results` | `jetpakistan-app` results shell | FAIL (`.ota-mobile-bottom-bar`) | No | Yes (updated selectors) | **RETIRE_AS_OBSOLETE** |
| 6 | results mobile chrome @ mobile390 | `/flights/results` | Mobile results shell | FAIL | No | Yes | **RETIRE_AS_OBSOLETE** |
| 7 | results mobile chrome @ mobile414 | `/flights/results` | Mobile results shell | FAIL | No | Yes | **RETIRE_AS_OBSOLETE** |
| 8 | results mobile chrome @ mobile430 | `/flights/results` | Mobile results shell | FAIL | No | Yes | **RETIRE_AS_OBSOLETE** |
| 9 | results sticky bar @ tablet768 | `/flights/results` | Mobile results + nav | FAIL | No | Replaced (tablet768 describe) | **REPLACE_WITH_NEW_SCOPE_TEST** |
| 10 | results sticky bar @ tablet991 | `/flights/results` | Mobile results + nav | FAIL | No | Covered by tablet768 + desktop | **RETIRE_AS_OBSOLETE** |
| 11 | desktop 1024 keeps desktop result layout | `/flights/results` | Desktop results layout | FAIL | No | Replaced (desktop1024 describe) | **REPLACE_WITH_NEW_SCOPE_TEST** |

## Full-file tests not in grep subset (also retired)

| Test title | Route | Action | Reason |
|------------|-------|--------|--------|
| home search shell @ mobile* (×4) | `/` | **RETIRE_AS_OBSOLETE** | Strategy 1 JetPK home; legacy `.ota-mobile-home-*` absent |
| home desktop search visible @ tablet* (×2) | `/` | **RETIRE_AS_OBSOLETE** | Superseded by Strategy 1 + desktop1024 home test |
| desktop 1024 keeps full homepage sections | `/` | **REPLACE_WITH_NEW_SCOPE_TEST** | `non-home-mobile-scope` desktop1024 home test |

## Replacement coverage map

| Retired behavior | Replacement test |
|------------------|------------------|
| Non-home mobile results chrome | `flights/results renders mobile chrome` @390 |
| Non-home mobile search | `mobile nav shell on flights search` @390 |
| Home mobile substitution guard | `home uses Strategy 1 responsive shell` @390 |
| Tablet results sticky/nav | `flights/results keeps mobile app shell` @768 |
| Desktop results layout | `flights/results keeps desktop result layout` @1024 |
| Desktop home sections | `home shows full JetPK sections` @1024 |
| Portal entry / booking guards | booking/customer/agent tests @390 |
| Authenticated portals | customer/agent dashboard tests |

**Normal CI:** run `playwright.non-home-mobile.config.ts` only (no `mobile-flight-ota.spec.ts`).
