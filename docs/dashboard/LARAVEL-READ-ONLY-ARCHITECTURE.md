# Laravel Read-Only Integration Architecture — JETPK-DASH-11

Phase: **JETPK-DASH-11 Prompt 01** (contracts + abstraction only)

## Principles

1. **Laravel remains authoritative** for authentication, RBAC, and production data.
2. **Dashboard remains Next.js** at `/testdash` (future: static export under Laravel `public/`).
3. **Authentication is server-authoritative** — Laravel session cookies; no parallel JWT store.
4. **All DASH-11 operations are read-only** — GET endpoints only in this phase.
5. **No mutation endpoints** — no POST/PUT/PATCH/DELETE for integration work.
6. **No silent fixture/live mixing** — source mode is explicit in metadata and UI.
7. **Data source is explicit** — `DataSourceMode` on every envelope.
8. **Stale-data state is explicit** — `staleAfter` + `StaleDataNotice`.
9. **Unauthorized state is explicit** — 401 → `UnauthorizedState`.
10. **Service errors are sanitized** — no stack traces, SQL, or paths in UI.
11. **Sensitive fields removed at server boundary** — see security doc.
12. **GDS/NDC distinctions remain intact** — channel badges and separate KPIs.
13. **Currency explicit per record** — no implicit conversion in read layer.
14. **Date/timezone interpretation explicit** — `referenceTime` on reports/overview.
15. **Fixture mode remains available** for development and tests.

## Data source mode

```typescript
type DataSourceMode = "fixture" | "laravelReadOnly" | "unavailable";
```

| Mode | When | UI |
|------|------|-----|
| `fixture` | `NEXT_PUBLIC_USE_MOCK_DATA !== "false"` (default preview) | `FixtureDataNotice` |
| `laravelReadOnly` | `NEXT_PUBLIC_USE_MOCK_DATA=false` + live adapter configured | `LiveReadOnlyNotice` |
| `unavailable` | `NEXT_PUBLIC_DATA_SOURCE_UNAVAILABLE=true` or adapter missing | `ServiceUnavailableState` |

Resolution: `dashboard/lib/read-only/data-source.ts` → `resolveDataSourceMode()`.

## Data source state

```typescript
type DataSourceState =
  | "loading" | "ready" | "stale" | "empty"
  | "unauthorized" | "forbidden" | "unavailable" | "error";
```

Module results may embed `state` (e.g. `UsersModuleResult`). UI maps states to shared components.

## Data source metadata

```typescript
type DataSourceMetadata = {
  source: DataSourceMode;
  fetchedAt: string | null;
  referenceTime: string | null;
  staleAfter: string | null;
  requestIdSafe: string | null;  // never raw request id
  recordCount: number | null;
  fixtureRevision: string | null;
  schemaVersion: string;           // "dash-read-only-v1"
};
```

## Response envelope

```typescript
type ReadOnlyResponseEnvelope<T> = {
  data: T;
  meta: DataSourceMetadata;
  pagination?: { page, pageSize, total, pageCount };
  filters?: Record<string, unknown>;
  source: DataSourceMode;
  generatedAt: string;
  referenceTime: string | null;
  warnings: { code: string; message: string }[];
  schemaVersion: string;
};
```

Implementation: `dashboard/lib/read-only/response-envelope.ts`.

## Error envelope

```typescript
type ReadOnlyErrorEnvelope = {
  error: {
    code: "unauthenticated" | "forbidden" | "validation_error" | "not_found"
         | "unavailable" | "timeout" | "rate_limited" | "internal_error";
    message: string;           // sanitized
    referenceIdSafe: string;
    details?: Record<string, string[]>;
  };
  meta: { source, schemaVersion };
};
```

Implementation: `dashboard/lib/read-only/error-envelope.ts`.

**Never expose:** stack traces, SQL, file paths, credentials, supplier secrets, exception class names.

## Frontend service abstraction

`dashboard/lib/read-only/read-only-service.ts`:

- `createReadOnlyService({ module, fixtureAdapter, laravelAdapter? })`
- `fetchReadOnly(query)` — selects adapter by mode
- **No silent fallback** when Laravel fails or adapter missing
- `fetchLaravelReadOnly(url)` — GET only, `credentials: "same-origin"`
- `assertReadOnlyHttpMethod("GET")` guard

Existing module services (`booking-service.ts`, etc.) remain fixture-backed until Prompt 02+ migration.

## Authentication contract (architecture)

| Topic | Contract |
|-------|----------|
| Credential | Laravel `laravel_session` cookie (httpOnly) |
| Transport | Same-origin preferred when dashboard served from Laravel |
| CSRF | Required for any future mutation phases; read-only GET exempt |
| Session expiry | 401 → `UnauthorizedState`; redirect to Laravel login (Prompt 02) |
| Identity summary | `GET /api/dashboard/session` → name, email, roles (safe fields) |
| RBAC | Server-enforced; UI is presentational only |
| Logout | Owned by Laravel; no client token store |
| Prohibited | localStorage/sessionStorage tokens, exposed session IDs |

See also: `DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md`.

## API endpoint contracts

Defined in `dashboard/lib/read-only/endpoint-contracts.ts` — 16 module groups, all **GET only**.

**Route inventory (Prompt 03 closure):**

| Group | Count | Routes |
|-------|------:|--------|
| Prompt 02 | 8 | session, overview, bookings (2), payments (2), customers (2) |
| Prompt 03 | 13 | suppliers (2), agents (2), pnrs (2), tickets (2), reports (5) |
| **Total** | **21** | All `GET\|HEAD` under `/api/dashboard/*` |

The prior “18 additional GET endpoints” figure was incorrect — Prompt 03 adds **13** routes (2+2+2+2+5), not 18.

**Implementation status:** Laravel routes live at `/api/dashboard/*`; Next.js adapters wired for session, overview, bookings, payments, customers (Prompt 02) and suppliers, agents, PNRs, tickets, reports (Prompt 03).

## Fixture/live boundary

| Rule | Enforcement |
|------|-------------|
| UI shows source | `FixtureDataNotice` / `LiveReadOnlyNotice` |
| Envelope includes `source` | `ReadOnlyResponseEnvelope.source` |
| Failed live ≠ fixture | `ReadOnlyServiceError` → error state |
| Tests assert no storage | `read-only-integration.foundation.spec.ts` |

## Visual integration gate

Append `?dataSourcePreview=<variant>` to any `/testdash` route:

`fixture`, `live`, `stale`, `unauthorized`, `forbidden`, `unavailable`, `error`, `metadata`

Component: `DataSourcePreviewGate` in `dashboard-shell.tsx`.

## Playwright smoke configuration (Prompt 03 closure)

`dashboard/playwright.config.ts` — **no weakening** from baseline:

| Setting | Value | Notes |
|---------|-------|-------|
| `retries` | `0` | Unchanged |
| `workers` | `1` | Unchanged |
| `timeout` | `90_000` | Unchanged |
| `webServer.command` | `npm run start:smoke` | Unchanged |
| `webServer.reuseExistingServer` | `false` | **Strict** — always starts fresh smoke server; no external-server bypass |
| `webServer.timeout` | `180_000` | Unchanged |

A temporary `PLAYWRIGHT_EXTERNAL_SERVER` bypass was **reverted** during closure — it could connect to stale or unrelated servers.

### Prompt 03 targeted Playwright inventory (17 specs, 442 tests)

| Spec | Tests |
|------|------:|
| `read-only-suppliers.smoke.spec.ts` | 14 |
| `read-only-agents.smoke.spec.ts` | 12 |
| `read-only-pnrs.smoke.spec.ts` | 11 |
| `read-only-tickets.smoke.spec.ts` | 11 |
| `read-only-reports.smoke.spec.ts` | 11 |
| `read-only-integration.foundation.spec.ts` | 21 |
| `visual-system.foundation.spec.ts` | 90 |
| `critical-regression.smoke.spec.ts` | 21 |
| `suppliers.smoke.spec.ts` | 37 |
| `agents.smoke.spec.ts` | 40 |
| `pnrs.smoke.spec.ts` | 45 |
| `tickets.smoke.spec.ts` | 46 |
| `reports-overview.smoke.spec.ts` | 32 |
| `reports-sales.smoke.spec.ts` | 13 |
| `reports-bookings.smoke.spec.ts` | 12 |
| `reports-payments.smoke.spec.ts` | 10 |
| `reports-operations.smoke.spec.ts` | 16 |
| **Total** | **442** |

High-risk repeat set: `read-only-high-risk.smoke.spec.ts` — **28 unique tests**; `28 × 2 = 56` executions with `--repeat-each=2 --retries=0`.

| Prompt | Scope |
|--------|-------|
| **01 (this)** | Contracts, abstraction, visual audit, foundation tests |
| **02 ✅** | Auth shell, overview, bookings, payments, customers — 8 Laravel GET endpoints + Next.js adapters |
| **03 ✅** | suppliers, agents, PNRs, tickets, reports — 13 Laravel GET endpoints + Next.js adapters |
| **04** | CMS, users, RBAC, settings, audit boundaries; final validation |

## Files added (Prompt 01)

| Path | Purpose |
|------|---------|
| `dashboard/types/read-only-integration.ts` | Core types |
| `dashboard/lib/read-only/*` | Mode, envelope, errors, service, contracts |
| `dashboard/components/ui/data-source-status.tsx` | Source/state UI |
| `dashboard/components/ui/loading-state.tsx` | Shared loading |
| `dashboard/components/dashboard/data-source-preview-gate.tsx` | Visual preview gate |
