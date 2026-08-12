# OWNER UAT WAVE 2 — Task Status

| TASK | SCOPE | CODE | TEST | PRODUCTION | OWNER_FINDING | STATUS | EVIDENCE | COMMIT | BLOCKER | NEXT |
|---|---|---|---|---|---|---|---|---|---|---|
| W2-01 | Authoritative PKR money pipeline | DONE | PASS | DEPLOYED | USD/PKR relabel | IN_PROGRESS | Rs. formatter | 8ed171d | — | Owner retest |
| W2-02 | Admin dashboard amount reconciliation | PARTIAL | PASS | DEPLOYED | KPI inconsistency | IN_PROGRESS | Overview presenter | 8ed171d | — | Owner retest |
| W2-03 | Users vs Staff semantics | DONE | PASS | PENDING | Users≠Staff | IN_PROGRESS | /staff scope; Customer in Users; Agent≠Agent Staff | pending push | Deploy | Prod verify |
| W2-04 | Compact Users table + sort/filter | DONE | PASS | DEPLOYED | Hard to find roles | IN_PROGRESS | Compact table + More filters + Staff filters | 1ec26f5 | — | Owner retest |
| W2-05 | Booking Management one-page cleanup | PARTIAL | PENDING | DEPLOYED | Duplicate actions | IN_PROGRESS | Dedupe + eligibility | 8ed171d | — | Owner retest |
| W2-06 | Contact/passenger amendment | DONE | PASS | PENDING | Need policy | IN_PROGRESS | Local contact PATCH + policy unit tests | pending push | NO_SUPPLIER_WRITE | Deploy |
| W2-07 | Admin/Staff typography | PENDING | PENDING | PENDING | Clash/Inter | OPEN | worktree 153cfaa exists | — | Reconcile onto business branch | Apply W2-21/22 |
| W2-08 | Admin/Staff My Profile | DONE | PASS | DEPLOYED | Missing entry | IN_PROGRESS | /profile | 8ed171d | — | Owner retest |
| W2-09 | Settings redesign | PARTIAL | PENDING | PARTIAL | Confusing validation | OPEN | False-validation fixed; IA incomplete | c4902cf | — | Finish GENERAL/SECURITY/NOTIFICATIONS/INTEGRATIONS |
| W2-10 | Reports live-data | PARTIAL | PENDING | DEPLOYED | Preview copy | IN_PROGRESS | Live-mode copy | 8ed171d | — | Owner retest |
| W2-11 | CMS baseline operational | PARTIAL | PENDING | PENDING | Empty/read-only | IN_PROGRESS | Pages JSON + Next editor only | pending push | No Page Builder | Deploy pages; then banners/notices |
| W2-12 | Support pagination max 10 | DONE | PASS | DEPLOYED | Need 10/page | IN_PROGRESS | Default 10 + Prev/Next | cd1e631 | — | Owner retest |
| W2-13 | Compact shared filters | PARTIAL | PENDING | PARTIAL | Giant panels | OPEN | Users/Staff pattern landed | 1ec26f5 | — | Bookings/Payments/Reports |
| W2-14 | Agent Deposits / manual credit | PARTIAL | PENDING | DEPLOYED | Insufficient ops | IN_PROGRESS | Read-only Approve/Reject guard | 3316ec0 | NO_PROD_MONEY | Owner retest |
| W2-15 | Markup Management | PARTIAL | PENDING | DEPLOYED | Not discoverable | IN_PROGRESS | markup_settings nav fix | 3316ec0 | NO_PROD_MUTATION | Owner retest |
| W2-16 | Failed notifications | DONE | PASS | CLASSIFIED+UI | Count unexplained | IN_PROGRESS | QA SMTP 550; ops page | 8ed171d | — | Owner retest |
| W2-17 | Remove fullscreen control | DONE | PASS | DEPLOYED | Mystery ○ | IN_PROGRESS | header.tsx | 8ed171d | — | Owner retest |
| W2-18 | Email location semantics | PENDING | PENDING | PENDING | Karachi | OPEN | — | — | — | Classify source |
| W2-19 | Button/text clarity | PENDING | PENDING | PENDING | Contrast | OPEN | — | — | — | After typography |
| W2-20 | Final cross-module regression | PENDING | PENDING | PENDING | Gate closure | OPEN | — | — | — | End of wave |
| W2-21 | Public shell header/footer | PENDING | LOCAL_PASS | PENDING | Currency/nav | OPEN | ota-jetpk-w2-shell @ 153cfaa | 153cfaa | Reconcile branch | Cherry-pick to business |
| W2-22 | Plus Jakarta + Clash typography | PENDING | LOCAL_PASS | PENDING | Inter residue | OPEN | ota-jetpk-w2-shell @ 153cfaa | 153cfaa | Reconcile branch | Cherry-pick to business |
