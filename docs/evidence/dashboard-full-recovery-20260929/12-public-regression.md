# 12 — Public regression (post Batch A deploy)

```text
PUBLIC_BUILD_ID=QfGNA8lxtL9hm6ceW3Rvi   # unchanged (public Next not rebuilt)
DASHBOARD_BUILD_ID=gwLT6IakIh_aK1-szGgux
DEPLOY_SHA=3dc81c07f11376f14b9a42e60167d74fb24dce2f
MERGED_MAIN=0878e727ee447fc21413ce7c17f8179d7fdc3590
```

| Check | Result |
|---|---|
| `/` 200 | PASS |
| `/login` 200 | PASS |
| `/groups` 200 | PASS |
| `/api/public/content/config` 200 | PASS |
| Homepage HTML ~78KB | PASS |
| Header/Hero/Flight/Groups/Trending/Destinations/Featured/Why/Support/Ask/footer keywords | PASS |
| Public PM2 restarted? | NO (intentional) |

```text
PUBLIC_GOLDEN_REGRESSIONS=0
MISSING_APPROVED_HOMEPAGE_SECTIONS=0
PUBLIC_404_500=0
CMS_FINAL_PUBLIC_DIFF=0
```
