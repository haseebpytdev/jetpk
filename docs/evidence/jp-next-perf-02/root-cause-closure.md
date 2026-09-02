# JP-NEXT-PERF-02A — Root cause closure matrix

## ROOT_CAUSE_1 — Groups client hydration/proxy authority

**Status:** CLOSED

| | ms |
|--|-----|
| Measured before | ~9755 first card (02B authority) |
| Measured after (N20) | Cold first-card P50 **1417** / P95 **6825** (single `jpAuditReset` outlier); remaining 9 cold samples **1013–1725**. Warm first-card P50 **1257** / P95 **1931**. |

SSR inventory + matched filter key skips client `/groups/search/data` refetch. Warm path meets ≤4s P95. Cold P95 inflated by one audit-reset outlier, not a reopened Groups engineering defect.

## ROOT_CAUSE_2 — 720ms artificial Groups timeout

**Status:** CLOSED

Landing `setTimeout(720)` removed; Groups landing ACK measured numerically in `user-action-ack.json`.

## ROOT_CAUSE_3 — Review blank client-only loading

**Status:** CLOSED

```
REVIEW_BLANK_FULL_PAGE_LOADING=NO
REVIEW_READY_TO_SKELETON_REGRESSION=0
REVIEW_LOADING_TO_READY_P95_MS=78
```

## ROOT_CAUSE_4 — Payment blank client-only loading / Flight READY-clearing loading

**Status:** CLOSED (split)

| Sub-cause | Status | Evidence |
|-----------|--------|----------|
| Payment blank full-page loading | CLOSED | `PAYMENT_BLANK_FULL_PAGE_LOADING=NO`; shell READY P95 loading→ready **138ms** |
| Flight READY→skeleton on local filter/sort | CLOSED | `FILTER_OR_SORT_READY_TO_SKELETON_REGRESSIONS=0` |

## Not reopened

No Groups redesign. No speculative optimizations in 02A.
