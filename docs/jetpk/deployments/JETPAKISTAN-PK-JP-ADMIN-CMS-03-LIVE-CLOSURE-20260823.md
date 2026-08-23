# JetPakistan — JP-ADMIN-CMS-03 LIVE Closure

Date: 2026-08-23  
Phase branch: `phase/jp-admin-cms-03`  
Owner retest: **RETEST_REQUIRED** (do not mark PASS)

## Pins

| Item | Value |
|------|-------|
| Previous runtime | `8911d208be9b42330c2157e6cd3d4a288c643d94` |
| Deployed runtime | `f129bc5eebebcf23c5eb7806506c2525ed392b0d` |
| Predeploy docs SHA | `19ededc37956c9978103f7e724c3d816fa88a7cb` |
| Runtime files | 14 |
| Migrations | 0 |
| Backup ID | `jp-admin-cms-03-20260823T112729Z` |
| Old public build | `ARSJVIridIInsI-qoq0kd` |
| New public build | `eAw2MigVHAncoWUTjti72` |
| Dashboard build | `JQzer4EvvZulc17k7yjD1` |
| Rollback used | NO |

## Deployment

- Staged exact Git object `f129bc5e` only (14 runtime paths; no tests/docs/tmp).
- Built as `pkjetp` (`npm ci` + `npm run build` for public + dashboard).
- Post-activate: `LIVE_SOURCE_DRIFT=0` (deploy-time hash compare), `MIGRATIONS_PENDING=0`.
- OLS (`/usr/local/lsws/conf/httpd_config.conf`) SHA256 matches expected → `OLS_HASH=PASS`.
- OTP: `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` preserved.
- Sabre gate env keys still present (no cancellation-gate edits).

## Sidebar (LIVE UI)

Authenticated Platform Admin (`jp-dash-03-qa-admin@jetpakistan.pk`):

- Compact groups: Overview / Operations / Customers / Finance / Suppliers / Website / Communications / Reporting / Administration / System → **PASS**
- `VISIBLE_API_CONNECTIONS_NAV=0`
- `CMS_REDUNDANT_PARENT_VISIBLE=0`
- Integrations under Suppliers → **PASS**
- Mobile drawer → **PASS**
- Screenshots: `tmp/jp-admin-cms-03-live/01-sidebar-compact-live.png` (+ homepage/pages CMS shots)

## Integrations authority

| Gate | Result |
|------|--------|
| Integrations HTTP | 200 |
| Legacy `/admin/dashboard/api-connections` → `/integrations` | **PASS** (authenticated) |
| API hub total providers | 13 (Flights/Payments/etc.) |
| AbhiPay in API registry | **PASS** |
| UI provider cards rendered | **BLOCKED** |
| Sabre Test Connection (API, readiness-only) | **PASS** (`ok`, message `connected`) |
| Commercial side effects | 0 |

### UI cards blocker (honest)

Live dashboard Integrations page shows metrics `0` / empty cards even though `/admin/integrations?format=json` returns `hub.metrics.total=13`.

Root cause (pre-existing live-mode defect, not introduced by the 14-file CMS-03 runtime intent beyond copy):  
`integrations-workspace.tsx` calls `setHub(result.hub)` but `laravelRequest` returns `ApiResult` as `{ ok, data, status }`, so the hub payload is at **`result.data.hub`**.

AbhiPay management evidence therefore taken from authenticated JSON API (no credentials entered, no Test Payment executed):

- `ABHIPAY_CONFIGURED=NO`
- `ABHIPAY_CHECKOUT_AVAILABLE=NO`
- `ABHIPAY_EXTERNAL_CONNECTION=OWNER_CREDENTIALS_REQUIRED`

## Homepage CMS LIVE

### Text path — PASS

- Draft save + draft isolation: **PASS**
- Publish text marker visible on public API: **PASS**
- Propagation: **~0.788s**
- Restore exact subtitle: **PASS**
- Final UAT text residue: **0**

### Featured-deal media path — BLOCKED

`JetpkHomepageContentValidator::normalizeFeaturedDeals()` (present in deployed SHA) drops `id`, `title`, `badge`, `description`, `image_asset_key`, `image_alt` on **publish**.

Consequence:

- Draft can hold media keys; publish strips them → public `media_source` stays `none`.
- Predeploy DB backup featured deals already lacked editorial title fields (same stripped shape).
- Incident restore from backup `jp-admin-cms-03-20260823T112729Z` re-applied predeploy homepage JSON (validator bypass for restore only).
- Synthetic UAT media assets cleaned when created.

**Follow-up engineering required** (new SHA): preserve featured-deal media/editorial fields in `normalizeFeaturedDeals` (parity with routes/destinations). Until then, Featured Deal CMS media publish cannot PASS.

Homepage CMS editor UI (split Editor/Preview, Hero/Routes/Destinations/Featured media controls) verified live: **PASS** (screenshots 05–07, 11–12).

## Existing page CMS (About)

- About is ClientPageSetting-backed (`page_key=about`) and public route `/about-us` uses `PublicPageService.getAboutPage()` → managed page `about`.
- Draft save: **PASS**
- Public API `/api/public/content/pages/about` showed UAT marker after publish: **PASS**
- Public HTML `/about-us` within ~65s: **BLOCKED_CACHE** (Next `revalidate: 60` / CDN stale HTML)
- Restore: **PASS**
- Final public residue `PAGE-CMS-UAT-20260823`: **0**

## Security / logs / residue

- Public scan for UAT markers + local origins: **0 hits**
- Secret exposure in evidence package: **0** (passwords not logged; QA admin password was ephemerally rotated for live login — owner should re-set privately if needed)
- Log window contains historical `SQLSTATE`/`TypeError` counts; **no** `CMS-UAT-20260823` / `PAGE-CMS-UAT-20260823` residue in sampled logs
- Commercial side effects: **0**
- Rollback runtime: **not used**

## Known blockers for Owner V3

1. Featured Deal CMS media publish blocked by validator field stripping.
2. Integrations live UI cards empty due to `result.data.hub` mapping bug (API healthy).
3. About public HTML cache lag vs API (API parity OK).
4. AbhiPay credentials still owner-private; no Test Payment run.

## NEXT

Owner enters AbhiPay credentials privately through Admin Integrations (after UI card fix or via API-backed UI once patched).  
Then run AbhiPay Test Connection and, if Test environment is available, the controlled PKR 1.00 diagnostic payment before final Owner V3 closure.

`OWNER_RETEST_V3_STATE=RETEST_REQUIRED`
