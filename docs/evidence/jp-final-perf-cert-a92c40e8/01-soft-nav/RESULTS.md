# Soft-nav — a92c40e8 / FLAS1lJ_c2IvqkXFzbius

Intent-only (no background queue) + staleTimes. N=20.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 752 | **1485** | PASS |
| support_home | 829 | **1839** | FAIL |
| home_privacy | 638 | **1829** | FAIL |
| privacy_terms | 288 | 359 | PASS |
| home_groups | 910 | **3272** | FAIL |
| home_login | 574 | **2829** | FAIL |
| login_register | 314 | 468 | PASS |
| home_about | 685 | **2199** | FAIL |
| home_faq | 613 | **2948** | FAIL |
| home_terms | 656 | **1147** | PASS |

SOFT_NAV_PASS_COUNT=4/10
WORST=home_groups 3272
SOFT_NAV_GATE=FAIL

Cold first-sample spikes still dominate P95. Traveler/return already PASS on e5eead09 (same lineage).
