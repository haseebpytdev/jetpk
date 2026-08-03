# JP-OPS-05 Deferred Runtime Dependencies

| Dependency | Owner phase | Notes |
|------------|-------------|-------|
| Cancellation supplier execution (`process`) | JP-OPS-06 | JSON returns `external_execution_required`; Blade gated |
| Refund settlement (`mark-paid`) | JP-OPS-06 | JSON blocked |
| Live ticketing (`issue-ticket`) | JP-OPS-06 | Blade only |
| Cancel/refund **review** Next UI (8 routes) | Future slice | Laravel JSON present; no `operational-api.ts` / dashboard UI / Playwright |
| Commission ticketing ledger (4 tests) | JP-OPS-06 | From JP-OPS-04 |
| Queue worker deploy | Ops runtime | Not in repo scope |
| CMS/settings/markup mutations | Blade fallback | Separate approval |
| Support mutation Next UI | Future slice | Blade operational |
| Agency/user management Next mutations | Future slice | Blade operational |

## Mutation binding truth (JP-OPS-05B)

| Bucket | Count |
|--------|------:|
| CONNECTED (full Next) | 6 |
| BACKEND_WITHOUT_NEXT_BINDING | 8 |
| DEFERRED | 145 |
| **Total** | **159** |

OTP demo (`OTP_DEMO_*`, `DemoFixedLoginOtpGate`) — **unchanged**.
