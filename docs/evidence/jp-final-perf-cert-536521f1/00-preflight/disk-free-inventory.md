# Infra disk free inventory — 2026-09-19

Purpose: production disk at **95%** amplifies soft-nav/RSC cold tails. Free proven-stale **backup archives only** (not app source retirement).

## Keep (recent rollback/backup candidates)

- `/home/pkjetp/backups/jetpk_app-20260919T133533Z.tar.gz`
- `/home/pkjetp/backups/jetpk_app-20260918T075549Z.tar.gz`
- `/home/pkjetp/backups/jetpk_app-20260918T051142Z.tar.gz`
- `/home/pkjetp/backups/jetpk_app-20260917T105102Z.tar.gz`
- `/home/pkjetp/backups/jetpk_app-20260917T081645Z.tar.gz`
- Live: `/home/pkjetp/jetpk_app` + `.env.production.local`
- Rollback SHA stamp: keep whatever `.jetpk-rollback-sha` points at

## Delete (stale full-app tarballs Sep 5–16 only)

Pattern: `/home/pkjetp/backups/jetpk_app-2026090[5-9]*.tar.gz` and `/home/pkjetp/backups/jetpk_app-2026091[0-6]*.tar.gz`

Also safe temp tree if unused:
- `/home/pkjetp/tmp/jetpk_repo_homepage_ssr_fix` (~545M) — temp clone, not live runtime

## Not deleted this pass

- `jetpk-seo-phase2-*` dirs (may still hold evidence; defer to retirement inventory)
- `jp-perf-final-*` dirs
- Active `frontend/.env.production.local`
- `storage` / database / uploads

## Expected free

~15–25 GB from Sep 5–16 tarballs alone.
