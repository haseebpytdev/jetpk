# Markup profile

Before: `getApplicableRules` queried all active agency rules **per offer**.

After: active rows memoized per agency per request; PHP `matchesRule` still per offer.

`MARKUP_INDEX_CHANGE_REQUIRED=NO` — no EXPLAIN evidence requiring a new index.

Markup resolution remains post-supplier (correct — cannot price before offers).
