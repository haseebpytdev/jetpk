# JP-OPS-03 Notification & Support Contract

## Notifications — INTENTIONALLY_UNAVAILABLE

`CustomerPortalNotificationPresenter` returns `available: false`, `unread_count: 0`. Dashboard hides unread metric when `notifications_available` is false.

## Support — CONNECTED

- List/create/detail/reply/close via JSON
- `close_url` wired in Next support detail (`PATCH` via POST + `_method`)
- Customer-visible messages only; no staff-private notes
