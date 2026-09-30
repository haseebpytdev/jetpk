# Batch 1.5 — Global Live / Operational Mode Closure

```text
BRANCH=work/jetpk-dashboard-operational-recovery-20260930
LOCAL_BRANCH_HEAD=81e1629ae94d1aa863da6739909b5b3e5e6d2cae
REMOTE_BRANCH_HEAD=81e1629ae94d1aa863da6739909b5b3e5e6d2cae
PRODUCTION_DASHBOARD_SOURCE_SHA=81e1629ae94d1aa863da6739909b5b3e5e6d2cae
PRODUCTION_DASHBOARD_BUILD_ID=6e4cVHleZi3KQ0ouqNTmJ
PM2_LOADED_BUILD_ID=6e4cVHleZi3KQ0ouqNTmJ
BROWSER_ASSET_PREFIX=/dashboard-next/_next/static/
```

## Gates

| Gate | Result |
|---|---|
| DOR_001_CUSTOMERS | PASS (prior) |
| DOR_002_ADMIN_PROFILE | PASS |
| DOR_004_GLOBAL_OPERATIONAL_MODE | PASS |
| DOR_005_SESSION_BRIDGE | PASS |
| DOR_006_BUILD_PARITY | PASS (PM2 manifest + laravelLive in build; old phase copy absent) |
| PRODUCTION_LEGACY_READONLY_BANNERS | 0 |
| PRODUCTION_PREVIEW_USER_FALLBACKS | 0 |
| PRODUCTION_FIXTURE_NOTICES | 0 |
| ADMIN_PROFILE_SESSION | PASS |
| ADMIN_PROFILE_SAFE_WRITE_RELOAD | PASS |
| ADMIN_PROFILE_RESTORE | PASS |
| ADMIN_LIVE_MODE | PASS |
| STAFF_LIVE_MODE | PASS |
| AGENT_LIVE_MODE | PASS |
| CUSTOMER_LIVE_MODE | PASS |
| AGENT_STAFF_LIVE_MODE | PASS |
| CROSS_ROLE_PREVIEW_FALLBACKS | 0 |
| VISIBLE_UAT_UNEXPECTED_500 | 0 |
| BATCH_1 | PASS |
| BATCH_1_5_GLOBAL_LIVE_MODE | PASS |

## Evidence paths

- `storage/framework/dor-b15-visible-uat/`
- `storage/framework/dor-b15-role-canary/`
- `docs/evidence/dashboard-operational-recovery-20260930/LEGACY-PREVIEW-READONLY-AUDIT.md`

## Backup

- Full app backup before stage: `/home/pkjetp/backups/jetpk_app-20260930T170440Z.tar.gz`
- Dashboard source backups under `/home/pkjetp/backups/dashboard-b15-*`
