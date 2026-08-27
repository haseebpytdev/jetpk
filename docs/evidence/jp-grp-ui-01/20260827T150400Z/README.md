# JP-GRP-UI-01 — Checkout auth / Book Now evidence

**Host:** https://jetpakistan.pk  
**Captured:** 2026-08-27T15:10Z (UTC)  
**Engineering SHA:** `996c7e6862387819582d84f30be850507356652f`  
**Public BUILD_ID:** `GCSJqpnr4it7E45lgaIay`  
**Backup:** `jp-grp-ui-01-20260827T145929Z`

## Root cause fixed this pass

Live `/groups/package/ALH-*` returned 404 while numeric `/groups/package/{id}` returned 200 with `available:true`.

Production `route:cache` skips `Route::bind` closures in `routes/web.php`. Next detail pages call `/groups/package/{public_id}`, so Book Now never rendered.

**Fix:** `GroupInventory::resolveRouteBinding` (+ `GroupBooking`) — route-cache safe.

## Live safe assertions (this capture)

| Check | Result |
|---|---|
| `GET /groups/package/ALH-3278?format=json` | HTTP 200, `available=true` |
| Group detail Book Now visible | PASS |
| Anonymous Book Now → login modal | PASS |
| Wrong-password error in modal | PASS |
| Mobile modal layout | PASS |
| Anonymous `GET …/passengers?format=json` | HTTP 401 |
| Anonymous HTML passengers | 302 → `/login` |
| Real payment | NOT_RUN_SAFETY |
| Real Al-Haider booking | NOT_RUN_SAFETY |
| `GROUP_BOOKING_ENABLED` | FALSE |
| `GROUP_RESERVATION_ENABLED` | FALSE |

## Screenshots (no secrets)

- `groups-landing.png`
- `groups-search-results.png`
- `group-detail-book-now.png`
- `anonymous-book-now-login-modal.png` (QA email + intentional wrong password only)
- `login-modal-error.png`
- `mobile-login-modal.png`

## Deferred (needs owner test login)

- Successful live login + post-login resume to passengers
- Authenticated checkout form / summary / payment UI screenshots
- Admin group payment review screenshot

Do not use browser autofill credentials in evidence.
