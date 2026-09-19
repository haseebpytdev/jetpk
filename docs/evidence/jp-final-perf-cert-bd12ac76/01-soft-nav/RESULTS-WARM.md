# Soft-nav warm — bd12ac76 / t0yFVrASTREvy-GuLWhWt

Post-cold natural warm recert (same BUILD). No artificial per-click prefetch wait.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 865 | **2138** | FAIL (outlier i=1 @9713) |
| support_home | 478 | **2557** | FAIL |
| home_privacy | 684 | 1075 | PASS |
| privacy_terms | 157 | 427 | PASS |
| home_groups | 667 | 1466 | PASS |
| home_login | 510 | **1972** | FAIL (outlier i=1 @11723) |
| login_register | 240 | **1505** | FAIL (by 5ms) |
| home_about | 772 | 1291 | PASS |
| home_faq | 621 | 892 | PASS |
| home_terms | 657 | 1039 | PASS |

SOFT_NAV_PASS_COUNT=6/10
WORST=support_home 2557
SOFT_NAV_GATE=FAIL

Next: a895eaea loading.tsx boundaries + recert.
