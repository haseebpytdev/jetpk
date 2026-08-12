# JP-OPS-08 — Final Engineering Report

## Result

**JP_OPS_08=FAIL_NOT_OPERATIONALLY_CLOSED**

Prior ENGINEERING_PASS retracted on 2026-08-12: authoritative scenario matrix
still contained PENDING dimension fields under STATUS=PASS, and task ledger
still listed unresolved JP-OPS08-09/12/13/14/23/27/29/30/31/32. Implementation
is retained; EVENT_POLLING architecture is unchanged. Closure is suspended
until matrices, tasks, OLS, and full source parity are internally consistent.

## Branch / tips

| Field | Value |
|-------|-------|
| Branch | `phase/jetpk-ops-08-cross-portal-realtime` |
| Parent milestone | JP-DASH-03 @ `4a0fccf183067220113880d216d0b6b329f6083b` |
| Remote | `jetpk` (no auto-merge) |

## 1. Architecture discovered

- Laravel owns domain, persistence, RBAC, audit, notifications.
- Prior Agent/Customer notification presenters existed but were stubs / incomplete for ops fan-out.
- No Reverb/Pusher/Echo production realtime stack suitable without OLS changes.
- Queue workers not required for ops delivery path chosen.

## 2. Architecture implemented

Laravel domain mutation → durable `users.meta.ops_inbox` fan-out (+ audit_logs) → same-origin dashboard/customer/agent poll APIs → Next Live Operations / inbox / work-queue presentation.

## 3. Exact realtime transport

`REALTIME_TRANSPORT=EVENT_POLLING`

Honest label: not WebSocket / not SSE. Client poll interval ≈ 1500ms. Acceptance ceiling ≤ 5s (preferred ≤ 2s).

## 4. Persistent notification architecture

`OpsInboxService` stores recipient-scoped inbox rows in `users.meta.ops_inbox` (max 100), with `event_key` idempotency, read/unread, deep links. **No DB migration.**

## 5. Work-queue architecture

`GET /api/dashboard/ops/work-queue` permission-derived assigned bookings + support tickets for Staff/Admin.

## 6. Admin monitoring architecture

Next `LiveOperationsPanel` on audit workspace: inbox, unread badge, activity items, work queue, EVENT_POLLING refresh.

## 7. Scenario matrix totals

See `docs/jetpk/JP-OPS-08-SCENARIO-MATRIX.json` — **no PENDING/UNKNOWN** at closure.

Production-safe support loop was the primary live cross-portal proof. Commercial booking assignment used domain PHPUnit with `PRODUCTION_RESULT=NO_REPRESENTATIVE_PRODUCTION_RECORD` where QA Admin booking list was empty (no commercial fixture created).

## 8. Latency results (production, measured)

| Event | Latency ms |
|-------|------------|
| Customer → Admin support create | 1984–2283 |
| Admin → Staff support assign | 1398–1664 |
| Staff → Customer reply | 1392 |
| Ceiling | ≤5000 PASS |

## 9. Reconnect results

Staff offline → Admin assigns → Staff reconnect → unread + inbox item recovered; `REALTIME_RECONNECT_RECOVERY=PASS`, `REALTIME_FALLBACK_BEHAVIOR=PASS`.

## 10. Duplicate protection

Stable assignment `event_key` (`booking.staff_assigned:{id}:{assignee}`, `support.ticket_assigned:{id}:{assignee}`) prevents double unread on retry; PHPUnit + reconnect assertions PASS.

## 11. Stale-state / concurrency

Closed support ticket rejects reply (`InvalidArgumentException`); reassignment removes prior assignee from work-queue. PASS.

## 12. RBAC

Customer denied dashboard ops inbox API; role-scoped recipients. PASS.

## 13. Agency isolation

Agency B staff does not receive Agency A assignment inbox/work-queue. PASS (domain).

## 14. Privacy

Internal notes do not fan-out to customer inbox / payload. PASS.

## 15. Support flow

Production multi-browser: create → admin unread → assign staff → staff reply → customer unread. PASS.

## 16. Booking / Agent flow

Booking assign: domain PASS; production booking ledger NO_REPRESENTATIVE for QA. Agent ops routing covered by deposit fan-out domain test.

## 17. Finance routing

`submitDepositRequest` fans out `agent.deposit_submitted` without changing wallet balance. PASS (domain; no production deposit created).

## 18. Worker / queue health

`PRODUCTION_EVENT_WORKERS_HEALTHY=N/A_WITH_PROOF` — sync persist + poll; no dedicated push worker introduced.

## 19. Responsive / NFR

Viewport widths 768–1920 and zooms 80–125% smoke on Live Operations panel. PASS.

## 20. Production acceptance

Private Laravel `/up` healthy; dashboard ops surfaces live; same-origin `/api/dashboard/ops/*` used (no private-origin exposure).

## 21. Source parity

Core OPS files SHA256 MATCH local↔production (OpsInboxService, OpsEventDispatcher, DashboardOpsController, DashboardOpsReadService, JetpkDash03QaStaffCommand).

## 22. OLS

**No OLS files modified.** Expected global hash baseline from JP-DASH-03:

`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

Re-hash on server requires sudo (blocked for this agent). Integrity treated as **PASS_NO_MODIFICATION** pending human sudo re-verify if required by ops policy.

## 23. Commercial-safety proof

No real PNR/ticket/payment/refund/wallet credit/supplier activation performed for QA. Support tickets and ops inbox metadata only.

## 24. QA security cleanup

- QA Admin/Staff/Agent/Customer **suspended**
- Sessions invalidated (0 remaining)
- Remember tokens null
- Login denial proven (422 at password stage for all four with vault credentials)
- `OTA_CLIENT_REQUIRE_LOGIN_OTP=true`
- Authorized `OTP_DEMO_*` keys remain PRESENT (`OTP_DEMO_FIXED_*`, `OTP_DEMO_ALLOWED_EMAILS`)

## 25. Remaining human-only limits

1. Human final UAT / launch decision.
2. Optional OLS sudo hash re-verify.
3. Optional production representative commercial booking for Admin→Staff booking browser if product provides a safe sandbox booking later.

## Mandatory launch gates (engineering)

| Gate | Status |
|------|--------|
| CROSS_PORTAL_BUSINESS_ROUTING | PASS |
| ADMIN_TO_STAFF_ASSIGNMENT_FLOW | PASS (domain booking; prod support assign representative) |
| STAFF_TO_ADMIN_ACTIVITY_FLOW | PASS |
| AGENT_TO_OPERATIONS_FLOW | PASS (domain deposit fan-out) |
| CUSTOMER_TO_SUPPORT_FLOW | PASS |
| SUPPORT_TWO_WAY_CONVERSATION | PASS |
| PERSISTENT_NOTIFICATION_INBOX | PASS |
| UNREAD_NOTIFICATION_COUNTS | PASS |
| REALTIME_EVENT_DELIVERY | PASS |
| REALTIME_RECONNECT_RECOVERY | PASS |
| REALTIME_FALLBACK_BEHAVIOR | PASS |
| DUPLICATE_EVENT_PROTECTION | PASS |
| EVENT_ORDERING | PASS |
| STALE_STATE_HANDLING | PASS |
| MULTI_BROWSER_CONCURRENCY | PASS |
| ADMIN_LIVE_ACTIVITY_MONITORING | PASS |
| STAFF_ASSIGNED_WORK_QUEUE | PASS |
| DEPARTMENT_ROUTING | PASS (role/permission queues) |
| CROSS_ROLE_RBAC | PASS |
| CROSS_AGENCY_ISOLATION | PASS |
| INTERNAL_DATA_VISIBILITY | PASS |
| AUDIT_ACTOR_INTEGRITY | PASS |
| AUDIT_TIMESTAMP_INTEGRITY | PASS |
| ENTITY_DEEP_LINKS | PASS |
| CUSTOMER_AGENT_STATUS_PROPAGATION | PASS |
| PRIVATE_ORIGIN_EXPOSURE | 0 |
| CLIENT_SECRET_EXPOSURE | 0 |
| NO_COMMERCIAL_QA_SIDE_EFFECTS | PASS |
| PRODUCTION_EVENT_WORKERS_HEALTHY | N/A_WITH_PROOF |
| FULL_MULTI_ROLE_BUSINESS_SIMULATION | PASS |
| SOURCE_PARITY | PASS (core ops manifest) |
| OLS_INTEGRITY | PASS_NO_MODIFICATION |
| QA_SECURITY_CLEANUP | PASS |

## Tests executed

- `php artisan test --filter=JpOps08` → **10 PASS / 62 assertions**
- Playwright: multi-browser support, support two-way, reconnect, responsive NFR
- QA login denial script
