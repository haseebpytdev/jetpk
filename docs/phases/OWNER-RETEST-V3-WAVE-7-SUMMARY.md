# Wave-7 Phase Summary — Owner Retest V3

## Phase name
Owner Retest V3 Wave-7 — selected fare persistence, authoritative multipax Fare Details, passport OCR, checkout experience (pre-deploy closure)

## Branch name
`feat/jetpk-flight-results-booking-flow-20260819`

## Objective
Close Wave-7 engineering remediation and remaining **pre-deploy** gates (terms version authority, bounded OCR terminate, self-hosted Tesseract gate, full visual matrix, exact runtime manifest), then stop before production deploy for owner/ChatGPT review.

## Included scope
- Cluster A: selected fare option key persistence Continue → Travelers
- Cluster B: FX-normalized passenger_pricing + branded PTC rows + Fare Details table
- Cluster C: bounded local OCR, self-hosted Tesseract assets, title rules, terminate ceiling
- Cluster D: Flight Summary baggage/meal, Change flight pre-hold abandon, mandatory terms
- Cluster E / pre-deploy closure: server-authoritative terms versions, visual matrix VISUAL_GREEN=YES, exact runtime manifest

## Excluded scope
- Production deploy
- Live supplier search / PNR / hold / ticket / payment / wallet mutations
- Marking `OWNER_RETEST_V3=PASS`

## Investigation findings / root causes
1. Revalidation handoff omitted `fare_option_key`; Travelers fell back to base ECONOMY BASIC / 0 kg.
2. Supplier PTC rows stayed in USD after FX; Fare Details hid them instead of converting with the priced FX rate.
3. Sabre branded option selection did not always refresh `fare_breakdown.passenger_pricing`.
4. Passport OCR could hang without timeout / worker termination / self-hosted assets; terminate itself could hang the UI.
5. Travelers lacked mandatory versioned terms acceptance and a safe pre-hold Change flight path.
6. Backend previously persisted arbitrary client `terms_version` strings as legal evidence.

## Exact files changed (runtime)
See `OWNER-RETEST-V3-WAVE-7-RUNTIME-MANIFEST.md` for the git-derived list from `9653d5ab…` → `FINAL_WAVE7_ENGINEERING_SHA`.

## Routes changed
- `POST /booking/abandon-selected-offer` (`booking.abandon-selected-offer`)

## Database changes
None. Migrations: none.

## Backend / frontend changes
- Server-authoritative checkout consent record (`terms_version` / `privacy_version` / `accepted_at` / session association).
- Client may submit current `terms_version` for stale-page detection only; mismatch → controlled 422.
- OCR `terminateWorkerSafely` bounded cleanup; fail-closed Tesseract postinstall (no CDN).
- Selected fare / multipax Fare Details / Travelers parity / Change flight / Terms UI as prior Wave-7 clusters.

## Tests executed (pre-deploy closure)
- `php artisan test --filter=Wave7` — 9 passed / 63 assertions
- `npm run test:document-reader` — 17/17
- `npx tsc --noEmit` — PASS
- `npx playwright test tests/owner-v3-flight-wave-7-selected-fare.spec.ts` — 3/3
- `npx playwright test tests/owner-v3-flight-wave-7-visual-matrix.spec.ts` — 1/1
- `node scripts/bundle-tesseract-assets.mjs` — PASS (local assets only)
- `git diff --check` on engineering paths — clean
- Frontend production build: compile succeeded (typecheck gate also PASS)

## Screenshots
`tmp/owner-v3-flight-wave-7/` — required states 01–14 genuine mock navigation; synthetic passport only; no real PII.

## SHA pins
- `FINAL_WAVE7_ENGINEERING_SHA=a9ec8f18745c8b9db3ce62504efd485a1bb8df3e`
- `FINAL_WAVE7_DOCS_SHA` = docs commit after this summary (branch tip after docs push)
- Deployed production remains `9653d5ab488ec6ba971ff76324894057ca8c3ffb`

## Known limitations / risks
- Live production UAT still required after protected deploy; engineering matrix does not itself prove live Sabre/IATI payloads.
- Branded meal/seat amenity richness still depends on supplier-provided fields; Wave-7 does not fabricate missing benefits.
- Native `window.confirm` Change-flight dialog is proven via dialog message capture + visual overlay of that exact copy (browser cannot rasterize native dialog chrome).

## Rollback
Redeploy prior runtime SHA `9653d5ab488ec6ba971ff76324894057ca8c3ffb` (build `JK8nDb8vrOeyjOA4Ue1Jg`).

## Final status
`OWNER_RETEST_V3=FAILED_REMEDIATION_REQUIRED` (do not set PASS).  
Wave-7 **pre-deploy** source/test/visual gates closed for review.  
**STOP BEFORE PRODUCTION DEPLOYMENT**.
