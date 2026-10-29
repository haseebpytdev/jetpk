# JP-UI-PERF-STABILITY-01A — Production route responsive hotfix closure

```text
PR_NUMBER=140
CANONICAL_MAIN_SHA=dddf76429b5bfc7edfe15f1319d97fe8434e2383
HOTFIX_SHA=4e6e3acc2c48d431c4fb622eb9c462cd0588e05c
HOTFIX_PARENT_SHA=321be5673036912868f2846a1f51322854bd157e
FINAL_APPLICATION_SHA=4e6e3acc2c48d431c4fb622eb9c462cd0588e05c
FINAL_PUBLIC_FRONTEND_SHA=43f5a3beb9a8e88567cf49bef76853f49b55e0c6
```

Production is **not** stamped as full canonical main `dddf7642`.

## Deploy path

- Preimage parity PASS vs parent `321be567` presenter.
- Stage dry-run: `MANIFEST_UPLOADABLE_COUNT=1`, deletions=0.
- Stage actual: `RELEASE_STAGED_AT=/home/pkjetp/jetpk_git/tmp/releases/jetpk-20261029T161415Z`.
- `jetpk-deploy.sh` not present on host; used previously proven allowlisted `install` of the single staged presenter.
- Live blob sha256 `e86c6207…` matches Git hotfix object.
- Marker written to `4e6e3acc…`; frontend marker unchanged; no FE rebuild/restart; dashboard not restarted.
- Backup: `/home/pkjetp/backups/pr140-route-hotfix-pre-20261029T161151Z`

## Recertification

- `revalidateHomepage` → ok (public-content + seo endpoints 200).
- API `/api/public/content/homepage`: `source=cms`, **API_ROUTE_RESPONSIVE_COUNT=4**
  - `route_seed-khi-dxb` fingerprint `2f5f8ced8f55a2d6`
  - `route_seed-lhe-jed` fingerprint `269ea6178cd31fa6`
- Playwright (`15-live-network-evidence.json`): both former route originals not transferred; derivatives selected; unique image transfer @1440 ≈ 443 KB (PNG only branding).

## Non-actions

```text
MEDIA_BACKFILL_RERUN=NO
MEDIA_METADATA_MUTATED=NO
ORIGINAL_FILES_CHANGED=NO
FRONTEND_REBUILD=NO
PUBLIC_FRONTEND_RESTART=NO
DASHBOARD_RESTARTED=NO
```
