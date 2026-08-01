# JP-OPS-01 Executive Summary (JP-OPS-01A reconciled)

**Phase:** JP-OPS-01 / JP-OPS-01A | **Branch:** `phase/jetpk-ops-01-full-stack-operational-audit` | **SHA:** `cfd65a76b448ec7fb77fddfb4995f290b5d841b3`

## Canonical counting units

| Model | Denominator | Purpose |
|-------|------------:|---------|
| **A. Route/page records** | Laravel 584 + Next 96 | Every route or `page.tsx` classified independently |
| **B. Unique operational contracts** | 31 | FE consumer or UI action ↔ authoritative Laravel action |
| **C. Gap records** | 15 | One row per gap ID |

Do not sum Laravel routes + Next pages to derive contract count.

## Exact surface counts (route/page records)

| Surface | Count |
|---------|------:|
| Laravel route records | 584 |
| Next public/B2C pages | 39 |
| Next Customer pages | 11 |
| Next Agent portal pages | 15 |
| Next Agent auth/registration pages | 2 |
| Next dashboard pages | 29 |
| **Next pages total** | **96** |
| **Frontend pages total** | **67** |

**Agent registration** (`/agent/register`, `/agent/register/submitted`) are counted in **Agent auth/registration (2)**, not Agent portal (15) or public/B2C (39). Sum: 39+11+15+2=67.

## Classification counts (mutually exclusive per record type)

### Laravel route records (denominator 584)

| Classification | Count |
|----------------|------:|
| OPERATIONAL_CONNECTED | 244 |
| PARTIALLY_CONNECTED | 285 |
| FRONTEND_WITHOUT_BACKEND_CONTRACT | 0 |
| BACKEND_WITHOUT_FRONTEND_BINDING | 0 |
| RBAC_BLOCKED_OR_UNVERIFIED | 0 |
| SUPPLIER_CAPABILITY_BLOCKED | 0 |
| INTENTIONALLY_UNAVAILABLE | 0 |
| DEPRECATED | 0 |
| TEST_ONLY | 55 |
| UNKNOWN_REQUIRES_VERIFICATION | 0 |
| **Sum** | **584** |

**Blade correction:** Admin/Staff routes (285) are `PARTIALLY_CONNECTED` — Blade operational, Next dashboard mutation migration pending. Not `BACKEND_WITHOUT_FRONTEND_BINDING`.

### Next page records (denominator 96)

| Classification | Count |
|----------------|------:|
| OPERATIONAL_CONNECTED | 66 |
| PARTIALLY_CONNECTED | 28 |
| FRONTEND_WITHOUT_BACKEND_CONTRACT | 0 |
| BACKEND_WITHOUT_FRONTEND_BINDING | 0 |
| RBAC_BLOCKED_OR_UNVERIFIED | 0 |
| SUPPLIER_CAPABILITY_BLOCKED | 0 |
| INTENTIONALLY_UNAVAILABLE | 1 |
| DEPRECATED | 0 |
| TEST_ONLY | 1 |
| UNKNOWN_REQUIRES_VERIFICATION | 0 |
| **Sum** | **96** |

### Unique operational contracts (denominator 31)

| Classification | Count |
|----------------|------:|
| OPERATIONAL_CONNECTED | 19 |
| PARTIALLY_CONNECTED | 5 |
| FRONTEND_WITHOUT_BACKEND_CONTRACT | 6 |
| BACKEND_WITHOUT_FRONTEND_BINDING | 0 |
| RBAC_BLOCKED_OR_UNVERIFIED | 0 |
| SUPPLIER_CAPABILITY_BLOCKED | 1 |
| INTENTIONALLY_UNAVAILABLE | 0 |
| DEPRECATED | 0 |
| TEST_ONLY | 0 |
| UNKNOWN_REQUIRES_VERIFICATION | 0 |
| **Sum** | **31** |

## Blade vs Next dashboard operational counts

| Metric | Count |
|--------|------:|
| Blade-operational Admin/Staff mutations | 159 |
| Next-dashboard connected reads (`GET /api/dashboard/*`) | 38 |
| Next-dashboard connected mutations | 0 |
| Next-dashboard missing mutations (Blade-only today) | 159 |

## P0 reassessment

**P0 count: 0**

| Former ID | Reassessment |
|-----------|--------------|
| GAP-002 mockUser | **Downgraded to P3** — presentation fallback when `session` prop null; `dashboard/app/layout.tsx` fetches `getDashboardSession()`; does not gate auth/ownership/mutations |
| GAP-010 fixture KPIs | **Downgraded to P1** — fixture mode active when `NEXT_PUBLIC_DASHBOARD_MODE` ≠ `live`; misconfiguration risk, not default server authority bypass |

## Gap severity (15 gap records)

| Severity | Count |
|----------|------:|
| P0 | 0 |
| P1 | 7 |
| P2 | 6 |
| P3 | 1 |
| P4 | 1 |

## Readiness conclusions (reconciled language)

| Area | Status |
|------|--------|
| **B2C booking** | CODE_CONNECTED_AND_LOCALLY_VERIFIED — standard journey wired; return pairing SUPPLIER_CERTIFICATION_READY per provider |
| **Payment** | CODE_CONNECTED_AND_LOCALLY_VERIFIED — AbhiPay callback server-authoritative; PRODUCTION_RUNTIME_UNVERIFIED for gateway |
| **Wallet/deposits** | CODE_CONNECTED_AND_LOCALLY_VERIFIED (agent Next); admin approval BLADE_OPERATIONAL_NEXT_MIGRATION_PENDING |
| **Suppliers** | Mixed — Sabre/PIA NDC/One API CODE_CONNECTED; IATI EXTERNAL_DEPENDENCY_BLOCKED (auth) |
| **Queues/scheduler** | CODE_READY_RUNTIME_UNVERIFIED |
| **Customer portal** | CODE_CONNECTED_AND_LOCALLY_VERIFIED for core pages; cancellation FRONTEND_WITHOUT_BACKEND_CONTRACT |
| **Agent portal** | CODE_CONNECTED_AND_LOCALLY_VERIFIED for wallet/bookings; staff/reports BLADE_OPERATIONAL_NEXT_MIGRATION_PENDING |
| **Admin/Staff** | BLADE_OPERATIONAL_NEXT_MIGRATION_PENDING — Blade mutations operational; Next dashboard read-connected only |

## Next phase

**JP-OPS-02** (auth/production OTP) + **JP-OPS-05** (dashboard live-mode gates + mutation API) before three-agency pilot.

Machine-readable metrics: [`JP-OPS-01-FULL-STACK-ROUTE-INVENTORY.json`](JP-OPS-01-FULL-STACK-ROUTE-INVENTORY.json), [`JP-OPS-01-GAP-REGISTER.json`](JP-OPS-01-GAP-REGISTER.json).
