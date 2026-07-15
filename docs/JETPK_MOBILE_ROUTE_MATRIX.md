# JetPakistan Mobile Route Matrix

**Date:** 2026-07-15  
**Toggle:** `OTA_MOBILE_APP_THEME` (`default-mobile` | `jetpakistan-app`)  
**Shell gate:** `MobileViewPreference::shouldUseMobileShell()` + `config/ota-mobile.mobile_pages`

## Resolution chain

```
Request → mobile_pages[key] && (cookie ota_view_mode=mobile || mobile UA)
       → controller returns view('mobile.*')
       → @extends(client_layout('mobile-app', 'mobile'))
       → themes.mobile.{app_theme}.layouts.mobile-app
       → default-mobile: @extends('layouts.mobile-app')
       → jetpakistan-app: full theme shell + app.css
```

## Public journeys

| Journey | Route name | Mobile view | `mobile_pages` | Health audit |
|---|---|---|---|---|
| Home / search | `home`, `flights.search` | `mobile.home` | ✅ | OK (public home) |
| Results | `flights.results` | `mobile.flights.results` | ✅ | OK (flights results) |
| Details | `flights.details` | `mobile.flights.details` | ✅ | OK (offer details) |
| Passengers | `booking.passengers` | `mobile.bookings.passengers` | ✅ | — (checkout; audit uses fixtures) |
| Review | `booking.review` | `mobile.bookings.review` | ✅ | — |
| Confirmation | `booking.confirmation` | `mobile.bookings.confirmation` | ✅ | — |

## Auth journeys

| Journey | Route name | Mobile view | `mobile_pages` |
|---|---|---|---|
| Login | `login` | `mobile.auth.login` | ✅ |
| Register | `register` | `mobile.auth.register` | ✅ |
| Forgot password | `password.request` | `mobile.auth.forgot-password` | ✅ |
| Reset password | `password.reset` | `mobile.auth.reset-password` | ✅ |

## Customer portal

| Journey | Route name | Mobile view | Health audit |
|---|---|---|---|
| Dashboard | `customer.dashboard` | `mobile.dashboard.customer` | OK |
| Bookings list | `customer.bookings.index` | `mobile.customer.bookings.index` | OK |
| Booking detail | `customer.bookings.show` | `mobile.customer.bookings.show` | OK (403 fixture) |
| Support index | `customer.support.tickets.index` | `mobile.customer.support.index` | — |
| Support create | `customer.support.tickets.create` | `mobile.customer.support.create` | — |
| Support show | `customer.support.tickets.show` | `mobile.customer.support.show` | — |

## Agent portal

| Journey | Route name | Mobile view | Health audit |
|---|---|---|---|
| Dashboard | `agent.dashboard` | `mobile.dashboard.agent` | OK |
| Bookings list | `agent.bookings.index` | `mobile.agent.bookings.index` | OK |
| Booking create | `agent.bookings.create` | `mobile.agent.bookings.create` | OK |
| Booking show | `agent.bookings.show` | `mobile.agent.bookings.show` | OK |
| Wallet | `agent.wallet.show` | `mobile.agent.wallet.show` | — |
| Ledger | `agent.ledger.index` | `mobile.agent.ledger.index` | — |
| Deposits | `agent.deposits.index` | `mobile.agent.deposits.index` | — |
| Staff | `agent.staff.index` | `mobile.agent.staff.index` | — |
| Support | `agent.support.tickets.index` | `mobile.agent.support.index` | — |

## Theme resolution test matrix

| `OTA_MOBILE_APP_THEME` | Resolved layout | Desktop theme impact |
|---|---|---|
| unset / `default-mobile` | `themes.mobile.default-mobile.layouts.mobile-app` → `layouts.mobile-app` | None |
| `jetpakistan-app` | `themes.mobile.jetpakistan-app.layouts.mobile-app` | None |
| invalid value | Falls back to `default-mobile` | None |

## Viewport QA coverage

| Viewport | Local screenshot | Pages captured |
|---|---|---|
| 390×844 | ✅ | home, login |
| 430×932 | ✅ | home, login |
| 768×1024 | ✅ | home, login |

**Note:** Full booking/agent/customer journey screenshots require authenticated fixtures; proposed specs in `tests/proposed-safe-tests/` are stubbed pending fixture wiring. Post-deploy staging pass required for revenue-path surfaces (results → confirmation, wallet, ledger).

## Known gaps

- `mobile.auth.login-otp` listed in MA-0 matrix but no Blade file in repo
- Proposed Playwright specs (`mobile-app-shell.spec.ts` etc.) skip until customer/agent login helpers are wired
- Production `https://jetpakistan.pk` does not reflect this integration until SFTP deploy + `OTA_MOBILE_APP_THEME=jetpakistan-app` on server
