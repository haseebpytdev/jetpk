# JP-OPS-03 Deferred Runtime Dependencies

| Dependency | Impact | Phase |
|------------|--------|-------|
| In-app notification backend | Notifications remain `available: false` | Future |
| Customer refund request intake | Read-only refund display only | Staff process / future |
| Live OTP provider | Demo OTP patch preserved | External runtime |
| Live Sabre cancellation | Staff processing only | JP-OPS-06+ |
| Production payment capture | Display only | JP-OPS-06 |
| Email/SMS notification delivery | Not in scope | External |

No production deploy, queue, or server configuration in JP-OPS-03.
