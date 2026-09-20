# Soft-nav reconcile vs `8793cc9f`

## Proven regression after 8793

| Commit | Change | Effect |
|--------|--------|--------|
| `4f1836c1` | Home anonymous ISR + root `loading.tsx` + LoginForm Suspense | Soft-nav regressed vs 8793 |
| `22a4e759` | About-us CMS Suspense wrap | Tip pack 5/10 (`mbGGmJZh1GgGZCIWsBdEV`) |

Best pack remains `8793cc9f` / `aEdZnf5JjqhJtZyrKe0dr` = **8/10**.

## Working-tree corrective actions (no history rewrite)

1. Restored `frontend/app/page.tsx` to 8793 behavior (await session + config; no anonymous shell / page Suspense).
2. Removed `frontend/app/loading.tsx` (did not exist at 8793).
3. Restored `frontend/app/(auth)/login/page.tsx` to 8793 (LoginForm not wrapped in Suspense fallback).
4. Restored `frontend/app/(public)/about-us/page.tsx` to 8793 (no Suspense wrap).
5. Kept 8793 Support/FAQ Suspense (part of the 8/10 pack).
6. Added homepage hero/below-fold Suspense split + request `cache()` dedupe to target `support_home` headroom without hiding SEO content.

## Still required before soft-nav PASS

- Commit + public-only deploy + N=20 soft-nav on **same** BUILD_ID
- Close `support_home` and `home_login` under 1500 with variance headroom
- Then same-SHA traveler/return/pair↔segmented cert

```text
SOFT_NAV_GATE=PENDING_DEPLOY_MEASURE
```
