# 13 — Production deploy (Batch A)

```text
PREDEPLOY_REMOTE_MAIN=3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492
PREDEPLOY_DASHBOARD_BUILD_ID=N_-Qce57A2g7ncQMatYVi
ROLLBACK_BACKUP=/home/pkjetp/backups/dashboard-recovery-batch-a-20260929-192851
AUTHORIZED_ENGINEERING_SHA=3dc81c07f11376f14b9a42e60167d74fb24dce2f
PR=https://github.com/haseebpytdev/jetpk/pull/55
MERGED_MAIN=0878e727ee447fc21413ce7c17f8179d7fdc3590

PRODUCTION_DEPLOY_SHA_MARKER=3dc81c07f11376f14b9a42e60167d74fb24dce2f
DASHBOARD_BUILD_ID=gwLT6IakIh_aK1-szGgux
PUBLIC_BUILD_ID=QfGNA8lxtL9hm6ceW3Rvi
```

## Steps performed
1. Backup presenter + sidebar + prior BUILD_ID under `/home/pkjetp/backups/dashboard-recovery-batch-a-20260929-192851`
2. SCP Batch A PHP + dashboard source files
3. `php artisan optimize:clear`
4. `npm run build:production` in `/home/pkjetp/jetpk_app/dashboard` (success before restart)
5. `pm2 restart jetpk-dashboard`
6. Write `storage/app/deploy-sha.txt` = `3dc81c07…`
7. Squash-merge PR #55 → main `0878e727`

## Commercial mutations
```text
REAL_TICKETS_ISSUED=0
REAL_PAYMENTS_TRIGGERED=0
REAL_SUPPLIER_BOOKINGS_CREATED=0
REAL_PNRS_MUTATED=0
REAL_CANCELLATIONS=0
REAL_REFUNDS=0
PRODUCTION_BALANCE_MUTATIONS=0
```
