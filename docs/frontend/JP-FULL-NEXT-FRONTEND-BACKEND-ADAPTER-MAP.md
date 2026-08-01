# JP-FULL-NEXT-FRONTEND-BACKEND-ADAPTER-MAP

Phase: **JP-FULL-NEXT-FRONTEND-01C**  
All adapters verified unchanged; presentation frozen at accepted baseline.

| Domain | Adapter location | Laravel contract |
|---|---|---|
| Session | `services/session.ts`, `features/auth/services/session-service.ts` | `GET /api/public/auth/session` |
| CSRF | `features/auth/utils/laravel-auth-api.ts` | `GET /api/public/content/csrf-token` |
| Homepage/CMS | `services/homepage-content.ts`, `features/public-content/services/*` | `homepage`, `pages/{key}`, `cms/{slug}`, `custom/{slug}` |
| Search | `services/flight-search.ts`, `features/search` | Search payload + `/flights/results/*` |
| Results/return | `features/flight-results` | `GET /flights/results/*`, `POST /flights/select-return-combo` |
| Fare selection | `features/flight-details` | `GET /flights/results/offer`, `POST /flights/results/revalidate-offer` |
| Booking/payment | `features/standard-booking` | Booking session APIs; AbhiPay handoff |
| Groups | `features/group-ticketing` | Group search/booking APIs |
| Customer portal | `features/customer-dashboard` + `requireCustomerPortalAccess` | Customer dashboard APIs |
| Agent portal | `features/agent-dashboard` + `requireAgentPortalAccess` | Agent dashboard APIs |

Presentation swapped; operational contracts unchanged.
