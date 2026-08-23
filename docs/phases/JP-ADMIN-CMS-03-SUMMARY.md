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
Filled at pin time: `FINAL_ADMIN_CMS03_ENGINEERING_SHA`

## Final status
`JP_ADMIN_CMS03_PREDEPLOY` — STOP BEFORE DEPLOYMENT. `OWNER_RETEST_V3=RETEST_REQUIRED`.
