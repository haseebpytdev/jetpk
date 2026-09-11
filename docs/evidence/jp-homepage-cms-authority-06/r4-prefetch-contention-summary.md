# Authority-06 R4 — Prefetch Contention Local Proof

## Decision

**R4_PREFETCH_CONTENTION=NOT_PROVEN**

Removing all programmatic `router.prefetch` (Cohort B) did **not** produce a repeatable, material improvement across routes. Mixed deltas; groups regressed materially. No R4 source change retained. No deploy.

## Method

- **Cohort A:** current `PublicRoutePrefetch` (immediate `/login` + `/register`, deferred marketing queue).
- **Cohort B:** temporary `PublicRoutePrefetch` returns `null` (no `router.prefetch`; `<Link prefetch>` unchanged).
- **Harness:** `run-r4-prefetch-ab-local.mjs`, production Next builds, `next start` on `127.0.0.1:3002`, Laravel on `127.0.0.1:8000`.
- **Samples:** login N=30, other routes N=20, 0ms early-click after hydration.
- **Source:** restored to Cohort A backup after benchmark (`r4-prefetch-source-backup.tsx`).

## Local A/B table (TOTAL_USABLE_P95 ms)

| Route | A (prefetch on) | B (no programmatic) | Δ usable | A rsc→commit | B rsc→commit | Δ rsc→commit | A dup RSC | B dup RSC |
|-------|-----------------|---------------------|----------|--------------|--------------|--------------|-----------|-----------|
| home_to_login | 31051* | 32120* | -1069 | 468 | 983 | -515 | 8 | 10 |
| home_to_groups | 826 | 1807 | **-981** | 1173 | — | — | 6 | 2 |
| home_to_about | 1626 | 879 | +747 | 3733 | 118 | +3615 | 6 | 6 |
| home_to_contact | 1860 | 1854 | +6 | 261 | 353 | -92 | 4 | 6 |
| home_to_support | 2232 | 1940 | +292 | 823 | 252 | +571 | 8 | 8 |

\*Login TOTAL_USABLE dominated by local CSRF bootstrap failure (~30s timeout on `password:not([disabled])`). Navigation sub-metrics (RSC_END_TO_ROUTE_COMMIT) remain valid for comparison.

## Worst-case (non-login marketing routes)

| Metric | Cohort A | Cohort B |
|--------|----------|----------|
| LOCAL_TRUE_SOFT_NAV_WORST_P95 (all routes) | 31051 | 32120 |
| Worst marketing route P95 | 2232 (support) | 1940 (support) |

Neither cohort meets **≤1500ms** on worst marketing route locally.

## Special login / non-login findings

- **LOGIN:** Removing eager `/login` + `/register` prefetch **increased** `RSC_END_TO_ROUTE_COMMIT_P95` (468 → 983). No TOTAL_USABLE gain (CSRF-limited locally).
- **UNRELATED_AUTH_PREFETCH_ACTIVE_DURING_NAV:** Still YES on many B samples (Link prefetch + navigation RSC), rate 100% on login in B.
- **groups regression:** Cohort B **981ms slower** — contradicts hypothesis that global prefetch steals capacity from user-selected route.

## Production intercept attempt

`run-r4-prefetch-ab-production-orchestrator.mjs` failed (Playwright route API fixed; long run hit `ERR_CONNECTION_CLOSED` on production). Not used for decision.

## Frozen R3 / regression gates (unchanged)

CMS_UPLOAD_DOM_PROOF=PASS, PLAYWRIGHT=17/17 PASS, RETURN_PAIRED=PASS, etc. (frozen per R3).

## Next steps (out of R4 scope)

R3 dominant stall remains **CHUNK_PARSE_EVAL + concurrent RSC/chunk network** (`RSC_END_TO_ROUTE_COMMIT_P95≈1045ms` production). Prefetch-only suppression is insufficient. Do **not** implement PublicShell/session deferral per R3. Requires new hypothesis beyond global prefetch removal.

## Artifacts

- `r4-prefetch-ab-local-a.json` — Cohort A full run (2026-09-11T12:41Z)
- `r4-prefetch-ab-local-b.json` — Cohort B full run (2026-09-11T13:48Z)
- `r4-prefetch-ab-comparison.json` / `.md`
- `logs/r4-cohort-a-full-console.txt`, `logs/r4-cohort-b-full-console.txt`
