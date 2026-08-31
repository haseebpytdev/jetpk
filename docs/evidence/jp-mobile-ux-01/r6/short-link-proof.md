# Short-link proof (flight)

## Routes
- Resolve: `GET /f/{code}` → `share.flight`
- Create: `POST /api/public/share/flight`

## States proven (final runtime)
| State | Code | Result |
|---|---|---|
| Valid | KTX4DSXK | Redirects to results; overflow=NO |
| Expired | FEXPIRER6 | Expired branded page |
| Invalid | NOTREAL99 | Invalid 404 page |

MOBILE_FLIGHT_SHORT_LINK=PASS
FLIGHT_SHORT_LINK_VALID=PASS
FLIGHT_SHORT_LINK_EXPIRED=PASS
FLIGHT_SHORT_LINK_INVALID=PASS
