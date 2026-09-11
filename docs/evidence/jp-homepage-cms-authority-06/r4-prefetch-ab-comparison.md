# R4 local prefetch A/B

| Route | A P95 | B P95 | Δ | A rsc→commit | B rsc→commit | Δ | A dup | B dup |
|-------|-------|-------|---|--------------|--------------|---|-------|-------|
| home_to_login | 31051 | 32120 | -1069 | 468 | 983 | -515 | 8 | 10 |
| home_to_groups | 826 | 1807 | -981 | 1173 | null | 1173 | 6 | 2 |
| home_to_about | 1626 | 879 | 747 | 3733 | 118 | 3615 | 6 | 6 |
| home_to_contact | 1860 | 1854 | 6 | 261 | 353 | -92 | 4 | 6 |
| home_to_support | 2232 | 1940 | 292 | 823 | 252 | 571 | 8 | 8 |

R4_PREFETCH_CONTENTION=NOT_PROVEN
