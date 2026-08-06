# JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP

Phase: **JP-FULLSTACK-01G** (route inventory parity)
Machine-readable: [JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json](./JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json)

## Counting methodology (authoritative)

| Rule | Value |
|------|-------|
| Source | Every `frontend/app/**/page.tsx` counted **once** |
| Excluded | `frontend/app/dev/**` only |
| Dynamic segments | Count once per filesystem route (`[slug]`, `[token]`, etc.) |
| Route groups | Omitted from public URLs (`(public)`, `(auth)`) |
| Redirect-only pages | **Included** in total; listed separately |
| Dashboard app | **Excluded** (`dashboard/` is a separate Next application) |
| CMS DB slugs | **Not** expanded into multiple filesystem routes |

## Production route count

| Classification | Count | Notes |
|---|---:|---|
| **Production `page.tsx` routes** | **82** | Authoritative filesystem inventory |
| Dev-only (excluded) | 1 | `/dev/jetpk-theme-lab` |
| Redirect-only | 3 | `/agent`, `/customer`, `/booking/payment` |
| Dynamic filesystem routes | 15 | Includes `[slug]`, `[token]`, `[reference]`, etc. |

**Prior stale references superseded:** 64 (theme audit), 67 (01C map), 76 (01 audit executive), 81 (connectivity denominator).

## Redirect-only routes

| Path | Target |
|------|--------|
| `/agent` | `/agent/dashboard` |
| `/customer` | `/customer/dashboard` |
| `/booking/payment` | `/booking/payment/manual` |

## Category totals (82 production routes)

| Category | Count |
|----------|------:|
| cms-public-content | 11 |
| checkout-booking | 18 |
| agent | 28 |
| customer | 12 |
| shared-auth | 9 |
| redirect-only | 3 |
| utility | 1 |

See JSON manifest for the complete per-route inventory with `app_router_path`, `category`, `redirect_only`, and `dynamic` flags.

## Verified exclusions

| Path | Status |
|------|--------|
| `/preview` | **404** — retired |
| `/booking/seats` | **404** — `seat_map_available=false` |
| `/dev/jetpk-theme-lab` | Dev-gated; excluded from production count |
| `/flights/search` | Compat redirect to `/#flight-search` (not a `page.tsx`) |

## Parity regression

`frontend/tests/regression/jp-fullstack-01g-route-inventory.test.mjs` enumerates the filesystem and asserts exact parity with this map and JSON manifest.
