# OWNER UAT WAVE 2 — Task Status

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_GAPS

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
| W2-20 | Regression | REOPEN | — | — | False PASS | REOPENED | — | — | Full loop | Continue |
| W2-24 | Module matrix | REOPEN | — | — | Fake ops pages | REOPENED | New matrices | — | Remaining gaps | Continue |
| W2-25 | Financial source of truth | IN_PROGRESS | UNIT | NOT_DEPLOYED | USD + unavailable | REOPENED | BookingOperationalMoneyResolver | — | — | Deploy+retest |
| W2-26 | Full markup management | IN_PROGRESS | — | NOT_DEPLOYED | Read-only | REOPENED | Existing engine reused | — | — | Feature tests |
| W2-27 | Applications vs Agents | IN_PROGRESS | SPEC UPDATED | NOT_DEPLOYED | Ambiguous actions | REOPENED | Selected application workspace | — | — | Deploy+retest |
| W2-28 | Agents compact filters | IN_PROGRESS | — | NOT_DEPLOYED | Giant card | REOPENED | Search/status/type/more | — | — | Deploy+retest |
| W2-29 | Supplier vs API | IN_PROGRESS | — | NOT_DEPLOYED | Contradictory lists | REOPENED | Connection registry + suppliers link | — | Deploy | Deploy |
| W2-30 | API connection mgmt | IN_PROGRESS | CRUD 403 pre-existing agency-admin Blade | NOT_DEPLOYED | Metadata | REOPENED | Next workspace + JSON masked | — | No prod rotate | Deploy |
| W2-31 | Sabre capability truth | IN_PROGRESS | — | — | — | REOPENED | NDC supported, default off | — | UI truth labels | Continue |
| W2-32 | Profile + org | IN_PROGRESS | — | NOT_DEPLOYED | No avatar | REOPENED | Photo FormData + org form | — | Logo file UI | Deploy |
| W2-33 | CMS operational | IN_PROGRESS | — | — | Unmanageable content | REOPENED | Pages live editor exists | — | Media/homepage | Continue |
| W2-34 | Users/Staff/RBAC | IN_PROGRESS | — | NOT_DEPLOYED | Read-only admin | REOPENED | Invite/create/staff perms JSON | — | Custom role entity | Deploy |
| W2-35 | Nav active state | IN_PROGRESS | — | NOT_DEPLOYED | Dual active | REOPENED | most-specific href | — | — | Deploy+retest |
| W2-36 | Admin management matrix | IN_PROGRESS | DOC | — | Primary requirement | REOPENED | matrix file | — | Gaps remain | Continue |
| W2-37 | Cross-portal matrix | IN_PROGRESS | DOC | — | — | REOPENED | matrix file | — | Audit remaining | Continue |
