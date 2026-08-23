# JP-ADMIN-CMS-03 — Admin CMS + Dashboard UX Closure Summary

## Phase name
JP-ADMIN-CMS-03

## Branch name
`phase/jp-admin-cms-03`

## Objective
Single Integrations control plane, compact collapsible Dashboard IA, Homepage CMS schema/media/editor parity with public rendering, and near-immediate public CMS propagation — engineering complete, stop before production deploy.

## Included scope
- Server-driven nav: remove API Connections + CMS parent; Integrations under Suppliers; Website children
- Collapsible sidebar groups with sessionStorage memory
- Featured deal / route / destination media pipeline + MEDIA SOURCE indicators
- Homepage builder split editor/preview + section header fields
- Page editor simplified section navigation
- Homepage fetch `cache: no-store` (≤10s propagation)
- Schema parity matrix + live round-trip protocol (not executed)
- Local Laravel + Playwright gates

## Excluded scope
- Production deployment
- Live CMS mutation on jetpakistan.pk
- Real AbhiPay credentials / PNR / booking / ticket / payment state

## Investigation findings
- `/api-connections` already redirected to Integrations; presenter still emitted API Connections
- Featured deals lacked image fields end-to-end
- Sidebar was a fully expanded flat list
- Public homepage used `revalidate: 120`

## Root causes
1. `BackOfficeCapabilitiesPresenter` System group listed both Integrations and API Connections
2. Featured deal display/presenter omitted media
3. Sidebar UI ignored collapsible group UX
4. Next fetch TTL left published CMS stale for up to 120s

## Exact files changed
See git diff on `phase/jp-admin-cms-03` (runtime + dashboard + frontend + tests + docs).

## Routes changed
- Compatibility: `/admin/dashboard/cms` → Homepage sections
- Legacy `/admin/dashboard/api-connections` retained → Integrations
- Laravel `/admin/api-settings` HTML continues → Integrations

## Database changes
None (MIGRATIONS=0)

## Backend changes
- Navigation IA refactor
- Featured deal asset keys + public presenter `image` / `image_alt` / `media_source`
- Destination CMS vs fallback media_source

## Frontend changes
- Compact collapsible sidebar
- Homepage builder split + card media
- Page editor split/sections
- Homepage content service no-store + tags

## Tests executed
- PHPUnit: JpAdminCms03*, ApiConnectionsHub, BackOfficeSession, DashboardNavigationOperational, Phase20B sidebar, HomepageDraftPublish, JetpkHomepageMedia
- Dashboard Playwright: `jp-admin-cms-03.spec.ts`, `jp-int-01-integrations.spec.ts` (one known dialog flake on Test Connection in int-01)
- Dashboard + frontend `tsc --noEmit`
- Dashboard + frontend `next build`

## Assertion counts
Focused PHPUnit batches: 23 + 17 assertions suites passed (see command logs).

## Screenshots
`dashboard/tmp/jp-admin-cms-03-visual/` (01–20 captured by Playwright when suite passes).

## Responsive / accessibility
- Desktop collapsible groups + chevron buttons with `aria-expanded`
- Mobile drawer accordion via Open navigation menu
- Keyboard: group toggles are buttons

## Known limitations
- Live production CMS round-trip deferred (protocol documented)
- Preview iframe requires Preview action for `jp_preview=1` session

## Risks
- Nav label renames may surprise staff training materials
- no-store increases homepage API hits (acceptable low volume)

## Rollback
Revert branch / redeploy prior SHA `8911d208be9b42330c2157e6cd3d4a288c643d94`.

## Commit SHA
Production base (pre-blocker-closure): `f129bc5eebebcf23c5eb7806506c2525ed392b0d`

## Live-blocker closure (engineering, stop before deploy)

Fixes reproducing production defects from the live round-trip:

1. **Integrations ApiResult unwrap** — `dashboard/services/operational-api.ts` flattens `{ ok, data, status }` so IntegrationsWorkspace reads `hub` / `permissions` / `integration` (13 providers + Settings + Test Connection contract).
2. **Featured deal publish fields** — `JetpkHomepageContentValidator::normalizeFeaturedDeals()` preserves `id`, editorial fields, and safe `image_asset_key` / `image_alt`; path-like asset keys rejected.
3. **CMS managed-page freshness** — managed/CMS/custom public fetches use `cache: "no-store"`; About/FAQ routes `force-dynamic`.
4. **Preview contract** — admin `beginPreview` mints a short-lived page-scoped HMAC `jp_preview_token` (plus `jp_preview=1`). Public Next forwards token via query/`X-JP-Preview-Token` and optional cookies. Laravel accepts token **or** admin session for draft read only; normal public stays published; no auth bypass / no publish required.

Prior eng slice: `eb5067e4` (Integrations unwrap + featured-deal fields + no-store). Preview-token eng: `49be311d`.

## Final status
`JP_ADMIN_CMS03_BLOCKER_CLOSURE_PREDEPLOY` — STOP BEFORE DEPLOYMENT. `OWNER_RETEST_V3=RETEST_REQUIRED`.

### Blocker-closure pins
- PRODUCTION_BASE_SHA=`f129bc5eebebcf23c5eb7806506c2525ed392b0d`
- FINAL_CMS03_BLOCKER_ENGINEERING_SHA=`49be311deb993b7b87644f27c75d883af574c997`
- Evidence (local, untracked): `tmp/jp-admin-cms-03-blocker-closure/`

## About Next-cache residual (engineering, stop before deploy)

Post live residual diagnostic: legacy api-connections redirect **PASS** (auth-probe false negative). About API/preview/draft **PASS**. Canonical `/about-us` HTML stale while query/API fresh → **NEXT_CACHE** (OLS unchanged).

Minimal eng `cbe445e35da33468834dcbf95aaf19b2eb3123ff`:
- `revalidate = 0` + `fetchCache = "force-no-store"` on About route (keep `force-dynamic`)
- managed-page SSR absolute `LARAVEL_URL` when set (dynamic env read)
- bare-URL regression test + local `next start` freshness harness (**0.367s**, query bust not required)

Predeploy note: `docs/jetpk/deployments/JETPAKISTAN-PK-JP-ADMIN-CMS-03-ABOUT-NEXT-CACHE-PREDEPLOY-20260824.md`

`OWNER_RETEST_V3=RETEST_REQUIRED` — STOP BEFORE DEPLOYMENT.
- Gates: Laravel JpAdminCms03 13 PASS; Playwright CMS-03 13 PASS; dashboard+frontend tsc/build PASS
- MIGRATIONS=0; production NOT deployed
