# 03 — Runtime routing

```text
browser /admin/dashboard
→ LiteSpeed
→ Laravel BackOfficeDashboardController (next_proxy enabled)
→ jetpk-dashboard PM2 :3001
→ Next app (assetPrefix=/dashboard-next)
→ GET /api/dashboard/* (read-only)
→ session navigation from BackOfficeCapabilitiesPresenter
→ (gap) no laravel-target links to /admin/api-settings, branding, SEO, CMS, etc.
```

```text
browser /admin/api-settings (and other Blade siblings)
→ Laravel admin routes (NOT under /admin/dashboard/* catch-all)
→ Blade Admin controllers/views
→ (alive on prod; requires auth; orphaned from Next sidebar)
```

```text
ADMIN_RENDERER=NEXT_DASHBOARD
STAFF_RENDERER=NEXT_DASHBOARD
CUSTOMER_RENDERER=NEXT_PUBLIC
AGENT_RENDERER=NEXT_PUBLIC
DASHBOARD_BUILD_ID=N_-Qce57A2g7ncQMatYVi (stale)
DASHBOARD_SOURCE_SHA_EXPECTED=3de07cdb (Laravel marker matches; dashboard build does not)
```
