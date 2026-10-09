# JP-DASH-PROD-04 Final Report

PR_NUMBER=62
PR_URL=https://github.com/haseebpytdev/jetpk/pull/62
MERGED_MAIN_SHA=50ae55c47161d211748ca2cc1204855a52442422
PRODUCTION_SHA=50ae55c47161d211748ca2cc1204855a52442422
PRODUCTION_DEPLOY_SHA=50ae55c47161d211748ca2cc1204855a52442422
DASHBOARD_BUILD_ID=G_-giY83K-tngzEQvf86p
PRODUCTION_DASHBOARD_BUILD_ID=G_-giY83K-tngzEQvf86p

FINAL_STATUS=FULL_PASS
HYDRATION_ERRORS=0
VISIBLE_ADMIN_PAGES=29
VISIBLE_ADMIN_PASS=29
VISIBLE_ADMIN_FAIL=0
CROSS_PORTAL_RBAC=PASS
API_CONNECTIONS_MODAL=PASS

QA_AGENCY=jetpk-production-qa
QA_ADMIN_AUTH=PASS
QA_STAFF_AUTH=PASS
QA_AGENT_AUTH=PASS
QA_AGENT_STAFF_AUTH=PASS
QA_CUSTOMER_AUTH=PASS

ADMIN_PAGES_TOTAL=29
ADMIN_PAGES_PASS=29
ADMIN_PAGES_FAIL=0

MOCK_DATA_IN_PRODUCTION=NO
PREVIEW_FALLBACK_IN_PRODUCTION=NO

REAL_SUPPLIER_CALLS=NO
REAL_TICKETS=NO
REAL_PAYMENTS=NO
REAL_CUSTOMER_MUTATIONS=NO

HYDRATION_PROD_04=PASS (0/10 admin home, bookings, users; see production-hydration-prod-04.json)
WRITE_BRIDGE=PASS (run-production-writes.mjs + gate-booking-note.mjs)
HASH_SOURCE_PARITY=PASS (deploy-sha.txt == .jetpk-runtime-sha == MERGED_MAIN_SHA)

## Deployment note (accurate)

The first protected deploy helper pass failed at `composer install` because
`/tmp/jetpk-deploy.sh` attempted `sudo -u pkjetp` while the SSH user is not in
sudoers. Staged dashboard/Laravel files from release `jetpk-20261008T212344Z` were
already copied to `/home/pkjetp/jetpk_app` before that failure.

Recovery (same authorized SHA, no redeploy of unrelated runtime):

- runtime ownership normalize + assert (`assert-runtime-ownership.sh`)
- `php artisan optimize:clear` as `pkjetp`
- `/tmp/jetpk-next-build.sh` (dashboard BUILD_ID `G_-giY83K-tngzEQvf86p`)
- `storage/app/deploy-sha.txt` and `.jetpk-runtime-sha` markers

This was **not** a clean first-pass deploy; it was a staged copy plus recovery
completion.

PRODUCTION_BACKUP=jetpk_app-20261008T212021Z (/home/pkjetp/backups/)

EVIDENCE_DIR=docs/evidence/jp-dashboard-production-cert-20261008
