# R3 dashboard onboarding matrix

| ROLE | AUTO_FIRST_USE | CORRECT_STEPS | PERMISSION_AWARE | COMPLETE/SKIP | MANUAL_RESTART | RESPONSIVE | ACCESSIBLE | PUBLIC_LEAK | FINAL |
|---|---|---|---|---|---|---|---|---|---|
| Customer | Proven (skip persisted) | Bookings/travelers/support oriented | N/A | SKIP persisted | PASS | Smoke OK | Dialog + Skip/Next | NO | PASS |
| Agent | PASS | Agent portal welcome; no Admin | YES | Restart works | PASS | Smoke OK | Dialog semantics | NO | PASS |
| Staff | PASS | “only areas you can access” (11 steps) | PASS | Restart available | PASS | Smoke OK | Dialog | NO | PASS |
| Admin | PASS | Includes API Settings step | YES | Restart available | PASS | Smoke OK | Dialog | NO | PASS |

```
WIZARD_STATE_AUTHORITY=users.meta.dashboard_tours (server)
WIZARD_VERSIONING=*_dashboard_tour_v1
REDUCED_MOTION_SUPPORTED=PASS (CSS prefers-reduced-motion; static character)
MISSING_TARGET_SAFE=PASS (catalog skips absent targets)
PUBLIC_HOME/SEARCH/BOOKING_WIZARD=NO
LOGIN_PAGE_DASHBOARD_WIZARD=NO
PUBLIC_FLIGHT_FLOW_WIZARD_BUNDLE_REGRESSION=NO (lazy hosts only in portal shells)
```
