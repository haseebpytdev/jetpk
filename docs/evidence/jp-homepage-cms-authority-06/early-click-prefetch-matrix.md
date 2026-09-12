# Early-click prefetch matrix

Captured: 2026-09-10T21:01:09.208Z

| Route | Delay ms | P50 usable | P95 usable | Prefetch before click | Reuse YES rate |
|-------|----------|------------|------------|----------------------|----------------|
| home_to_login | 0 | 864 | 1996 | 0% | 0% |
| home_to_login | 100 | 787 | 1072 | 0% | 0% |
| home_to_login | 250 | 799 | 1423 | 20% | 0% |
| home_to_login | 500 | 651 | 1327 | 30% | 10% |
| home_to_login | 750 | 371 | 489 | 70% | 30% |
| home_to_login | 1000 | 272 | 585 | 90% | 80% |
| home_to_login | 1500 | 324 | 1704 | 100% | 60% |
| home_to_login | 2000 | 305 | 708 | 90% | 80% |
| home_to_login | 3000 | 294 | 553 | 100% | 20% |
| home_to_groups | 0 | 931 | 1372 | 0% | 0% |
| home_to_groups | 100 | 658 | 1057 | 0% | 0% |
| home_to_groups | 250 | 600 | 832 | 0% | 0% |
| home_to_groups | 500 | 403 | 696 | 0% | 0% |
| home_to_groups | 750 | 308 | 435 | 60% | 40% |
| home_to_groups | 1000 | 279 | 564 | 80% | 40% |
| home_to_groups | 1500 | 241 | 386 | 100% | 70% |
| home_to_groups | 2000 | 269 | 297 | 100% | 100% |
| home_to_groups | 3000 | 287 | 392 | 100% | 0% |
| home_to_about | 0 | 353 | 1334 | 0% | 0% |
| home_to_about | 100 | 386 | 1221 | 0% | 0% |
| home_to_about | 250 | 553 | 925 | 0% | 0% |
| home_to_about | 500 | 289 | 934 | 0% | 0% |
| home_to_about | 750 | 206 | 1821 | 0% | 0% |
| home_to_about | 1000 | 289 | 754 | 0% | 0% |
| home_to_about | 1500 | 228 | 359 | 0% | 0% |
| home_to_about | 2000 | 260 | 503 | 0% | 0% |
| home_to_about | 3000 | 217 | 536 | 100% | 40% |

## Threshold (P95<=1500ms)

```json
{
  "home_to_login": 100,
  "home_to_groups": 0,
  "home_to_about": 0
}
```

## Root cause

Priority prefetch starts post-hydration (useEffect+setTimeout); clicks <500ms race ahead of prefetch completion.
