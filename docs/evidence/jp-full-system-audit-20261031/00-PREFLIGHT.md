# Phase 0 — Preflight / Provenance

DATE: 2026-10-31
HOST: https://jetpakistan.pk
REPO_REMOTE: jetpk (not origin)

## Git

| Field | Value |
|---|---|
| CURRENT_LOCAL_BRANCH | perf/jp-ui-perf-stability-01d3-group-prefetch |
| CURRENT_LOCAL_SHA | 7a75c4fa285a6a9d1ae053bdd0cf07269615af51 |
| JETPK_MAIN_SHA | 2bf821226604869f494c64d11709e741da595dfa |
| BASELINE_MATCH | YES (matches prompt ORIGIN_MAIN_SHA) |
| WORKTREE_STATUS | dirty: untracked docs/evidence/jp-ui-perf-stability-01d3-group-prefetch/02-* only |

Branch protection: `gh` not authenticated in this shell; treat as unverified this run (TEMPORARILY_BLOCKED for gh API). Protection historically required for main — recheck after auth.

## LIVE health (HTTP)

| URL | Status |
|---|---|
| /up | 200 |
| / | 200 |
| /login | 200 |

## LIVE runtime markers

| Marker | Value |
|---|---|
| Laravel deploy-sha.txt | 4e6e3acc2c48d431c4fb622eb9c462cd0588e05c |
| Frontend .next/BUILD_ID | PGYL8tGphA5eLG-rJnr1B (mtime Oct 31 11:42 UTC server) |
| Prompt expected FE selective SHA | 49f7f58e440018991d5e911664c6877fdc6529a1 (map via deploy evidence; BUILD_ID != git SHA) |

## Critical file hash parity (local working tree vs LIVE disk)

| File | LOCAL sha256 | LIVE sha256 | PARITY |
|---|---|---|---|
| FlightController.php | 24fa386ad56045f87f987119b2207faa3531c9f4dade34a27692cf7a8501a6a9 | 24fa386a… | MATCH |
| OfferBaggageResolver.php | 1492130d476e39249feed2a1f10d2fbc5c4d8409b4caa5f138597a0b6f75bd88 | 1492130d… | MATCH |
| frontend/services/flight-search.ts | eb174204… | eb174204… | MATCH |
| frontend/app/globals.css | 11b966ed… | 11b966ed… | MATCH |
| AuthPageShell.tsx | d20f511c… | d20f511c… | MATCH |

LIVE FlightController already imports `OfferBaggageResolver` and uses `summaryTextFromOffer` / `filterBucketFromOffer` (not raw `(string)$offer['baggage']` at filter meta).

Conclusion so far:

- PRODUCTION_MARKER != AUTOMATIC_PRODUCTION_FILE_PARITY still holds as a rule.
- For the Oct-28 baggage sites specifically: LIVE disk matches current local source (selective deploy present).
- Live search incident is NOT automatically “undeployed baggage fix”; must reproduce current failure boundary.

## Evidence root

`docs/evidence/jp-full-system-audit-20261031/`
