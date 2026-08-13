# OWNER UAT WAVE 2 — Task Status

OWNER_UAT_WAVE_2=PASS_READY_FOR_OWNER_RETEST_V2

Prior PASS_READY is invalidated by owner production screenshots.

| TASK | SCOPE | CODE | TEST | PRODUCTION | OWNER_FINDING | STATUS | EVIDENCE | COMMIT | BLOCKER | NEXT |
|---|---|---|---|---|---|---|---|---|---|---|
| W2-01 | Authoritative PKR money pipeline | IN_PROGRESS | PARTIAL | NOT_RETESTED | USD 589.73 KPI | REOPENED | Operational PKR snapshot | — | Historical USD without snapshot | Owner retest V2 |
| W2-02 | Admin dashboard amount reconciliation | IN_PROGRESS | PARTIAL | NOT_RETESTED | Amount unavailable | REOPENED | amount_display on recent rows | — | Missing currency on arrays | Owner retest V2 |
| W2-03 | Users vs Staff semantics | PARTIAL | PASS | DEPLOYED | Management incomplete | REOPENED | — | — | Role mutations | Continue |
| W2-04 | Compact Users table | DONE | PASS | DEPLOYED | — | KEEP | — | — | — | — |
| W2-09 | Settings | PARTIAL | PASS | DEPLOYED | Metadata only | REOPENED | — | — | Credential UI | Continue |
| W2-11 | CMS | PARTIAL | PASS | DEPLOYED | Read-only | REOPENED | Pages write only | — | Media/banners | Continue |
| W2-13 | Compact filters | IN_PROGRESS | — | NOT_RETESTED | Noisy Agents | REOPENED | Compact Agents bar | — | — | Owner retest V2 |
| W2-15 | Markup Management | IN_PROGRESS | — | NOT_RETESTED | Read-only page | REOPENED | MarkupRule JSON + UI | — | No prod QA mutation | Owner retest V2 |
| W2-20 | Regression | DONE | 132/132 Dashboard; 58 Wave-2 Laravel | VERIFIED | False PASS | KEEP | 05c24789 + browser 5-actor | 05c24789 | — | Owner retest V2 |
| W2-24 | Module matrix | REOPEN | — | — | Fake ops pages | REOPENED | New matrices | — | Remaining gaps | Continue |
| W2-25 | Financial source of truth | DONE | 8/25 | DEPLOYED | USD + snapshot policy | KEEP | 0860c212 + Admin GBV USD note | 0860c212 | — | Owner retest V2 |
| W2-33 | CMS operational | DONE | — | DEPLOYED | Structured homepage | KEEP | Panel on /cms/sections BUILD llKFcUe5cBrUnhUEHic0U | 2fb80b50 | Sections list API unavailable | Owner retest V2 |
| W2-36 | Admin management matrix | DONE | DOC | VERIFIED | — | KEEP | Admin deep JSON | 2fb80b50 | Regression remaining | Full regression |
| W2-37 | Cross-portal matrix | DONE | DOC | VERIFIED | — | KEEP | QA agent_staff id 12 | — | Blade admin.users.show 302 tests | Full regression |
| W2-26 | Full markup management | DONE | — | DEPLOYED | Write UI present | KEEP | No prod markup mutation | ed57f078 | Safety | Owner retest V2 |
| W2-27 | Applications vs Agents | DONE | — | DEPLOYED | Selected-only actions | KEEP | Admin deep workspace | a8a7c527 | — | Owner retest V2 |
| W2-28 | Agents compact filters | DONE | — | DEPLOYED | Compact bar | KEEP | — | ed57f078 | — | Owner retest V2 |
| W2-29 | Supplier vs API | DONE | PASS | DEPLOYED | Connection registry | KEEP | — | 8d79f0c7 | No prod rotate | Owner retest V2 |
| W2-30 | API connection mgmt | DONE | 3/15 | DEPLOYED | Masked JSON | KEEP | — | 0e724683 | No prod rotate | Owner retest V2 |
| W2-31 | Sabre capability truth | DONE | — | DEPLOYED | GDS/NDC labels | KEEP | — | 8d79f0c7 | — | Owner retest V2 |
| W2-32 | Profile + org | DONE | — | DEPLOYED | Photo + org | KEEP | — | bf5e9cdb | — | Owner retest V2 |
| W2-34 | Users/Staff/RBAC | DONE | — | DEPLOYED | Next users/staff | KEEP | Blade admin.users.show tests 302 | 8d79f0c7 | Test update | Full regression |
| W2-35 | Nav active state | DONE | — | DEPLOYED | most-specific href | KEEP | — | 8d79f0c7 | — | Owner retest V2 |
