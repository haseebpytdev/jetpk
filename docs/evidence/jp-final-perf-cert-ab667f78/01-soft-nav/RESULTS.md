# Soft-nav — ab667f78 / W5Ru6q2ZPJnBtDSlB-zLR

Unbiased N=20. Disk free to 64%. Staggered priority waves.

| Route | APP_P95 | Gate |
|-------|---------|------|
| home_support | 1484 | PASS |
| support_home | 666 | PASS |
| home_privacy | **2738** | FAIL (cold sample0–1) |
| privacy_terms | 334 | PASS |
| home_groups | 1234 | PASS |
| home_login | **2143** | FAIL (auth force-dynamic) |
| login_register | 713 | PASS |
| home_about | **1752** | FAIL |
| home_faq | 1197 | PASS |
| home_terms | 979 | PASS |

SOFT_NAV_PASS_COUNT=7/10
WORST=home_privacy 2738
SOFT_NAV_GATE=FAIL

Root cause for login: `(auth)/layout.tsx` had `force-dynamic` — fixed in bd12ac76.
