# JP dashboard production certification (browser)

Read-only and QA-scoped **production** runners for `https://jetpakistan.pk` only. Do not
embed certified deploy SHAs in these scripts.

## Required runtime inputs

| Variable | Purpose |
|----------|---------|
| `JP_PRODUCTION_SHA` | Full 40-character Git SHA of the **certified** production deploy (fail-closed if missing/invalid). |
| `JP_DASHBOARD_BUILD_ID` | Dashboard Next.js `BUILD_ID` from production (required by `run-production-cert.mjs`). |

Optional: `JP_SSH_KEY` — SSH key path for deploy-marker reads (default `~/.ssh/jetpk_contabo_2026_v2`).

## Auth storage (local only, never commit)

Build Playwright storage states after OTP is available:

```bash
cd dashboard
node scripts/jp-dashboard-prod-cert/build-auth-states.mjs
```

Outputs under repo `tmp/jp-dash-03-*-storage-state.json`.

## Example invocation (JP-DASH-PROD-04)

```bash
cd dashboard
export JP_PRODUCTION_SHA=50ae55c47161d211748ca2cc1204855a52442422
export JP_DASHBOARD_BUILD_ID=G_-giY83K-tngzEQvf86p

node scripts/jp-dashboard-prod-cert/run-production-hydration-prod-04.mjs
node scripts/jp-dashboard-prod-cert/run-production-cert.mjs
node scripts/jp-dashboard-prod-cert/run-production-writes.mjs
node scripts/jp-dashboard-prod-cert/gate-booking-note.mjs
```

Evidence is written to `docs/evidence/jp-dashboard-production-cert-20261008/`.

## Local regression (no production)

```bash
cd dashboard
node --test scripts/jp-dashboard-prod-cert/production-cert-env.test.mjs
node tests/regression/jp-dashboard-prod-cert-no-hardcoded-sha.test.mjs
```
