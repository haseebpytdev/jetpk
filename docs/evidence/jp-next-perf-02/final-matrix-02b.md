# JP-NEXT-PERF-02B — final matrix

| Metric | Before (02A residual) | After (02B r8) | Target | Status |
|--------|----------------------|----------------|--------|--------|
| BOOK_NOW_TO_ROUTE_SHELL_P95 | 6098 | 780 | ≤1500 | PASS |
| FARE_TO_TRAVELER_FRONTEND_OVERHEAD_P95 | 6098 | 1518 | ≤2000 | PASS |
| FARE_TO_TRAVELER_TOTAL_P95 | 20632 | 2968 | report | OK |
| FARE_VALIDATE_SUPPLIER_P95 | NOT_CAPTURED | 1510 | capture | PASS |
| PAIR_TO_SEGMENTED_P95 | 8889 | 131 | ≤500 | PASS |
| SEGMENTED_TO_PAIR_P95 | 14414 | 227 | ≤500 | PASS |
| RETURN_VIEW_SWITCH_SUPPLIER_CALLS | (refetch) | 0 | 0 | PASS |
| GROUP_CLEAN_COLD_FIRST_CARD_P95 | 6825* | 3521 | ≤4000 | PASS |
| DATA→RENDER_P95 | 908 | 460 | ≤500 | PASS |
| Review/Payment | GOOD | unchanged | preserve | PASS |

\*02A cold included harness `jpAuditReset` outlier (not user path).

## Certification

`PERF_02B_CERTIFICATION=PASS_RESIDUAL_NEXTJS_LATENCY_CLOSED`

`FINAL_STATUS=PASS_READY_FOR_CHATGPT_FINAL_PERFORMANCE_REVIEW`

## Authority

- Branch: `phase/jp-flight-perf-01`
- Remote freeze: `1f12edef052da278f02b7ffeaf4e7a881c663ef9` (unchanged, no push)
- Deploy engineering: `568efa8d2d9e916370b6dc49a36bcbbc26ff268a`
- Public build: `U9-V-YGZgQ3qKayMCp4BX`
- MOFA undeployed
