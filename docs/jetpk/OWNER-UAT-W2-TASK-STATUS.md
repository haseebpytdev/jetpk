# OWNER UAT WAVE 2 — Task Status

| TASK | SCOPE | CODE | TEST | PRODUCTION | OWNER_FINDING | STATUS | EVIDENCE | COMMIT | BLOCKER | NEXT |
|---|---|---|---|---|---|---|---|---|---|---|
| W2-01 | Authoritative PKR money pipeline | DONE | PASS | DEPLOYED | USD/PKR relabel | READY_OWNER | Rs. formatter | 8ed171d | — | Owner retest |
| W2-02 | Admin dashboard amount reconciliation | PARTIAL | PASS | DEPLOYED | KPI inconsistency | READY_OWNER | Overview presenter | 8ed171d | — | Owner retest |
| W2-03 | Users vs Staff semantics | DONE | PASS | DEPLOYED | Users≠Staff | READY_OWNER | /staff + scope tests | 73a48f2 | — | Owner retest |
| W2-04 | Compact Users table + sort/filter | DONE | PASS | DEPLOYED | Hard to find roles | READY_OWNER | Compact table + filters | 1ec26f5 | — | Owner retest |
| W2-05 | Booking Management one-page cleanup | PARTIAL | PASS | DEPLOYED | Duplicate actions | READY_OWNER | Dedupe + eligibility | 8ed171d | — | Owner retest |
| W2-06 | Contact/passenger amendment | DONE | PASS | DEPLOYED | Need policy | READY_OWNER | Local contact PATCH | 4addea7 | NO_SUPPLIER_WRITE | Owner retest |
| W2-07 | Admin/Staff typography | DONE | PASS | DEPLOYED | Clash/Inter | READY_OWNER | Plus Jakarta body; Clash H1; Inter=0 | 5885dec | — | Owner retest |
| W2-08 | Admin/Staff My Profile | DONE | PASS | DEPLOYED | Missing entry | READY_OWNER | /profile | 8ed171d | — | Owner retest |
| W2-09 | Settings redesign | DONE | PASS | DEPLOYED | Confusing validation | READY_OWNER | Live readiness + OWNER_INPUT | b3af949 | — | Owner retest |
| W2-10 | Reports live-data | PARTIAL | PASS | DEPLOYED | Preview copy | READY_OWNER | Live-mode copy | 8ed171d | — | Owner retest |
| W2-11 | CMS baseline operational | DONE | PASS | DEPLOYED | Empty/read-only | READY_OWNER | Pages write; others RO disposition | b3af949 | No Page Builder | Owner retest |
| W2-12 | Support pagination max 10 | DONE | PASS | DEPLOYED | Need 10/page | READY_OWNER | Default 10 | cd1e631 | — | Owner retest |
| W2-13 | Compact shared filters | DONE | PASS | DEPLOYED | Giant panels | READY_OWNER | Users/Staff/Bookings/Payments/Reports/Tickets | b3af949 | — | Owner retest |
| W2-14 | Agent Deposits / manual credit | PARTIAL | PASS | DEPLOYED | Insufficient ops | READY_OWNER | Read-only guard | 3316ec0 | NO_PROD_MONEY | Owner retest |
| W2-15 | Markup Management | PARTIAL | PASS | DEPLOYED | Not discoverable | READY_OWNER | markup_settings nav | 3316ec0 | NO_PROD_MUTATION | Owner retest |
| W2-16 | Failed notifications | DONE | PASS | CLASSIFIED+UI | Count unexplained | READY_OWNER | QA SMTP 550 | 8ed171d | — | Owner retest |
| W2-17 | Remove fullscreen control | DONE | PASS | DEPLOYED | Mystery ○ | READY_OWNER | header.tsx | 8ed171d | — | Owner retest |
| W2-18 | Email location semantics | DONE | PASS | DEPLOYED | Karachi | READY_OWNER | Seed address null; no security city | b7e72a3 | — | Owner retest |
| W2-19 | Button/text clarity | DONE | PASS | DEPLOYED | Contrast | READY_OWNER | Ghost + muted contrast | 91602b4 | — | Owner retest |
| W2-20 | Final cross-module regression | DONE | PASS | DEPLOYED | Gate closure | READY_OWNER | Staff auth + route smoke + OLS | see W2-20 evidence | — | Owner retest |
| W2-21 | Public shell header/footer | DONE | PASS | DEPLOYED | Currency/nav | READY_OWNER | Prod accept PASS | see W2-21/22 doc | — | Owner retest |
| W2-22 | Plus Jakarta + Clash typography | DONE | PASS | DEPLOYED | Inter residue | READY_OWNER | Prod Inter=0; fonts verified | see W2-21/22 doc | — | Owner retest |
