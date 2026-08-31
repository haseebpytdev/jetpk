# Auth matrix (R6)

## Identities (controlled demo QA — credentials not recorded)
| Role | Identity available | Login method |
|---|---|---|
| Customer | YES (`customer@ota.demo`) | Laravel JSON login (`Accept: application/json`, maxRedirects 0) |
| Agent Owner | YES (`agent@ota.demo`) | same |
| Staff | YES (`staff@ota.demo`) | same |
| Admin | YES (`admin@ota.local`) | same |

JP-DASH-03 vault identities exist locally but production passwords were out of sync (422); demo QA identities used instead.

## Portal matrix
See `portal-responsive-matrix.json` / `auth-matrix.json`.
MATRIX_FAIL=0, MATRIX_BLOCKED=0 for authenticated role landings.

## Staff permission leak
Hidden admin modules not exposed on staff routes during responsive audit.
STAFF_RESPONSIVE_PERMISSION_LEAK=0

## Nav
DASHBOARD_NAV_OVERLAP=0
DASHBOARD_NAV_MOBILE=PASS
DASHBOARD_NAV_TABLET=PASS
