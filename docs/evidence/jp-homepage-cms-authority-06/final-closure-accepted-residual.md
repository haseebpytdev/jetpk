# Authority-06 — Final Closure (Accepted Soft-Nav Performance Residual)

**Closed:** 2026-09-12  
**Owner decision:** Accept residual soft-nav performance debt; stop Authority-06 performance remediation.

## Closure decision

Authority-06 business and functional scope is **complete**. All functional, CMS, booking-flow, UAT, safety, and regression gates are frozen **PASS**.

The only unresolved original acceptance criterion:

| Criterion | Target | Final measured (production) | Gate |
|-----------|--------|----------------------------|------|
| `TRUE_SOFT_NAV_WORST_USABLE_P95` | ≤1500ms | **≈2367ms** (`home_to_support`) | **ACCEPTED_RESIDUAL** |

**Do not relabel this metric PASS.** This is an owner-approved scope split, not a threshold pass.

```
AUTHORITY_06_FUNCTIONAL_STATUS=PASS
AUTHORITY_06_PRODUCTION_UAT=PASS
AUTHORITY_06_SECURITY_SAFETY=PASS
AUTHORITY_06_DEPLOYMENT=PASS
SOFT_NAV_PERFORMANCE_GATE=ACCEPTED_RESIDUAL
AUTHORITY_06_STATUS=CLOSED_WITH_ACCEPTED_PERFORMANCE_RESIDUAL
```

## SHA anchors

| Key | Value |
|-----|-------|
| ENGINEERING_SHA | `8c50fc61967e51c5b576375b55ae7701d122c121` |
| PRODUCTION_SHA | `8c50fc61967e51c5b576375b55ae7701d122c121` |
| PRIOR_EVIDENCE_HEAD | `a676487e25166a795ea25c8ec5cad0aea31def59` |
| DEPLOYED_AGAIN | **NO** |
| NEXT_PERFORMANCE_ITEM | `JP-SOFT-NAV-PERF-01` |

## Frozen completed gates

| Gate | Status |
|------|--------|
| CMS_MEDIA | PASS |
| FEATURED | PASS |
| FAVICON | PASS |
| LOGO_SCALE | PASS |
| CMS_CONFIRMATIONS | PASS |
| CMS_UPLOAD_DOM_PROOF | PASS |
| AUTH | PASS |
| AGENT_LICENSE_LIVE_E2E | PASS |
| TRENDING | PASS |
| PUBLIC_CRAWL | PASS |
| ONE_WAY_UAT | PASS |
| RETURN_PAIRED_UAT | PASS |
| RETURN_SEGMENTED_UAT | PASS |
| TRAVELER_UAT | PASS |
| CHECKOUT_SAFE_UAT | PASS |
| RETURN_POST_SUPPLIER_P95 | 835ms |
| RETURN_DUPLICATES | 0 |
| AUTH_PLAYWRIGHT | 17/17 PASS |
| SUPPLIER_MUTATION_CALLS | 0 |
| PRODUCTION_HEALTH | PASS |
| DEPLOY_HYGIENE | PASS |

Evidence: `postdeploy-certification.md`, `final-certification-recovery.md`, `final-flight-uat.json`, `final-cms-confirmations.json`, `final-agent-license-live.json`, `final-public-crawl.json`, `cms-upload-dom-proof.json`, `postdeploy-cms-live-uat.json`, `logs/auth-playwright-17-17-console.txt` (via `6d9870d2`).

## Performance history (preserved)

### Original binding target

```
SOFT_NAV_USABLE_P95_TARGET=1500ms
```

### Final Authority-06 production measurement (validated harness, production SHA `8c50fc61`)

| Route | USABLE_P95 |
|-------|----------:|
| HOME_TO_LOGIN | ≈2294ms |
| HOME_TO_GROUPS | ≈2106ms |
| HOME_TO_ABOUT | ≈1412ms |
| HOME_TO_CONTACT | ≈1868ms |
| HOME_TO_SUPPORT | ≈2367ms |

```
TRUE_SOFT_NAV_WORST_USABLE_P95≈2367ms
```

**SOFT_NAV_PERFORMANCE ≠ PASS**

### R3 finding (production diagnostic, frozen)

| Field | Value |
|-------|-------|
| HARNESS_VALID | YES |
| RSC_END_TO_ROUTE_COMMIT_P95 | ≈1045ms |
| ROUTE_COMMIT_TO_USABLE_P95 | ≈246ms |
| DOMINANT_BUCKET | CHUNK_PARSE_EVAL + RSC/router transition |

Evidence: `r3-diagnostic-summary.md`, `router-wait-harness-validation.json` (production cohort), `router-wait-traces/`.

### R4 finding (negative experiment, preserved)

| Field | Value |
|-------|-------|
| PREFETCH_CONTENTION | NOT_PROVEN |
| WHOLESALE_PREFETCH_REMOVAL | REJECTED |

Evidence: `r4-prefetch-contention-summary.md`, `r4-prefetch-ab-comparison.json`, `r4-prefetch-ab-local-a.json`, `r4-prefetch-ab-local-b.json`.

### Prior remediation attempts (not discarded)

| Round | Hypothesis | Outcome |
|-------|------------|---------|
| R2 | Early-click / soft-nav latency reduction (`8c50fc61`) | Deployed; worst usable still >1500ms |
| R3 | Router-wait causality / chunk parse eval | Dominant stall identified; no deploy |
| R4 | Idle/deferred prefetch contention | NOT_PROVEN; wholesale prefetch removal REJECTED |
| Auth-C / Hypothesis C | Auth shell isolation from marketing bundle | Local login improvement; **not deployed**; does not close ≤1500ms gate |
| Turnstile harness | Booking lookup Playwright | Hardened locally; **not deployed** as part of this closure |

## Deferred workstream

Soft-nav performance remediation continues under **`JP-SOFT-NAV-PERF-01`** (`docs/phases/JP-SOFT-NAV-PERF-01.md`). That item does **not** block Authority-06 closure.

## Rollback

Production remains on `8c50fc61967e51c5b576375b55ae7701d122c121`. No rollback required for this closure (docs-only evidence commit).
