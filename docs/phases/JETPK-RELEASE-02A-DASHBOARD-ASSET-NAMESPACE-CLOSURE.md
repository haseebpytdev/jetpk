# JETPK-RELEASE-02A — Dashboard Asset Namespace Closure

**Phase name:** JETPK-RELEASE-02A-DASHBOARD-ASSET-NAMESPACE
**Branch:** `phase/jetpk-release-02a-dashboard-asset-namespace`
**Starting SHA:** `f311d07b183c38c0217b5f3132b88a1e4b1fd752` (main)
**Objective:** Isolate dashboard Next static assets from public `/_next` namespace

---

## Included scope

- Dashboard `assetPrefix` via `DASHBOARD_ASSET_PREFIX`
- Asset namespace contract test (production build)
- Local two-Next proof script
- Local reverse-proxy simulation script
- Operations documentation

## Excluded scope

- Production deploy / SSH / LiteSpeed edits
- Public frontend changes
- Laravel / Blade changes
- `basePath` or route relocation
- Proxy cutover execution

---

## Investigation findings

| Item | Finding |
|------|---------|
| Next.js version | 15.5.21 (package `^15.1.9`) |
| Existing `assetPrefix` | Not configured |
| Existing `basePath` | Not configured (explicitly forbidden for routes) |
| `next/image` | Not used in dashboard |
| `next/font` | Used in `app/layout.tsx` — compiles to prefixed static CSS |
| `dashboard/public/` | Empty / no root-relative public assets |
| Middleware | None |
| Laravel fetches | Root-relative in `lib/api/` and `lib/read-only/` |

---

## Root cause

Two independent Next.js production builds cannot share bare `/_next/*` behind a reverse proxy when `BUILD_ID` values differ. Dashboard-specific chunks 404 or return wrong content from public Next.

---

## Exact files changed

- `dashboard/lib/dashboard-asset-prefix.ts` (new)
- `dashboard/next.config.ts`
- `dashboard/.env.example`
- `dashboard/package.json`
- `dashboard/playwright.release-02a.config.ts` (new)
- `dashboard/tests/release-02a-asset-namespace.spec.ts` (new)
- `dashboard/scripts/run-release-02a-asset-namespace-test.mjs` (new)
- `dashboard/scripts/local-two-next-proof.mjs` (new)
- `dashboard/scripts/local-proxy-simulation.mjs` (new)
- `docs/operations/JETPK-RELEASE-02A-DASHBOARD-ASSET-NAMESPACE.md` (new)
- `docs/phases/JETPK-RELEASE-02A-DASHBOARD-ASSET-NAMESPACE-CLOSURE.md` (this file)

---

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `DASHBOARD_ASSET_PREFIX=/dashboard-next npm run build` | PASS |
| `npm run test:release-02a-asset-namespace` | See stop-gate report |
| `npm run proof:release-02a-two-next` | See stop-gate report |
| `npm run proof:release-02a-proxy-sim` | See stop-gate report |

---

## Known limitations

- `dashboard/public/` assets (if added later) remain root-relative — LiteSpeed must route separately
- Production LiteSpeed rules not applied in this phase
- Dashboard PM2 restart + rebuild required at deploy time

---

## Risks

- Missing `DASHBOARD_ASSET_PREFIX` at dashboard build time reintroduces collision
- LiteSpeed must route `/dashboard-next/_next/*` to dashboard port before cutover completes

---

## Rollback instructions

Remove `DASHBOARD_ASSET_PREFIX`, rebuild dashboard, restart PM2, revert LiteSpeed prefix context.

---

## Final status

Pending stop-gate review — **do not merge** until approved.
