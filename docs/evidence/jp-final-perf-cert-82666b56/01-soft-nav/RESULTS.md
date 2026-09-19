# Soft-nav — 82666b56 / AvC6rWR9ol8wIT_M2oc8H

N=20. Intent prefetch + queue support/groups-first + groups Suspense.

| Route | P50 | P95 | Gate |
|-------|-----|-----|------|
| home_support | 1645 | **3201** | FAIL |
| support_home | 657 | 1382 | PASS |
| home_privacy | 798 | **9766** | FAIL |
| privacy_terms | 474 | 1055 | PASS |
| home_groups | 732 | **1952** | FAIL (was 11402) |
| home_login | 932 | **3393** | FAIL |
| login_register | 216 | 1429 | PASS |
| home_about | 889 | **2418** | FAIL |
| home_faq | 1342 | **4308** | FAIL |
| home_terms | 940 | **2076** | FAIL |

SOFT_NAV_PASS_COUNT=3/10
WORST=home_privacy 9766
SOFT_NAV_GATE=FAIL

**Note:** groups Suspense cut P95 11402→1952. Queue reorder hurt privacy/faq/terms vs e6d5aaea.
Next: restore legal-first queue; delay background prefetch until after first paint idle; keep intent hover warm.
