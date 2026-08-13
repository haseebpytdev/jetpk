# OWNER UAT WAVE 2 — Task Status

OWNER_UAT_WAVE_2=REOPENED_PRE_OWNER_RETEST_V3_SOURCE_INTEGRITY
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
ADMIN_REQUIRED_MANAGEMENT_GAPS=remaining production CMS QA draft proof + 5-actor cross-portal after 694b5e1b
OWNER_RETEST_V2=DO_NOT_START_V3_UNTIL_SOURCE_INTEGRITY_CLOSED
LATEST_ENGINEERING_SHA=694b5e1b21a86ffd4f861647090408c7288828a8
LATEST_DOCS_CONTENT_SHA=f30567556b402518c0405d988af2e3924796513f
REMOTE_BRANCH_HEAD=pin-commit-follows
PRODUCTION_BUILD_ID=j_V7qVPpvh6PJvCoKBNLS
CMS_BLOCK_ROUNDTRIP=PASS
CMS_MEDIA_ROUNDTRIP=PASS
CMS_HIDDEN_RENDERING=PASS
PROVIDER_FIELD_METADATA=PASS
PROVIDER_CHANNEL_AWARE_CONFIGURATION=PASS
API_CONNECTION_AUDIT=PASS
RBAC_ROLE_SWITCH_STATE_ISOLATION=PASS
MARKUP_BUSINESS_RULE_BUILDER=PASS
FINAL_OLS_INTEGRITY=PASS
JP_REL_01=PROHIBITED

USER_ACCESS_MANAGEMENT_CRUD_TEST=PASS
FULL_ADMIN_REGRESSION=PASS
FULL_REGRESSION=PASS
CROSS_PORTAL_REGRESSION=PASS
FINAL_SOURCE_PARITY=PASS
FINAL_OLS_INTEGRITY=PASS
RBAC_ROLE_PERMISSION_MANAGEMENT=PASS
RBAC_OWNER_UX=PASS
CMS_FULL_MANAGEMENT=PASS
CMS_REAL_DRAFT_PREVIEW=PASS
API_CONNECTION_FULL_MANAGEMENT=PASS
MARKUP_BUSINESS_RULE_BUILDER=PASS
SAFE_ACTIONABLE_TASKS_REMAINING=0
SAFE_NON_MIGRATION_GAPS_REMAINING=0
RBAC_SCHEMA_APPROVAL_REQUIRED=NO

Pre-V3 source audit closed in `589e7089`. Stop for Owner Retest V3. Do not start JP-REL-01.

| TASK | SCOPE | CODE | TEST | PRODUCTION | OWNER_FINDING | STATUS | EVIDENCE | COMMIT | BLOCKER | NEXT |
|---|---|---|---|---|---|---|---|---|---|---|
| W2-01 | Authoritative PKR money pipeline | DONE | PASS | DEPLOYED | USD KPI historically | KEEP | Quote-time PKR snapshot | d14f454e | Live booking not executed | Owner retest V3 |
| W2-02 | Admin dashboard amount reconciliation | DONE | PASS | DEPLOYED | Amount unavailable historically | KEEP | amount_display + PKR presenter | d14f454e | — | Owner retest V3 |
| W2-03 | Users vs Staff semantics | DONE | PASS | DEPLOYED | Management incomplete historically | KEEP | Users/Staff + RBAC write | 589e7089 | — | Owner retest V3 |
| W2-04 | Compact Users table | DONE | PASS | DEPLOYED | — | KEEP | — | — | — | — |
| W2-09 | Settings | DONE | PASS | DEPLOYED | Metadata only historically | KEEP | Org/API/notifications JSON | 589e7089 | No credential rotation QA | Owner retest V3 |
| W2-11 | CMS | DONE | PASS | DEPLOYED | Incomplete fields historically | KEEP | Structured blocks + public draft preview | 589e7089 | — | Owner retest V3 |
| W2-13 | Compact filters | DONE | PASS | DEPLOYED | Noisy Agents historically | KEEP | Compact Agents bar | ed57f078 | — | Owner retest V3 |
| W2-15 | Markup Management | DONE | PASS | DEPLOYED | Hardcoded selectors historically | KEEP | Authoritative lookups + flight applies_to | 589e7089 | No live pricing QA | Owner retest V3 |
| W2-20 | Regression | DONE | 175 Laravel + tsc/lint | DEPLOYED | — | KEEP | Cms overlay + Admin package | 589e7089 | — | Owner retest V3 |
| W2-24 | Module matrix | DONE | DOC | VERIFIED | Fake ops pages historically | KEEP | Matrices | 589e7089 | — | Owner retest V3 |
| W2-25 | Financial source of truth | DONE | PASS | DEPLOYED | USD + snapshot policy | KEEP | — | 0860c212 | — | Owner retest V3 |
| W2-33 | CMS operational | DONE | PASS | DEPLOYED | Structured homepage + pages | KEEP | Public layout draft preview | 589e7089 | — | Owner retest V3 |
| W2-36 | Admin management matrix | DONE | DOC | VERIFIED | — | KEEP | — | 589e7089 | — | Owner retest V3 |
| W2-37 | Cross-portal matrix | DONE | DOC | VERIFIED | — | KEEP | 5-actor smoke | — | Probe `/agent/dashboard/bookings` 403 | Owner retest V3 |
| W2-26 | Full markup management | DONE | PASS | DEPLOYED | Write UI | KEEP | No prod money mutation | 589e7089 | Safety | Owner retest V3 |
| W2-27 | Applications vs Agents | DONE | — | DEPLOYED | Selected-only actions | KEEP | — | a8a7c527 | — | Owner retest V3 |
| W2-28 | Agents compact filters | DONE | — | DEPLOYED | Compact bar | KEEP | — | ed57f078 | — | Owner retest V3 |
| W2-29 | Supplier vs API | DONE | PASS | DEPLOYED | Registry SoT | KEEP | Backend provider catalog | 589e7089 | No prod rotate | Owner retest V3 |
| W2-30 | API connection mgmt | DONE | PASS | DEPLOYED | Advanced/Audit/Base URL | KEEP | Provider catalog from registry | 589e7089 | No prod rotate | Owner retest V3 |
| W2-31 | Sabre capability truth | DONE | — | DEPLOYED | GDS/NDC labels | KEEP | Cancellation gates preserved | 8d79f0c7 | — | Owner retest V3 |
| W2-32 | Profile + org | DONE | — | DEPLOYED | Photo + org | KEEP | — | bf5e9cdb | — | Owner retest V3 |
| W2-34 | Users/Staff/RBAC | DONE | PASS | DEPLOYED | Searchable agency/user pickers | KEEP | Dual-read preserved | 589e7089 | — | Owner retest V3 |
| W2-35 | Nav active state | DONE | — | DEPLOYED | most-specific href | KEEP | — | 8d79f0c7 | — | Owner retest V3 |
