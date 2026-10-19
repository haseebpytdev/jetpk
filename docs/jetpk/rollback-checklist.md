# JetPakistan rollback checklist

**Authority companion:** `docs/jetpk/DEPLOYMENT-CONTEXT.md`, `docs/PRODUCTION_DEPLOYMENT_SAFETY.md`.

Use when a deploy causes 500s, broken search/booking, dashboard failure, or branding regression.

## Current production model (authoritative)

| Concern | Current architecture |
|---------|----------------------|
| Runtime base | `/home/pkjetp/jetpk_app` with **patch-marker** overlays (not every deploy is a full-tree SHA replace) |
| Protected main (engineering) | Verify with `git rev-parse jetpk/main` before claiming a rollback target |
| Public frontend | Next build under app `frontend/`; PM2 `jetpk-public-frontend` |
| Dashboard | Next build; PM2 `jetpk-dashboard` |
| Laravel | Scoped-file deploys preferred; `composer dump-autoload` + `php artisan optimize:clear` after PHP changes |
| Database | Prefer forward-fix migrations; no destructive rollback of financial rows |
| Supplier gates | Sabre live PNR/ticketing remain go-live gated; **Airblue external calls stay disabled** (`AIRBLUE_EXTERNAL_CALLS_ENABLED=false`) until launch window |
| Guest tokens | Legacy guest-token routes may remain under sanitized telemetry observation (`guest.legacy_token.usage`) — do not remove during rollback unless that was the broken change |
| Web server logs | OpenLiteSpeed access/error logs on canonical host |
| Backups | Prefer dated paths under `/home/pkjetp/backups/` from the deploy that introduced the change |

Do **not** treat historical SHAs in older evidence packs as the current runtime unless labeled and verified against the live patch marker / release note.

## 1. Stop further uploads

Do not upload additional files until root cause is identified. Preserve the last known-good backup path from the failed deploy evidence.

## 2. Restore files

| Source | Action |
|--------|--------|
| Pre-deploy backup | Restore the exact file set from the deploy backup folder |
| Git | Checkout prior version of **changed** files only (scoped) |
| Patch marker | Reconcile marker to the restored overlay set |
| Frontend / dashboard | Restore prior build artifact or rebuild the known-good commit; restart PM2 processes |
| `deployment.json` / release notes | Record `last_deployed_at` and rollback target for audit |

Priority restore order:

1. Controllers / routes / middleware (if request path broken)
2. Policies / payment / booking services (if auth or money path broken)
3. Public Next / dashboard build (if UI 5xx or blank)
4. Layout / theme CSS only if branding regression
5. Config caches last (`optimize:clear` then selective cache)

## 3. Server cache clear

```bash
cd /home/pkjetp/jetpk_app
composer dump-autoload
php artisan optimize:clear
php artisan route:list
```

When Blade/views changed:

```bash
php artisan view:clear
php artisan view:cache
```

## 4. Database rollback (if migrations ran)

- Prefer restore DB dump from the **pre-deploy backup** for that release
- Or run `php artisan migrate:rollback` **only** if the migration was isolated and proven non-destructive to ledger/wallet/payment rows
- Never rewrite real production financial rows to “fix” QA hygiene

## 5. Supplier / payment safety during rollback

- Keep `AIRBLUE_EXTERNAL_CALLS_ENABLED=false` unless an explicit launch-window change is being reverted
- Do not exercise live Sabre PNR create, ticketing, void, or AbhiPay charges to “verify” rollback
- Confirm feature gates match the restored code

## 6. Asset / build version

If CSS/JS or Next build rollback: restore prior assets **and** prior cache-bust / build id expectations; restart PM2.

## 7. Verification (canonical host only)

Canonical: `https://jetpakistan.pk` (see DEPLOYMENT-CONTEXT forbidden hosts).

- [ ] `GET /up` healthy
- [ ] Homepage `/` loads
- [ ] `/login` loads
- [ ] `/lookup-booking` loads
- [ ] `/agent` and `/admin` reach auth gates as expected
- [ ] No new `production.ERROR` flood in `storage/logs/laravel.log`
- [ ] Access logging still writing (if that was in scope)
- [ ] Guest legacy telemetry still present if observation is active
- [ ] `php artisan ota:route-page-health-audit --all` → `fail=0` when runnable

## 8. Document

- Record rollback commit/tag, backup path, patch marker before/after
- Update the active ops evidence note for the incident
- Reset deploy timestamps only when redeploying the fixed set

## Emergency disable (parity / preview)

If a preview-parity flag causes issues:

```env
CLIENT_ROUTE_PARITY_ENABLED=false
```

Then `php artisan optimize:clear`. Root routes unchanged.

## Related docs

- `docs/jetpk/DEPLOYMENT-CONTEXT.md`
- `docs/PRODUCTION_DEPLOYMENT_SAFETY.md`
- `docs/evidence/jp-ops-readiness-01/` (incident evidence; historical SHAs labeled as such)
