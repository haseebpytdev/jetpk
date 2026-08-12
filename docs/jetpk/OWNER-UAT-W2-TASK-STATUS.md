# OWNER UAT WAVE 2 — Task Status

| TASK | SCOPE | CODE | TEST | PRODUCTION | OWNER_FINDING | STATUS | EVIDENCE | COMMIT | BLOCKER | NEXT |
|---|---|---|---|---|---|---|---|---|---|---|
| W2-01 | Authoritative PKR money pipeline | DONE | PENDING | PENDING | USD/PKR relabel + Amount unavailable | IN_PROGRESS | format.ts + DashboardMoneyPresenter Rs. | — | — | Unit tests + prod verify |
| W2-02 | Admin dashboard booking/amount reconciliation | PARTIAL | PENDING | PENDING | 590 USD vs 624 USD vs unavailable | IN_PROGRESS | Overview uses presenter displayLabel | — | — | Confirm live KPI currency labels |
| W2-03 | Users vs Staff semantics | PENDING | PENDING | PENDING | Users≠Staff | OPEN | — | — | — | Compact Users table |
| W2-04 | Compact Users table + sort/filter | PENDING | PENDING | PENDING | Hard to find roles | OPEN | — | — | — | After compact filter framework |
| W2-05 | Booking Management one-page cleanup | PARTIAL | PENDING | PENDING | Duplicate actions | IN_PROGRESS | showOperationalActions=false on mgmt page | — | — | Lifecycle eligibility |
| W2-06 | Contact/passenger amendment | PENDING | PENDING | PENDING | Need policy | OPEN | — | — | — | Domain audit |
| W2-07 | Admin/Staff typography | PENDING | PENDING | PENDING | Clash/Inter incomplete | OPEN | — | — | — | After P1 |
| W2-08 | Admin/Staff My Profile | DONE | PENDING | PENDING | Missing entry | IN_PROGRESS | /profile + header menu | — | — | Prod verify |
| W2-09 | Settings redesign | PENDING | PENDING | PENDING | Confusing validation | OPEN | — | — | — | IA redesign |
| W2-10 | Reports live-data | PARTIAL | PENDING | PENDING | Preview records copy | IN_PROGRESS | Live-mode copy + Rs. report labels | — | — | Confirm no fixture path in live |
| W2-11 | CMS baseline operational | PENDING | PENDING | PENDING | Empty/read-only | OPEN | — | — | — | Existing schema audit |
| W2-12 | Support pagination max 10 | PENDING | PENDING | PENDING | Need 10/page | OPEN | — | — | — | Default page size |
| W2-13 | Compact shared filters | PENDING | PENDING | PENDING | Giant filter panels | OPEN | — | — | — | Shared filter UX |
| W2-14 | Agent Deposits / manual credit | PENDING | PENDING | PENDING | Insufficient ops | OPEN | — | — | NO_PROD_MONEY | Architecture + test-only mutations |
| W2-15 | Markup Management | PENDING | PENDING | PENDING | Not discoverable | OPEN | — | — | NO_PROD_MUTATION | Nav + CRUD test |
| W2-16 | Failed notifications | PENDING | PENDING | PENDING | Count=43 unexplained | OPEN | — | — | — | Classify records |
| W2-17 | Remove fullscreen control | DONE | PENDING | PENDING | Mystery ○ control | IN_PROGRESS | header.tsx removed fullscreen | — | — | Prod verify |
| W2-18 | Email location semantics | PENDING | PENDING | PENDING | Karachi in email | OPEN | Asia/Karachi timezone in format.ts | — | — | Trace mail templates |
| W2-19 | Button/text clarity | PENDING | PENDING | PENDING | Contrast/hierarchy | OPEN | — | — | — | After typography |
| W2-20 | Final cross-module regression | PENDING | PENDING | PENDING | Gate closure | OPEN | — | — | — | End of wave |
