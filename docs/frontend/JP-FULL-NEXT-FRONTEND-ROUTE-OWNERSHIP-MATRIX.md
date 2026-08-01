# JP-FULL-NEXT-FRONTEND-ROUTE-OWNERSHIP-MATRIX

Phase: **JP-FULL-NEXT-FRONTEND-01C**
01C verification: CMS catch-all collision risk mitigated by `isReservedPublicSlug`; payment/booking/auth prefixes reserved; contact canonical at `/contact`.

| Route family | Next owner | Laravel dependency | Blade fallback | CMS collision risk |
|---|---|---|---|---|
| Public content | Next | CMS APIs | Temporary for some GETs | Low when `isReservedPublicSlug` synced |
| Auth | Next presentation | Session, CSRF, signed verify | Login/register Blade until cutover | Low |
| Flights/booking | Next | Search, booking, payment | Results Blade until cutover | **High** — payment/booking prefixes reserved |
| Customer/Agent | Next | RBAC, ownership APIs | None for portal pages | Low |
| CMS `[slug]` | Next | Custom page API | Laravel catch-all if unmatched | **Critical** |

Cutover prerequisites documented per route in [JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json](./JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json).
