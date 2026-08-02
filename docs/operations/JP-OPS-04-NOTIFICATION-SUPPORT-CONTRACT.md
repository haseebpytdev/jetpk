# JP-OPS-04 Notification & Support Contract

## Notifications — INTENTIONALLY_UNAVAILABLE

`AgentPortalNotificationPresenter` returns stub payload:

| Field | Value |
|-------|-------|
| `available` | `false` |
| `unread_count` | `0` |
| `message` | Honest unavailable copy |

Nav item `notifications` remains in `capabilities.navigation` for discoverability; dashboard does not show fake unread counts when `notifications_available` is false.

Mark-read endpoints exist in routes but return 501 or stub — no inbox backend in JP-OPS-04.

## Support — CONNECTED

| Action | JSON endpoint | Notes |
|--------|---------------|-------|
| List tickets | `GET /agent/support/tickets?format=json` | Paginated |
| Create form | `GET /agent/support/tickets/create?format=json` | Categories, linked bookings |
| Create | `POST /agent/support/tickets?format=json` | Turnstile when required |
| Detail | `GET /agent/support/tickets/{ref}?format=json` | Conversation thread |
| Reply | `POST .../reply?format=json` | Customer-visible messages only |

Requires `support.manage` + `platform.module:agent_support`.

Gate: existing `SupportTicketPolicy` — agents see agency-scoped tickets only. No staff-private notes in agent JSON.

## Agency profile (supporting context)

`GET /agent/agency?format=json` — read-only agency details for support reference; edit via owner-only `PATCH /agent/agency`.

CRM features (lead pipeline, contact history) are **deferred**.
