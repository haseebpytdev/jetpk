# Wave-8 Phase Summary â€” Owner Retest V3

## Phase name
Owner Retest V3 Wave-8 â€” Change Flight fresh search + branded PTC transparency + Results/Travelers modernisation + passport OCR robustness

## Branch name
`feat/jetpk-flight-results-booking-flow-20260819`

## Objective
Close Wave-8 engineering remediation for Change Flight commercial semantics, selected-brand passenger pricing, Results header/nearby-dates/sort bar, Travelers polish, and multi-pass client-side passport OCR. Stop before production deploy for owner/ChatGPT review.

## Included scope
- Cluster A: Change Flight commercial-state guard + criteria-only fresh search (new search_id)
- Cluster B: Selected-brand PTC rows / no stale ECOLIGHTâ†’SMART breakdown
- Cluster C: Results hero, search-context card, nearby dates strip, single Sort control
- Cluster D: Travelers form hierarchy, Flight Summary Total label, multi-pass OCR UX
- Cluster E: Wave-8 visual matrix, gate/manifest docs

## Excluded scope
- Production deploy
- Live supplier search / PNR / hold / ticket / payment mutations
- Marking `OWNER_RETEST_V3=PASS`

## Investigation findings / root causes
1. `isPreHoldChangeFlightSafe` treated local `hold_session_id` + `hold_status=not_supported` as commercial, disabling Change Flight on pre-hold Travelers.
2. `abandonSelectedOffer` preserved old `search_id` and reused prior result cache identity.
3. Brand switch updated totals but left/mismatched prior-brand `passenger_pricing`, so Fare Details dropped Adult rows after reconcile failure.
4. Results UI duplicated sort controls (tabs + select) and needed tighter header/nearby-date hierarchy.
5. Passport OCR single full-page pass was insufficient for real phone photos; failure copy still felt device-technical.

## Exact files changed (runtime)
See `OWNER-RETEST-V3-WAVE-8-RUNTIME-MANIFEST.md`.

## Routes changed
None new. Behaviour change on `POST /booking/abandon-selected-offer`.

## Database changes
None.

## Backend / frontend changes
- Change Flight safe only when genuine supplier PNR/locator/held commercial state exists.
- Abandon clears offer/fare/`search_id`, redirects criteria-only Results URL (`fresh_search=true`).
- Sabre brand apply clears stale PTC and normalizes selected-brand passenger rows with FX-aware totals.
- Results header/nearby dates/sort bar modernisation; branded fare benefit label order.
- Travelers copy/sections + in-app Change Flight dialog; multi-pass client-side OCR.

## Tests executed
- `php artisan test --filter=Wave8` (+ Wave7 consent/change flight)
- `npm run test:document-reader` (19/19)
- `npx tsc --noEmit`
- `npx playwright test tests/owner-v3-flight-wave-8-visual-matrix.spec.ts` (after build)
- `git diff --check` on intended paths

## Screenshots
`tmp/owner-v3-flight-wave-8/` — required states 01–20; synthetic passport only.

## SHA pins
- Production baseline runtime: `a9ec8f18745c8b9db3ce62504efd485a1bb8df3e`
- `FINAL_WAVE8_ENGINEERING_SHA` = `8cf657d7d35cc97848318f56184825ac49af6225`
- Docs/visual tip: branch HEAD after Wave-8 docs commits

## Known limitations / risks
- Live Sabre SMART PTC still needs owner UAT after protected deploy.
- Connected Flight Summary visual uses an annotated layover note when the mock itinerary is direct.
- OCR multi-pass improves phone photos but cannot guarantee every low-quality capture.

## Rollback
Redeploy prior runtime SHA `a9ec8f18745c8b9db3ce62504efd485a1bb8df3e` (build `i4kZsZzH4c9IcSNyRyhRi`).

## Final status
`OWNER_RETEST_V3=FAILED_REMEDIATION_REQUIRED`  
Wave-8 **pre-deploy** engineering ready for independent review.  
**STOP BEFORE PRODUCTION DEPLOYMENT**.

