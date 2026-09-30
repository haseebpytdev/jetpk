# Current vs Finalized Operational Dashboard Matrix

```text
START_MAIN=33d70f722e7eefbe5a574ef836695345956f7b8f
START_PRODUCTION_SOURCE_SHA=bc37a9067720daa144509d0f3bc381497120e62c
HISTORICAL_REFERENCE_SHA=0ebb2278a436f9367266cfe11e50100c7369704b
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
```

| MODULE | HISTORICAL_FINAL_BEHAVIOR | CURRENT_BEHAVIOR | CLASSIFICATION | ROOT_CAUSE | RECOVERY_ACTION | AUTOMATED_TEST | VISIBLE_UAT | PRODUCTION | COMMIT | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|
| Admin Customers list/detail | Next + Laravel JSON; profile `country_code`; list/detail aggregates | Deployed + owner-visible headed Chrome PASS | FULL_MANAGEMENT | Wrong column + global bind collided with detail ID | Restore `country_code`, detail aggregates, rename route param | DashboardCustomersJsonContractTest PASS (4/48) | PASS | `4f3aec30` | `4f3aec30` | PASS |
| Admin My Profile | Editable phone/city/country; safe write | Next `/admin/dashboard/profile` missing expected controls; Blade `/profile` has fields | PARTIAL_BY_DOMAIN | Source/runtime contract mismatch | Trace Next live-mode + Profile JSON hydration | ProfileJsonContractTest (existing) | PENDING | PENDING | — | OPEN |
| Users directory | Live platform directory; compact table | Synthetic preview copy in component | BROKEN | Preview semantics in live mode | Remove preview copy; wire live API | PENDING | PENDING | PENDING | — | OPEN |
| Staff management | Create/edit/roles/permissions | Mostly GET/search/KPIs | PARTIAL_BY_DOMAIN | Write UI not recovered | Restore against Laravel staff domain | PENDING | PENDING | PENDING | — | OPEN |
| Roles & Permissions | Full RBAC write panel | Write panel missing | BROKEN | Lost Next module | Surgical restore rbac write UI | PENDING | PENDING | PENDING | — | OPEN |
| Markups | Business rule builder + lookups | Generic name/type/value form | PARTIAL_BY_DOMAIN | Lost builder UX | Restore builder against current Laravel | PENDING | PENDING | PENDING | — | OPEN |
| Support | Ops + default page size 10 | Simplified; pagination helper missing | PARTIAL_BY_DOMAIN | Lost pagination/ops depth | Restore pagination + ops actions | PENDING | PENDING | PENDING | — | OPEN |
| Commissions | KPI + pending review queue | Module missing | BROKEN | Lost Next workspace | Restore against current commission domain | PENDING | PENDING | PENDING | — | OPEN |
| Notification Failures | Operational failure workspace | Missing | BROKEN | Lost Next workspace | Restore read-only ops view | PENDING | PENDING | PENDING | — | OPEN |
| Live Operations | Inbox/events/assigned work | Missing | BROKEN | Lost Next panel | Restore against current ops APIs | PENDING | PENDING | PENDING | — | OPEN |
| Execution / Cancellations | Operational queues | Nav absent | BROKEN / NEEDS_PROOF | Nav/routes drift | Audit operations routes; restore queues | PENDING | PENDING | PENDING | — | OPEN |
| Booking management | Single action panel + amendments | Detail recovered; ops depth TBD | PARTIAL_BY_DOMAIN | Incomplete ops contract | Audit lifecycle actions | PENDING | PENDING | PENDING | — | OPEN |
| Agent Applications | Admin review workspace | Missing Next workspace | BROKEN | Lost Next module | Restore if Laravel domain current | PENDING | PENDING | PENDING | — | OPEN |
| CMS / Media | Full block builder + media library | Pages/Homepage restored; depth TBD | PARTIAL_BY_DOMAIN | Media/builder absent or superseded | Functional parity audit | PENDING | PENDING | PENDING | — | OPEN |
| Reports | Live authoritative data | Needs live-mode audit | NEEDS_PROOF | Possible preview fallback | Verify no preview in live | PENDING | PENDING | PENDING | — | OPEN |
| Settings | GENERAL/SECURITY/NOTIFICATIONS/INTEGRATIONS | Newer OTP/AI present; IA TBD | PARTIAL_BY_DOMAIN | Mixed old/new | Reconcile IA; keep newer controls | PENDING | PENDING | PENDING | — | OPEN |
| API Connections | Multi-tab full management | Cards load; depth TBD | NEEDS_PROOF | Depth not owner-proven | Deep tab UAT | PENDING | PENDING | PENDING | — | OPEN |
| Customer Queries | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| SEO | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Group Ticketing | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Login OTP settings | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Ask JetPakistan settings | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Company Profile / Homepage CMS | Current implementations | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Agent / Agent Staff portals | Operational portals | Nav UAT passed; action-level TBD | PARTIAL_BY_DOMAIN | Shallow UAT only | Action-level UAT | PENDING | PENDING | PENDING | — | OPEN |
| Customer portal | Operational portal | Nav UAT passed; action-level TBD | PARTIAL_BY_DOMAIN | Shallow UAT only | Action-level UAT | PENDING | PENDING | PENDING | — | OPEN |
| Go-live / System Health | Present / policy | Present / TBD | READ_ONLY_BY_DESIGN | Policy | Classify explicitly | PENDING | PENDING | PENDING | — | OPEN |

Status legend: `OPEN` | `IN_PROGRESS` | `PASS` | `PRESERVE` | `BLOCKED`.
