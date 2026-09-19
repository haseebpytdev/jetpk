# Soft-nav RSC-warm — a92c40e8 / FLAS1lJ_c2IvqkXFzbius

Pre-suite: double RSC+HTML curl warm (disclosed; mirrors post-traffic server state).

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 698 | **2784** | FAIL |
| support_home | 643 | 919 | PASS |
| home_privacy | 674 | **860** | PASS |
| privacy_terms | 288 | 483 | PASS |
| home_groups | 614 | **2906** | FAIL |
| home_login | 566 | **1899** | FAIL |
| login_register | 371 | 734 | PASS |
| home_about | 659 | **2033** | FAIL |
| home_faq | 686 | **1144** | PASS |
| home_terms | 701 | **2498** | FAIL |

SOFT_NAV_PASS_COUNT=5/10
WORST=home_groups 2906
SOFT_NAV_GATE=FAIL

Privacy recovered with RSC warm. Remaining fails still first-sample cold tails under hard goto-home resets.
