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
| W2-29 | Supplier vs API | DONE | PASS | PENDING_DEPLOY | Contradictory lists | CODE_CLOSED | Connection registry + suppliers link | 8d79f0c7 | Prod verify | Deploy+retest |
| W2-30 | API connection mgmt | DONE | 3 tests / 15 assertions | PENDING_DEPLOY | Metadata | CODE_CLOSED | JSON masked + client list without server-only import | 8d79f0c7 | No prod rotate | Deploy+retest |
| W2-31 | Sabre capability truth | DONE | Adapter classes | PENDING_DEPLOY | — | CODE_CLOSED | GDS/NDC supported; NDC enabled default false | 8d79f0c7 | Prod UI labels | Deploy+retest |
| W2-32 | Profile + org | DONE | — | PENDING_DEPLOY | No avatar | CODE_CLOSED | Photo FormData + org form | 8d79f0c7 | Prod verify | Deploy+retest |
| W2-33 | CMS operational | DONE | — | PENDING_DEPLOY | Unmanageable content | CODE_CLOSED | Pages + media + structured homepage | — | Prod verify | Deploy+retest |
| W2-34 | Users/Staff/RBAC | DONE | — | PENDING_DEPLOY | Read-only admin | CODE_CLOSED | Invite/create/staff perms; account-type catalogue | 8d79f0c7 | No fake Create Role | Deploy+retest |
| W2-35 | Nav active state | DONE | — | PENDING_DEPLOY | Dual active | CODE_CLOSED | most-specific href | 8d79f0c7 | SIDEBAR_PRIMARY_ACTIVE_COUNT=1 | Deploy+retest |
| W2-36 | Admin management matrix | IN_PROGRESS | DOC | PENDING_DEPLOY | Primary requirement | REOPENED | Go-live validators wired | — | Prod verify | Deploy+retest |
| W2-37 | Cross-portal matrix | IN_PROGRESS | DOC | — | — | REOPENED | matrix file | — | Audit remaining | Continue |
