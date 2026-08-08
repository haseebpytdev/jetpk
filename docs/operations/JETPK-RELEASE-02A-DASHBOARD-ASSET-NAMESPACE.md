# JETPK-RELEASE-02A — Dashboard Static Asset Namespace

**Phase:** Release-02A
**Status:** Engineering complete — pending commit review
**Branch:** `phase/jetpk-release-02a-dashboard-asset-namespace`

---

## Problem (verified production fact)

Public Next (`127.0.0.1:3010`) and Dashboard Next (`127.0.0.1:3001`) produce **different `BUILD_ID` values** but both emitted static assets under the **same bare** `/_next/*` namespace.

Server-side proof during Release-02 cutover prep:

| Observation | Result |
|-------------|--------|
| Dashboard HTML asset references | 11 `/_next` assets |
| Dashboard-only assets | 8 of 11 |
| Cross-app collision | Dashboard assets returned **404** or **different content** when requested from public Next |
| Dashboard-specific prefix | **None** before this phase |

**Conclusion:** LiteSpeed cannot safely route bare `/_next/*` to only one Next app. Shared bare `/_next` routing defaults to public Next and breaks dashboard hydration.

---

## Solution

Configure Dashboard Next **`assetPrefix`** via server/build env:

```bash
DASHBOARD_ASSET_PREFIX=/dashboard-next
```

Dashboard static assets emit as:

```
/dashboard-next/_next/static/...
/dashboard-next/_next/image?...   (if next/image used in future)
```

**Page routes unchanged:**

- `/admin/dashboard`
- `/admin/dashboard/...`
- `/staff/dashboard`
- `/staff/dashboard/...`

**Public Next unchanged:** continues to own bare `/_next/*`.

---

## Why `assetPrefix` (not `basePath`)

| Option | Page routes | Static assets | Selected |
|--------|-------------|---------------|----------|
| `basePath` | Would move to `/dashboard-next/admin/...` | Prefixed | **Rejected** — violates route invariants |
| `assetPrefix` | Unchanged at `/admin/dashboard` | Prefixed under `/dashboard-next/_next/*` | **Selected** |

`basePath` is explicitly documented as disallowed in `docs/dashboard/ADMIN-STAFF-PRODUCTION-ROUTING.md`.

---

## Implementation

| File | Change |
|------|--------|
| `dashboard/lib/dashboard-asset-prefix.ts` | Normalize/read `DASHBOARD_ASSET_PREFIX` |
| `dashboard/next.config.ts` | Apply `assetPrefix` when env set |
| `dashboard/.env.example` | Document default `/dashboard-next` |
| `dashboard/tests/release-02a-asset-namespace.spec.ts` | Production-build contract |
| `dashboard/scripts/run-release-02a-asset-namespace-test.mjs` | Build + test runner |
| `dashboard/scripts/local-two-next-proof.mjs` | Two-process namespace proof |
| `dashboard/scripts/local-proxy-simulation.mjs` | Local routing simulation |

**Next.js version:** `^15.1.9` (resolved `15.5.21` at build time)

---

## Resource classification

| Class | Dashboard handling | Release-02A change |
|-------|-------------------|-------------------|
| A. Next build static (`/_next/static`) | `assetPrefix` applied | **Yes — prefixed** |
| B. `next/image` optimizer | Would use prefixed `/_next/image` | **N/A** — dashboard does not use `next/image` |
| C. `dashboard/public/` assets | Root-relative, unaffected by `assetPrefix` | **No change** |
| D. External assets | Unchanged | **No change** |
| E. Laravel-owned assets | Root-relative `/api`, `/admin`, `/staff` fetches | **No change** |

**Fonts:** `next/font/google` in `app/layout.tsx` — compiled into CSS under prefixed `/_next/static`.

---

## Laravel / API invariants (unchanged)

Dashboard runtime continues root-relative Laravel contracts:

- `fetch("/api/...")`
- `fetch("/admin/...")`
- `fetch("/staff/...")`
- Auth, payments, webhooks — **not** prefixed

`DASHBOARD_ASSET_PREFIX` is **not** a `NEXT_PUBLIC_*` variable.

---

## Future LiteSpeed routing contract (document only)

```
/dashboard-next/_next/*     → Dashboard Next :3001
/admin/dashboard*           → Dashboard Next :3001
/staff/dashboard*           → Dashboard Next :3001
/_next/*                    → Public Next :3010
Public page routes          → Public Next :3010
Laravel operational paths   → Laravel / LSAPI
```

**Do not** use Referer, cookie, or User-Agent routing for assets.

---

## Production deployment (later — not part of this phase)

1. Set on server before dashboard build:
   ```bash
   export DASHBOARD_ASSET_PREFIX=/dashboard-next
   ```
2. Rebuild dashboard: `cd dashboard && npm ci && npm run build`
3. Restart PM2: `pm2 restart jetpk-dashboard`
4. Apply LiteSpeed External App + Context rules (operator)
5. Verify `/admin/dashboard` HTML references `/dashboard-next/_next/*`
6. Verify public `/_next/*` still serves public Next only

### SFTP / file upload list (dashboard only)

- `dashboard/lib/dashboard-asset-prefix.ts`
- `dashboard/next.config.ts`
- `dashboard/.env.example` (reference)
- Rebuilt `dashboard/.next/` (build on server preferred per Release-02 D-11)

### Rollback

1. Remove `DASHBOARD_ASSET_PREFIX` from dashboard build env
2. Rebuild dashboard without prefix
3. Restart `jetpk-dashboard` PM2 process
4. Revert LiteSpeed asset-prefix context rules

---

## Production status

**Production untouched** during Release-02A engineering. No SSH, deploy, or env mutation on `185.215.166.176`.
