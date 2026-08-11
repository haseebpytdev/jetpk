# JP-OPS-08 — Architecture Audit

**Branch:** `phase/jetpk-ops-08-cross-portal-realtime`  
**Baseline tip:** `4a0fccf183067220113880d216d0b6b329f6083b` (JP-DASH-03 closed)  
**Audit UTC:** 2026-08-11T20:25:00Z  
**REALTIME_TRANSPORT:** `EVENT_POLLING` (honest — not WebSocket/SSE)

---

## 1. Current capabilities

| Area | Status | Evidence |
|------|--------|----------|
| Email/ops notification orchestration | Present | `OtaNotificationService`, `OtaNotificationEvent`, `NotificationRecipientResolver` |
| Communication delivery log | Present | `communication_logs` |
| Agency notification settings | Present | `agency_notification_settings` |
| Booking staff assignment | Present | `bookings.assigned_staff_id`, `BookingService::assignStaff`, audit `booking.staff_assigned` |
| Support tickets + messages | Present | `support_tickets`, `support_ticket_messages`, `SupportTicketService` |
| Immutable ops audit | Present | `audit_logs` + Dashboard `GET /api/dashboard/audit` |
| Database queue tables | Present | `jobs`, `job_batches`, `failed_jobs`; default `QUEUE_CONNECTION=database` |
| User Notifiable trait | Present | `User` uses `Illuminate\Notifications\Notifiable` (unused inbox) |
| User JSON meta | Present | `users.meta` (array cast) — **reusable for inbox/read state** |
| Customer/Agent notification routes | Present (stub) | Controllers + presenters return `available: false` |
| Booking status polling (public) | Present | `useBookingStatusPoll.ts` |
| Admin booking assign UI | Present | Dashboard `booking-assign-staff` → Laravel portal JSON |

## 2. Missing capabilities (pre JP-OPS-08)

| Area | Status |
|------|--------|
| Laravel `notifications` table / DatabaseNotification | Absent |
| `app/Events`, `app/Listeners`, domain event fan-out | Absent |
| Broadcasting / Reverb / Pusher / Echo | Absent (`BROADCAST_CONNECTION=log`) |
| SSE / event-cursor APIs | Absent |
| Durable in-app inbox + unread | Absent (stubs) |
| Cross-portal realtime delivery | Absent |
| Support assign → AuditLog | Incomplete (email only) |
| Staff assigned-work surface as first-class queue | Partial (list filters exist; no live inbox) |
| Horizon / managed realtime worker for push | Absent |

## 3. Transport already present

- **Push/WebSocket/SSE:** none (OLS must not be modified for websocket upgrade).
- **Polling:** payment/booking confirmation poll; dashboard `staleAfter` freshness metadata.
- **Queued email:** `OtaNotificationService` dispatch when queue ≠ sync.

**Decision:** JP-OPS-08 uses **same-origin EVENT_POLLING** (`since_id` / inbox poll ≤1s interval for active sessions). Documented honestly — not WebSockets.

## 4. Notification persistence already present

| Store | Inbox-ready? |
|-------|--------------|
| Laravel `notifications` | No table |
| `communication_logs` | Delivery trail only (no `read_at`) |
| `audit_logs` | Activity feed yes; not per-recipient inbox |
| `users.meta` | **Yes** — durable JSON without migration |

## 5. Queue infrastructure

- Driver: database (default)
- Workers: documented Supervisor/cron; Horizon not present
- App jobs: minimal; mail often queued as closures
- JP-OPS-05: queue mutation UI intentionally unavailable
- Runtime worker hardening historically deferred to JP-RUNTIME-01

For EVENT_POLLING, **no new daemon is required** for delivery. Email queue workers remain optional for outbound mail.  
`PRODUCTION_EVENT_WORKERS_HEALTHY` = **N/A with proof** when architecture uses synchronous persistence + poll (no push worker).

## 6. Server / OLS constraints

- Private Laravel `127.0.0.1:8088` must never reach browsers.
- Expected OLS hash: `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`
- **Do not** modify OLS for websocket reverse-proxy.
- Same-origin `/laravel/*` and dashboard API remain the browser surface.

## 7. Recommended architecture

```
Admin/Staff/Agent/Customer UI
        ↓ same-origin HTTP
Laravel domain services (authoritative)
        ↓ DB transaction
audit_logs + domain rows (assignment/support)
        ↓ OpsInboxService fan-out (idempotent)
users.meta.ops_inbox[]  (per-recipient, durable)
        ↓
GET poll: ops/inbox + ops/events?since_id=
        ↓
Recipient dashboard reflects ≤5s
```

### Persistence ownership

| Concern | Owner |
|---------|-------|
| A. Notifications | `users.meta.ops_inbox` (+ event_key dedupe) |
| B. Assignments / work queue | `bookings.assigned_staff_id`, `support_tickets.assigned_to_user_id` |
| C. Activity / audit | `audit_logs` |
| D. Read/unread | `ops_inbox[].read_at` in `users.meta` |
| E. Event cursor | client `since_id` over `audit_logs.id` |

### Migration decision

**Schema migration NOT required** for JP-OPS-08 gates.

Rationale:

1. Assignment and support domain tables already exist.
2. `audit_logs` already records booking assignment and can record support ops.
3. `users.meta` already stores durable per-user JSON (`staff_permissions`, etc.).
4. Inbox fan-out + `read_at` fit meta without a new table for launch-critical persistence.
5. A dedicated `notifications` table would be preferable at scale but is **not unavoidable** for acceptance.

If meta inbox size or concurrency later becomes a product bottleneck, a future phase may introduce Laravel notifications with an explicit migration hard-gate.

## 8. Decision rationale

| Option | Rejected/Accepted | Why |
|--------|-------------------|-----|
| Reverb/WebSocket | Rejected for this phase | Requires OLS/proxy changes → hard stop |
| SSE | Deferred | Needs long-lived OLS streaming config risk |
| EVENT_POLLING | **Accepted** | Same-origin, no OLS change, ≤5s achievable |
| New `notifications` migration | Avoided | Existing `users.meta` + domain/audit suffice |
| Browser-to-browser | Forbidden | Laravel remains authority |

## 9. Deployment implications

- Deploy Laravel services/controllers/routes + dashboard/frontend poll UI.
- No OLS change.
- No DB migrate.
- Restart only intended PM2 apps after Next build if UI changed.
- Queue worker restart only if email path regressions appear (not required for poll delivery).
- Source parity manifest for every uploaded file.
- Preserve OTP_DEMO_* and restore QA suspend at closure.

## 10. Highest-priority implementation order

1. `OpsInboxService` + audit hooks on assign/support mutations  
2. Dashboard ops inbox/events/work-queue read APIs + mark-read  
3. Replace Customer/Agent notification stubs with meta inbox  
4. Admin live activity poll + Staff work-queue UX  
5. Multi-browser Playwright harness + latency measurement  
6. Production-safe Support simulation + domain booking assignment tests  
7. RBAC / agency isolation / reconnect / duplicate gates  
8. Deploy, parity, OLS verify, QA cleanup
