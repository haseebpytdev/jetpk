# OWNER UAT WAVE 2 — Task Status

| TASK | SCOPE | CODE | TEST | PRODUCTION | OWNER_FINDING | STATUS | EVIDENCE | COMMIT | BLOCKER | NEXT |
|---|---|---|---|---|---|---|---|---|---|---|
| W2-01 | Authoritative PKR money pipeline | DONE | PASS | DEPLOYED | USD/PKR relabel | IN_PROGRESS | Rs. formatter | 8ed171d | — | Owner retest |
| W2-02 | Admin dashboard amount reconciliation | PARTIAL | PASS | DEPLOYED | KPI inconsistency | IN_PROGRESS | Overview presenter | 8ed171d | — | Owner retest |
| W2-03 | Users vs Staff semantics | DONE | PASS | DEPLOYED | Users≠Staff | IN_PROGRESS | /staff + scope tests | 73a48f2 | — | Owner retest |
| W2-04 | Compact Users table + sort/filter | DONE | PASS | DEPLOYED | Hard to find roles | IN_PROGRESS | Compact table + filters | 1ec26f5 | — | Owner retest |
| W2-05 | Booking Management one-page cleanup | PARTIAL | PENDING | DEPLOYED | Duplicate actions | IN_PROGRESS | Dedupe + eligibility | 8ed171d | — | Owner retest |
| W2-06 | Contact/passenger amendment | DONE | PASS | DEPLOYED | Need policy | IN_PROGRESS | Local contact PATCH | 4addea7 | NO_SUPPLIER_WRITE | Owner retest |
| W2-07 | Admin/Staff typography | DONE | LOCAL_PASS | DEPLOYED | Clash/Inter | IN_PROGRESS | Plus Jakarta + Clash cherry-pick | 5885dec | Browser font verify | Computed font check |
| W2-08 | Admin/Staff My Profile | DONE | PASS | DEPLOYED | Missing entry | IN_PROGRESS | /profile | 8ed171d | — | Owner retest |
| W2-09 | Settings redesign | PARTIAL | PASS | DEPLOYED | Confusing validation | IN_PROGRESS | OWNER_INPUT_REQUIRED + IA badges | b7e72a3 | — | Overview readiness sync |
| W2-10 | Reports live-data | PARTIAL | PENDING | DEPLOYED | Preview copy | IN_PROGRESS | Live-mode copy | 8ed171d | — | Owner retest |
| W2-11 | CMS baseline operational | PARTIAL | PENDING | PARTIAL | Empty/read-only | IN_PROGRESS | Pages JSON editor deployed | 4addea7 | No Page Builder | Banners/notices/assets |
| W2-12 | Support pagination max 10 | DONE | PASS | DEPLOYED | Need 10/page | IN_PROGRESS | Default 10 | cd1e631 | — | Owner retest |
| W2-13 | Compact shared filters | PARTIAL | PENDING | PARTIAL | Giant panels | OPEN | Users/Staff pattern | 1ec26f5 | — | Bookings/Payments |
| W2-14 | Agent Deposits / manual credit | PARTIAL | PENDING | DEPLOYED | Insufficient ops | IN_PROGRESS | Read-only guard | 3316ec0 | NO_PROD_MONEY | Owner retest |
| W2-15 | Markup Management | PARTIAL | PENDING | DEPLOYED | Not discoverable | IN_PROGRESS | markup_settings nav | 3316ec0 | NO_PROD_MUTATION | Owner retest |
| W2-16 | Failed notifications | DONE | PASS | CLASSIFIED+UI | Count unexplained | IN_PROGRESS | QA SMTP 550 | 8ed171d | — | Owner retest |
| W2-17 | Remove fullscreen control | DONE | PASS | DEPLOYED | Mystery ○ | IN_PROGRESS | header.tsx | 8ed171d | — | Owner retest |
| W2-18 | Email location semantics | DONE | PASS | DEPLOYED | Karachi | IN_PROGRESS | Seed address null; no security city | b7e72a3 | — | Owner retest |
| W2-19 | Button/text clarity | PENDING | PENDING | PENDING | Contrast | OPEN | — | — | — | After font verify |
| W2-20 | Final cross-module regression | PENDING | PENDING | PENDING | Gate closure | OPEN | — | — | — | End of wave |
| W2-21 | Public shell header/footer | DONE | LOCAL_PASS | DEPLOYED | Currency/nav | IN_PROGRESS | Cherry-pick + public rebuild | 5885dec | Browser verify | Drop-up/nav check |
| W2-22 | Plus Jakarta + Clash typography | DONE | LOCAL_PASS | DEPLOYED | Inter residue | IN_PROGRESS | Cherry-pick + public rebuild | 5885dec | Computed fonts | Inter residue scan |
