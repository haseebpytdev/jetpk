# JP-HOMEPAGE-CMS-AUTHORITY-06 SUMMARY

## Phase name
JP-HOMEPAGE-CMS-AUTHORITY-06 (Authority-06)

## Branch name
`phase/jp-homepage-cms-authority-06`

## Objective
Close homepage CMS authority, branding, auth, trending, public crawl, and safe booking UAT on production. Attempt soft-nav usable P95 ≤1500ms as a binding gate.

## Included scope
- CMS media, featured deals, favicon, logo scale, confirmations, upload DOM proof
- Auth header/register cleanup, agent license live E2E
- Trending routes and public link crawl
- One-way, return paired/segmented, traveler, and checkout-safe UAT
- Return post-supplier performance certification
- Auth Playwright suite (17/17)
- Soft-nav performance investigation R2–R4 (diagnostics + negative experiments)

## Excluded scope (deferred)
- Soft-nav usable P95 ≤1500ms — **accepted residual** under `JP-SOFT-NAV-PERF-01`
- Post-`a676487e` local perf/auth experiments (Auth-C, turnstile harness) — not deployed

## Investigation findings
- All functional/CMS/booking UAT gates PASS on production SHA `8c50fc61`.
- `TRUE_SOFT_NAV_WORST_USABLE_P95≈2367ms` (`home_to_support`) exceeds the 1500ms binding target.
- R3: dominant stall is `CHUNK_PARSE_EVAL` + RSC/router transition (`RSC_END_TO_ROUTE_COMMIT_P95≈1045ms`).
- R4: wholesale prefetch removal did not prove contention; rejected.

## Root causes (soft-nav residual)
- Route chunk topology and parse/eval cost during client soft navigation
- RSC flight + post-RSC router commit interval (not harness inflation — R3 validated)
- Prefetch-only suppression insufficient (R4 negative result)

## Exact files changed (engineering, deployed)
See production SHA `8c50fc61967e51c5b576375b55ae7701d122c121` — perf soft-nav R2 commits on branch; full file list in deploy evidence under `docs/evidence/jp-homepage-cms-authority-06/`.

## Routes changed
None in closure commit (docs only).

## Database changes
None in closure commit.

## Tests executed (frozen PASS)
- AUTH_PLAYWRIGHT: 17/17 PASS
- ONE_WAY_UAT, RETURN_PAIRED_UAT, RETURN_SEGMENTED_UAT, TRAVELER_UAT, CHECKOUT_SAFE_UAT: PASS
- RETURN_DUPLICATES: 0; RETURN_POST_SUPPLIER_P95: 835ms
- SUPPLIER_MUTATION_CALLS: 0

## Known limitations
- `SOFT_NAV_PERFORMANCE_GATE=ACCEPTED_RESIDUAL` — worst usable P95 ≈2367ms vs 1500ms target
- Follow-up: `JP-SOFT-NAV-PERF-01`

## Risks
- Accepting residual leaves marketing-route soft nav above target on slow samples; no functional regression identified.

## Rollback
Production frozen at `8c50fc61967e51c5b576375b55ae7701d122c121`. Protected deploy rollback per `docs/jetpk/DEPLOYMENT-CONTEXT.md` if needed.

## Final status

```
AUTHORITY_06_FUNCTIONAL_STATUS=PASS
AUTHORITY_06_PRODUCTION_UAT=PASS
AUTHORITY_06_SECURITY_SAFETY=PASS
AUTHORITY_06_DEPLOYMENT=PASS
SOFT_NAV_PERFORMANCE_GATE=ACCEPTED_RESIDUAL
AUTHORITY_06_STATUS=CLOSED_WITH_ACCEPTED_PERFORMANCE_RESIDUAL
```

Closure evidence: `docs/evidence/jp-homepage-cms-authority-06/final-closure-accepted-residual.md`
