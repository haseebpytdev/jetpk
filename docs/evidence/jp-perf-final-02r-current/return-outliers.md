# Return outlier classification — JP-PERF-FINAL-02R-CURRENT

Cohort: preserved `return-n30.json` (N=30, SHA256 B4BF43…)

## Aggregate decomposition (all 30 samples)

| Metric | ms |
|---|---|
| RETURN_P50 | 3866 |
| RETURN_P95 | 5622 |
| RETURN_GATE | FAIL |
| RETURN_SUPPLIER_P95 | 3651.7850000000003 |
| RETURN_PRE_SUPPLIER_P95 | 2739.877 |
| RETURN_POST_SUPPLIER_TO_USABLE_P95 | 2023.7689999999998 |
| RETURN_APP_P95 | 4093.0750000000003 |
| RETURN_UNEXPLAINED_OUTLIERS | 6 |

## Samples >4500ms (n=6)

| sample | wall | supplier | post-supplier→usable | JP app | dominant |
|---|---:|---:|---:|---:|---|
| return-browser-00 | 5250 | 3409 | 1786 | 3529 | JETPAKISTAN_POST_SUPPLIER |
| return-browser-03 | 5724 | 4495 | 1174 | 2994 | SUPPLIER_NETWORK |
| return-browser-04 | 5034 | 3652 | 1295 | 2869 | SUPPLIER_NETWORK |
| return-browser-05 | 4603 | 3078 | 1456 | 2996 | SUPPLIER_NETWORK |
| return-browser-25 | 5622 | 2963 | 2606 | 5358 | JETPAKISTAN_POST_SUPPLIER |
| return-browser-29 | 4991 | 2901 | 2024 | 3497 | JETPAKISTAN_POST_SUPPLIER |

## Conclusion

Absolute Return P95 **5622ms FAILS** ≤4500ms gate.
Over-4500ms samples: 3 supplier-dominant, 3 JP post-supplier, 0 mixed/JP-client.
Do not code-fix until Traveler + soft-nav complete unless JP post-supplier >1000ms is proven systematic.
