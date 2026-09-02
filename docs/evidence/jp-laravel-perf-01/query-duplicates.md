# Query duplicates

Post-deploy sample (typical READY `search_perf`):

- `PRE_SUPPLIER_DB_QUERY_COUNT` ≈ 9
- `PRE_SUPPLIER_DUPLICATE_QUERY_COUNT` ≈ 2 (settings/eligibility residual)
- `PRE_SUPPLIER_N_PLUS_ONE` for markup: addressed via per-request memo (post-supplier path)
- `UNNECESSARY_DUPLICATE_DB_READS` for airport/agency on same request: memoized

No speculative indexes added.
