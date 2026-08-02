# JP-OPS-04 API Error State Matrix

Agent portal uses JP-OPS-02 `laravelRequest` via `agent-dashboard-api.ts`.

| HTTP | Code | Agent UI |
|------|------|----------|
| 401 | `unauthorized` | Session expired — sign in again |
| 403 | `forbidden`, `agency_inactive`, `staff_inactive`, `permission_required` | Permission denied / session unusable |
| 404 | `not_found` | Record unavailable |
| 409 | `cancellation_already_requested`, `staff_already_exists`, `conflict` | Server message (duplicate request) |
| 419 | `csrf_expired` | CSRF failure — no blind mutation replay |
| 422 | `booking_not_cancellable`, validation | Field errors or business rule message |
| 429 | `rate_limit` | Throttle message (e.g. payment-proof) |
| 5xx | `server` | Generic failure + retry affordance |

## Payload shape

Success:

```json
{ "ok": true, "...": "..." }
```

Error:

```json
{ "ok": false, "code": "...", "message": "...", "errors": { "field": ["..."] } }
```

`unwrapPayload()` in `agent-dashboard-api.ts` treats in-body `ok: false` as failure even on 200.

## Session denial codes (`AgentPortalAccess`)

| `code` | Trigger |
|--------|---------|
| `agency_inactive` | `agent.is_active = false` or missing agency |
| `staff_inactive` | User suspended/inactive |
| `permission_required` | Wrong account type, context mismatch, membership removed |

## Mutation safety

All agent mutations use `retryCsrfOnce: false` to prevent uncertain replay after CSRF rotation.

## Frontend mapper

`agentApiErrorMessage()` maps 401/403/404/409 to user-facing copy; passes through server `message` for business-rule errors.
