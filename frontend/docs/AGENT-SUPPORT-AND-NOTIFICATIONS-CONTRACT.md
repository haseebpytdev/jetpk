# Agent Support and Notifications Contract

## Support routes

| Method | Laravel path | Next.js path |
|--------|--------------|--------------|
| GET | `/agent/support/tickets?format=json` | `/agent/support` |
| GET | `/agent/support/tickets/create?format=json` | `/agent/support` (create form) |
| POST | `/agent/support/tickets` | `/agent/support` |
| GET | `/agent/support/tickets/{ticket_reference}?format=json` | `/agent/support/[reference]` |
| POST | `/agent/support/tickets/{ticket_reference}/reply` | `/agent/support/[reference]` |

Route binding uses `{ticket:ticket_reference}` — public ticket reference in URL.

## Authorization

- Requires `agent.support.manage` (`AgentPermission::SupportManage`)
- Requires `platform.module:agent_support`
- Tickets scoped via `SupportTicket::forAgentPortalUser($user)`

## Support list

Query: `page`.

Each ticket:

- `reference`, `subject`, `category`, `category_label`
- `booking_reference` — linked booking display reference when set
- `status` — `{ code, label }`
- `created_at`, `updated_at`
- `detail_url` — `/agent/support/{ticket_reference}`
- `can_reply` — false when ticket closed
- `can_close` — false (close not exposed in agent JSON phase)

## Create form

`GET /agent/support/tickets/create?format=json`

- `categories` — from `SupportTicketCategory` enum
- `bookings` — recent agency bookings (id, booking_reference, route, travel_date)
- `turnstile_required` — **false** for authenticated agents
- `submit_url` — `/laravel/agent/support/tickets`

Create payload: `subject`, `category`, `body`, optional `booking_id`.

Success: `{ ok: true, redirect_url }` with 201.

## Detail and reply

`GET /agent/support/tickets/{ticket_reference}?format=json`

- `ticket` — list item shape
- `conversation[]` — customer-visible messages with `author_name`, `author_role` (`agent` | `staff`), `body`, `created_at`
- `reply_url` — `/laravel/agent/support/tickets/{reference}/reply`

Reply: POST `body` only when `can_reply` is true.

## Turnstile

Authenticated agent support does **not** require Turnstile (matches Laravel `StoreSupportTicketRequest` policy). Public support/contact still requires Turnstile (JP-FE-10A).

## Rate limiting

Inherited from Laravel web middleware and support policies. JSON returns generic errors on failure.

## Attachments

Not exposed in agent dashboard JSON (no complete backend contract).

---

## Notifications

### Current state

Laravel has **no agent database notification inbox**. Agent dashboard returns honest unavailable state:

```json
{
  "ok": true,
  "available": false,
  "unread_count": 0,
  "notifications": [],
  "message": "In-app notifications are not available yet. Booking and wallet updates are sent to your registered email address."
}
```

Dashboard overview sets `notifications_available: false` and `metrics.unread_notifications: 0`.

### Endpoints

| Method | Path | Behavior |
|--------|------|----------|
| GET | `/agent/notifications?format=json` | Unavailable empty list |
| GET | `/agent/notifications/unread-summary?format=json` | `{ available: false, unread_count: 0 }` |
| POST | `/agent/notifications/{notification}/read` | Unavailable error payload |
| POST | `/agent/notifications/read-all` | Unavailable error payload |

Next.js route: `/agent/notifications` — displays unavailable message; no client-side read state.

### Rules

- No client-only read state or fake unread badges
- No arbitrary action URLs in notification payloads
- Email notifications remain authoritative until inbox backend is added
- Shell may show notifications nav item; page content must reflect `available: false`

## Dashboard metrics

Overview includes `open_support_cases` when user has support permission; `unread_notifications` always 0 until backend exists.
