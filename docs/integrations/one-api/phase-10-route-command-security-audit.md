# Phase 10 — Route and command security audit

Re-audit date: 2026-07-23 (post Phase 9 acceptance closure).

## HTTP routes (`routes/web.php`)

| Method | URI | Controller | Middleware | AuthZ | Mutation | Sensitivity |
|--------|-----|------------|------------|-------|----------|-------------|
| GET | `/booking/one-api/catalog` | `OneApiCheckoutController@catalog` | web, auth | Workflow guard + connection ownership | Read | Opaque selection IDs only |
| POST | `/booking/one-api/final-price` | `OneApiCheckoutController@saveSelections` | web, auth, throttle | Same + CSRF | **Mutation** | No raw XML/TID/RPH/cookies in JSON |
| GET | `/booking/one-api/extras` | `OneApiCheckoutController@showExtras` | web, auth | Session + ownership | Read | No supplier secrets in HTML |
| POST | `/booking/one-api/selections` | `OneApiCheckoutController@saveSelections` | web, auth, throttle | Same | **Mutation** | Server-side price only |

**Proved by tests:** `OneApiWorkflowOwnershipFeatureTest`, `OneApiSecurityPhase6Test`, `OneApiCheckoutBrowserContractTest` (no `TID_` in rendered extras; tampered selection IDs rejected).

**Controls:** no GET booking mutation; unauthenticated denied; cross-user/agency denied; `fixture_path` not accepted on HTTP; transport implementation not selectable via HTTP.

## Admin (`routes/admin.php`)

Supplier connections under `admin.api-settings.*` — **platform admin only** (`SupplierConnectionPolicy`). One API ADM-002–004 covered by `OneApiSupplierConnectionAuthorizationTest`. Test Connection performs readiness check only (no price/book/read/modify).

## CLI (One API)

| Command | Fixture default | Live gates |
|---------|-----------------|------------|
| `ota:one-api-test-matrix` | `--mode=fixture` | Live requires explicit flags + connection |
| `ota:one-api-fixture-test` | Fixture scope | Rejects arbitrary paths without scope |
| `ota:one-api-search-probe` | Readiness-oriented | Live search flag required |
| `ota:one-api-price-probe` | SOAP blocked without config | Live SOAP flag required |
| `ota:one-api-read-reservation` | Fixture/diagnostic | Not exposed on public HTTP |
| `ota:one-api-reconcile-booking` | Local reconciliation | Confirmation for mutations |
| `ota:one-api-connection-audit` | Read-only audit | No booking |
| Inventory/matrix commands | Generate manifests only | No supplier calls |

Secrets: `SensitiveDataRedactor` on auth logs; tokens not persisted on connections or workflow serialization (`OneApiAuthenticationMatrixTest`).

## Verdict

**PASS** for Phase 10 evidence scope — no new public leakage paths introduced since Phase 8 audit.
