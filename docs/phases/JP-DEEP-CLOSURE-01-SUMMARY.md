# JP-DEEP-CLOSURE-01 SUMMARY

## Phase name
JP-DEEP-CLOSURE-01

## Branch
`phase/jp-flight-perf-01`

## Objective
Prove Return/Traveler latency attribution; optimize JetPakistan-controlled waits; add truthful processing UX; hygiene audit without unsafe deletes.

## Included scope
- Deep Return + Book Now / fare revalidation / Traveler path inspection
- Early progressive publish after first priced supplier offer
- Fare revalidation duplicate store-read reduction
- Processing transition UI (VALIDATING_FARE / PREPARING_TRAVELER / NAVIGATING_TO_TRAVELER)
- Return loading copy (immediate cards + “Checking for more options…”)
- Hygiene classification (no deletions)

## Excluded scope
- Cross-provider wait-all parallel dispatch (not beneficial when only Sabre eligible)
- Controller mega-refactors
- summary.md rewrite / Playwright config consolidation
- Production deploy (local `vendor/` / `node_modules` absent; certification deferred)
- MOFA / Chatwoot
- Push

## Investigation findings
1. REG-05 “pre-supplier P95 3746ms” was a **metric fall-through** on cache-hit paths; live pre-supplier ≈ 45–65ms.
2. Live Return first-useful dominated by **Sabre network (~2.1–3.3s)** + **full-batch pricing before first partial (~1.5–2.5s JetPakistan)**.
3. Only Sabre appeared eligible on live REG-05 provider rows → parallel multi-provider dispatch would not move first-useful.
4. Traveler hold_validate tail already closed in REG-05; fare revalidation still ~supplier BFM revalidate (~2.2s historically).
5. No SAFE_TO_DELETE hygiene targets at high confidence.

## Root causes addressed
- Progressive publish waited for entire supplier offer batch pricing.
- Revalidate path double-read search store (`get` + `findOffer*` + post-patch `get`).

## Exact files changed
- `app/Services/FlightSearch/FlightSearchService.php`
- `app/Services/FlightSearch/FlightSearchResultStore.php`
- `app/Support/FlightSearch/SearchPerfTrace.php`
- `app/Http/Controllers/Frontend/FlightController.php`
- `frontend/features/flight-details/hooks/use-revalidation.ts`
- `frontend/features/flight-details/components/FareProcessingTransition.tsx` (new)
- `frontend/features/flight-details/components/FlightDetailsDrawer.tsx`
- `frontend/features/flight-details/components/FareSelectionPage.tsx`
- `frontend/features/flight-details/components/FareChangeDialog.tsx`
- `frontend/features/flight-details/components/ContinueToPassengersButton.tsx`
- `frontend/features/flight-results/hooks/use-flight-results.ts`
- `frontend/features/flight-results/components/FlightResultsPage.tsx`
- `frontend/app/globals.css`
- `tests/Unit/Support/FlightSearch/SearchPerfEarlyPartialMarkTest.php` (new)
- `docs/evidence/jp-deep-closure-01/*`
- `docs/phases/JP-DEEP-CLOSURE-01-SUMMARY.md`

## Routes / DB / backend / frontend
- Routes unchanged
- DB unchanged
- Backend: early partial progress + revalidate store helpers
- Frontend: processing transition + Return status copy

## Tests executed
- `php -l` on all touched PHP files — pass
- `php artisan test` — **not run** (`vendor/` missing in this sandbox)
- Frontend build/typecheck — **not run** (`frontend/node_modules` missing)

## Known limitations / risks
- Post-deploy N≥30 certification **not executed** in this loop.
- Early partial publishes one offer first; remaining pricing still blocks the afterResponse worker (polls on other FPM workers can read early pairs).
- Fare revalidation supplier floor unchanged.

## Rollback
Revert the phase commits on `phase/jp-flight-perf-01` (exact-path). Restore prior `FlightSearchService` onProgress-at-batch-end behavior if early partial misbehaves.

## Final status
**PARTIAL** — root-cause fix + UX shipped locally; live N≥30 certification and deploy deferred.

```
RETURN_LATENCY_CLOSED=NO (pending live cert; expected large cut to JetPakistan post-network gap)
TRAVELER_LATENCY_CLOSED=NO (supplier fare revalidation floor remains)
SAFE_TO_RESUME_REPO_HYGIENE=YES (with deletion gate)
SAFE_TO_FINAL_RECERTIFICATION=NO (deploy + N>=30 required)
PUSH_PERFORMED=NO
```
