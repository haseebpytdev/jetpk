# 00 — Baseline

```text
PUBLIC_BASELINE_CAPTURED_AT=2026-09-29T18:24:42+05:00
START_REMOTE_MAIN=3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492
START_LOCAL_HEAD_AI_BRANCH=b83099c937265a06b5cd4c8d56b55b8605d2f87e
RECOVERY_BRANCH=work/jetpk-dashboard-full-recovery-20260929
RECOVERY_WORKTREE=.recovery/work-dashboard-full-recovery-20260929
PUBLIC_BASELINE_SHA=3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492
DASHBOARD_BUILD_ID=N_-Qce57A2g7ncQMatYVi
PUBLIC_BUILD_ID=QfGNA8lxtL9hm6ceW3Rvi
LARAVEL_RELEASE_SHA=3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492
ADMIN_RENDERER=NEXT_DASHBOARD
STAFF_RENDERER=NEXT_DASHBOARD
AGENT_RENDERER=NEXT_PUBLIC
CUSTOMER_RENDERER=NEXT_PUBLIC
DASHBOARD_PM2_NOTE=online_port_3001_uptime_~7D_stale_vs_laravel
PUBLIC_PM2_NOTE=online_port_3010_build_2026-09-28
```

## HTTP matrix (pre-change)

| URL | Status |
|---|---|
| `/` | 200 |
| `/login` | 200 |
| `/groups` | 200 |
| `/api/public/content/config` | 200 |
| `/robots.txt` | 200 |
| `/sitemap.xml` | 200 |
| `/admin` | 302 → `/login` |
| `/admin/dashboard` | 200 Next (`/dashboard-next/_next`) |
| `/admin/api-settings` | 302 → `/login` (Blade route alive) |
| `/admin/settings/branding` | 302 → `/login` |
| `/admin/settings/homepage` | 302 → `/login` |
| `/admin/customer-queries` | 302 → `/login` |
| `/admin/seo` | 302 → `/login` |
| `/admin/cms-pages` | 302 → `/login` |
| `/agent` / `/customer` | 307 → `/login` (public Next) |

Checkpoint refs retained (not moved): `checkpoint/jetpakistan-app-release-20260921`, `backup/jetpakistan-post-recovery-20260921`, tag `jetpakistan-recovered-final-20260921`.
