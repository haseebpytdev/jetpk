# Soft-nav — a895eaea / tEgZGLmLHLmRO2eqJs__z

Server curl warm before suite (ISR warm; not harness per-click prefetch wait).
Loading.tsx on auth + support deployed.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 751 | **3192** | FAIL |
| support_home | 634 | **1752** | FAIL |
| home_privacy | 530 | 1000 | PASS |
| privacy_terms | 161 | 545 | PASS |
| home_groups | 616 | 996 | PASS |
| home_login | 734 | **2273** | FAIL |
| login_register | 224 | 370 | PASS |
| home_about | 819 | **1856** | FAIL |
| home_faq | 585 | 860 | PASS |
| home_terms | 638 | 1020 | PASS |

SOFT_NAV_PASS_COUNT=6/10
WORST_SOFT_NAV_ROUTE=home_support
WORST_SOFT_NAV_P95=3192
SOFT_NAV_GATE=FAIL

PERFORMANCE_CERTIFICATION remains blocked on soft-nav (retirement/cleanup gated).
