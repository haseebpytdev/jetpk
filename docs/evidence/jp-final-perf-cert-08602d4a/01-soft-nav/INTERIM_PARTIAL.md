# Soft-nav recert interim — 08602d4a / dXsEQITaqWlNkV92C-XMz

Harness crashed before `home_terms` with disclosed transient failure:

`page.goto: net::ERR_NAME_NOT_RESOLVED at https://jetpakistan.pk/`

RETRY_DISCLOSED=YES (harness/network; not slow-sample discard)

Partial APP_P95 before crash:

| Route | P50 | P95 | vs 536521f1 |
|-------|-----|-----|-------------|
| home_support | 791 | 2310 | improved 4250→2310 still FAIL |
| support_home | 504 | 1223 | PASS (was 3809) |
| home_privacy | 713 | 2795 | still FAIL |
| privacy_terms | 182 | 514 | PASS |
| home_groups | 732 | 1761 | FAIL (was PASS) |
| home_login | 546 | 1579 | improved still FAIL |
| login_register | 239 | 493 | PASS |
| home_about | 868 | 2799 | improved 5244→2799 still FAIL |
| home_faq | 696 | 3274 | REGRESSED (was 1331) |

Next: stagger priority waves (wave2 +220ms) and full soft-nav retry.
