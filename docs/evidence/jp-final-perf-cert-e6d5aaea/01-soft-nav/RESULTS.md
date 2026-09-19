# Soft-nav — e6d5aaea / ukXo5hPaGs1auUWE9-8Xg

Intent PrefetchOnIntentLink + single-flight PublicRoutePrefetch (STAGGER 160ms).
Server curl warm before suite. N=20/route. No artificial prefetch wait.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 882 | **3249** | FAIL |
| support_home | 518 | 1084 | PASS |
| home_privacy | 722 | **1963** | FAIL |
| privacy_terms | 310 | 923 | PASS |
| home_groups | 1160 | **11402** | FAIL |
| home_login | 732 | **2382** | FAIL |
| login_register | 296 | 1018 | PASS |
| home_about | 843 | **1842** | FAIL |
| home_faq | 576 | 1301 | PASS |
| home_terms | 707 | 1337 | PASS |

SOFT_NAV_PASS_COUNT=5/10
WORST=home_groups 11402
SOFT_NAV_GATE=FAIL

vs ea6133bf (prefetch={false} only): faq/terms/privacy much improved; support/groups still fail.
