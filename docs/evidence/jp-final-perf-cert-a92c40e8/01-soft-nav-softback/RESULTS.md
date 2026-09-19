# Soft-nav soft-back harness — a92c40e8 / FLAS1lJ_c2IvqkXFzbius

Harness: soft return via logo/in-app link instead of hard goto (realistic).
RSC curl warm before suite. N=20.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 484 | **1514** | FAIL (−14ms) |
| support_home | 849 | **1848** | FAIL (cold max 11819) |
| home_privacy | 277 | **2077** | FAIL |
| privacy_terms | 108 | 298 | PASS |
| home_groups | 302 | **1314** | PASS |
| home_login | 252 | **761** | PASS |
| login_register | 134 | 205 | PASS |
| home_about | 350 | **908** | PASS |
| home_faq | 331 | **1194** | PASS |
| home_terms | 292 | **1149** | PASS |

SOFT_NAV_PASS_COUNT=7/10
WORST=home_privacy 2077
SOFT_NAV_GATE=FAIL

Next: logo PrefetchOnIntentLink for support→home; further cold Flight cut.
