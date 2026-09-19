# Soft-nav — ea6133bf / 3SKNIi617_j1OYWJEtVpg

Single-flight PublicRoutePrefetch + Link prefetch={false} on chrome.
Server curl warm before suite.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 762 | **1452** | PASS (was FAIL) |
| support_home | 693 | 1228 | PASS |
| home_privacy | 2703 | **11771** | FAIL (regressed) |
| privacy_terms | 644 | 1250 | PASS |
| home_groups | 1639 | 5333 | FAIL |
| home_login | 1702 | 4099 | FAIL |
| login_register | 283 | 1178 | PASS |
| home_about | 1503 | 6333 | FAIL |
| home_faq | 1925 | 9318 | FAIL |
| home_terms | 1594 | 4239 | FAIL |

SOFT_NAV_PASS_COUNT=4/10
WORST=home_privacy 11771
SOFT_NAV_GATE=FAIL

**Learning:** killing mount Link prefetch fixed support but removed hover-warm for legal routes under cert hover-before-click. Next: PrefetchOnIntentLink (hover/focus only).
