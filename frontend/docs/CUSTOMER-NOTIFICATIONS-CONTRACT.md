# Customer Notifications Contract

## Current state

Laravel has **no customer database notification inbox** (`notifications` table not present). Customer dashboard returns:

```json
{
  "ok": true,
  "available": false,
  "unread_count": 0,
  "notifications": [],
  "message": "In-app notifications are not available yet..."
}
```

## Endpoints

- `GET /customer/notifications?format=json`
- `GET /customer/notifications/unread-summary?format=json`
- `POST /customer/notifications/{id}/read` → 501 unavailable
- `POST /customer/notifications/read-all` → 501 unavailable

## Rules

- No client-only read state
- No arbitrary action URLs
- Email notifications remain authoritative until inbox backend is added
