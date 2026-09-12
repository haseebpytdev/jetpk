# Trending Route UAT — Production API Probe

Probe: `probe-trending-routes-prod.mjs`  
Evidence: `trending-routes-prod-probe.json`

## Result

| Metric | Value |
|--------|------:|
| TRENDING_ROUTES_TESTED | 4 |
| INFINITE_SEARCHING_COUNT (API) | **0** |
| DUPLICATE_SEARCH_COUNT | **0** |

All enabled production trending routes reached terminal **`ready`** with results within **1.1–4.3s** (supplier-backed).

## Owner-reported 1.5 min UI spinner

- **API layer:** searches terminate correctly on current production (`563d6d07`).
- **Client layer:** engineering source includes bounded client deadline (`CLIENT_SEARCH_DEADLINE_MS=60000`) and failed/empty terminal UI in `use-flight-results.ts` / `FlightResultsPage.tsx` — not modified in this branch WIP, already present at integration base.
- **Likely cause class:** stale public frontend build UX on production **or** segmented return / view-choice path not exercised by one-way trending probes.

## Action

No backend trending-route code change required for one-way CMS routes. Post-deploy live browser UAT still required for UI terminal states. If segmented return spinner reproduces, trace `awaitingReturnViewChoice` + `SearchProgress` path separately.

TRENDING_ROUTE_FIX_VERIFIER=**READY_FOR_POST_DEPLOY_UI** (API PASS)
