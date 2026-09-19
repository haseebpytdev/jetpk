# Soft-nav — dba79865 / Nhn4WumfBYaLZlJaYW1f-

Intent-preempting single-flight coordinator. N=20.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 629 | **1890** | FAIL |
| support_home | 592 | 1102 | PASS |
| home_privacy | 597 | **1422** | PASS |
| privacy_terms | 269 | 779 | PASS |
| home_groups | 534 | **2701** | FAIL |
| home_login | 765 | **1629** | FAIL |
| login_register | 365 | 746 | PASS |
| home_about | 688 | **2954** | FAIL |
| home_faq | 677 | **2730** | FAIL |
| home_terms | 659 | **2549** | FAIL |

SOFT_NAV_PASS_COUNT=4/10
WORST=home_about 2954
SOFT_NAV_GATE=FAIL

Privacy recovered (9766→1422). Still cold P95 tails on CMS/auth routes.
Next: segment loading.tsx so URL commits immediately under soft-nav.
