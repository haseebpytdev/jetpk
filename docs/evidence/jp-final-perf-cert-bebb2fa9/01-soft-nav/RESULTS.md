# Soft-nav — bebb2fa9 / 57bxDF087hSG1yFiCm6Zj

N=20. Intent overlap + 4s delayed background + CMS loading.tsx.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 700 | **3210** | FAIL |
| support_home | 680 | 980 | PASS |
| home_privacy | 695 | **1888** | FAIL |
| privacy_terms | 306 | 460 | PASS |
| home_groups | 524 | **5115** | FAIL |
| home_login | 551 | **2465** | FAIL |
| login_register | 367 | 857 | PASS |
| home_about | 695 | **4742** | FAIL |
| home_faq | 641 | **4280** | FAIL |
| home_terms | 635 | **2412** | FAIL |

SOFT_NAV_PASS_COUNT=3/10
WORST=home_groups 5115
SOFT_NAV_GATE=FAIL

Regressed vs dba79865 (4/10). loading.tsx did not move APP (URL) P95.
