# MA-0 — Mobile App Theme: Audit & Architecture

**Repository:** `haseebpytdev/jetpk` · **Baseline:** `claude/ui-master` @ `6fbfae4` (re-verified).
**Type:** audit + architecture. **No code in MA-0** (MA-1's code ships alongside — see `README-MA1.md`).
**Goal:** JetPakistan-branded, compact, fully-responsive **mobile app-style** theme, auto-loading on
mobile devices, toggleable independently of the desktop theme. **Same theme, no new features.**

## 1. Where we are today

| Fact | Evidence |
|---|---|
| **0** mobile theme overrides exist | `resources/views/themes/**` — no `mobile` area |
| **76** mobile blade files; **51** rendered by controllers; 26 partials/layouts; **0 orphans** | view tree scan + controller scan |
| All mobile views are literal `view('mobile.*')` | not theme-resolvable |
| **50** views `@extends('layouts.mobile-app')` | the **parwaaz-shared** shell |
| Shell CSS `public/css/ota-mobile-app.css` | **parwaaz-shared** |
| JetPK mobile today | **brand-coloured** (CSS vars via `BrandDisplayResolver`), **not brand-designed** |
| Shell quality | Strong: `viewport-fit=cover`, `env(safe-area-inset-*)`, `100dvh`, 23 tap-target rules ≥44px |
| Dual-shell switch | `shouldUseMobileShell()` = `config('ota-mobile.mobile_pages')[$key]` **&&** (cookie `ota_view_mode` \|\| UA regex) |

`config/client_view_paths.php` already anticipates this work: *"Mobile shell still uses mobile.home
**until a later phase**."*

## 2. Architecture decision record

| # | Decision | Rationale |
|---|---|---|
| **D1** | Add a **`mobile` area** to the resolver (`themes/mobile/{theme}`, **`legacy_prefix 'mobile'`**, fallback `default-mobile`) | `client_view('customer.bookings.index','mobile')` → theme file **if present**, else today's `mobile.customer.bookings.index`. Makes every later migration backward-compatible and independently revertable |
| **D2** | Theme key **`jetpakistan-app`**, never `jetpakistan` | `RuntimeThemeManager::resolveArea()` (L183–187) coerces *any* area to `jetpakistan` when `active_admin_theme === 'jetpakistan'` **and** `validateTheme('jetpakistan',$area)` passes. No mobile theme keyed `jetpakistan` ⇒ **coercion cannot fire** ⇒ the mobile skin stays independent — **with zero change to `RuntimeThemeManager`** |
| **D3** | Toggle = **`config('ota-mobile.app_theme')`** (env `OTA_MOBILE_APP_THEME`), read **only** by `RuntimeViewResolver::resolvedMobileTheme()` | No migration/schema risk; instantly reversible; never reads `active_*_theme` ⇒ independence by construction. Per-tenant DB column is a documented one-line upgrade |
| **D4** | `default-mobile` shim `@extends('layouts.mobile-app')` | Mirrors the proven MC-8D pattern (`default-agent` → `@extends('layouts.agent-portal')`). Default ⇒ **byte-identical output** |
| **D5** | **Never** edit `layouts/mobile-app`, `resources/views/mobile/**`, `public/css/ota-mobile-app.css` | parwaaz-shared. All JetPK work lives in `themes/mobile/jetpakistan-app/**` |
| **D6** | Keep `mobile_pages` map + `ota_view_mode` cookie unchanged | The shell/opt-in and desktop escape hatch are orthogonal to the skin |
| **D7** | New CSS gets its **own** inline asset version var (mirror `$jpPortalAssetVersion`/`$jpDashAssetVersion`) | Repo rule: bump on every asset change |

## 3. Toggle model (three independent dimensions)

| Dimension | Control | Values |
|---|---|---|
| Which shell? | `mobile_pages` map + `ota_view_mode` cookie + UA regex | mobile / desktop |
| Which desktop theme? | `ClientProfile.active_{frontend,admin,staff}_theme` | jetpakistan / default-* |
| **Which mobile skin?** | **`OTA_MOBILE_APP_THEME`** | `default-mobile` / `jetpakistan-app` |

## 4. Mobile surface matrix — 51 controller-rendered views

**Migration is mechanical:** `view('mobile.X')` → `view(client_view('X','mobile'))`, then add
`themes/mobile/jetpakistan-app/X.blade.php`. The logical name is simply the view name minus the
`mobile.` prefix — so the target filename is derivable, not invented.

### MA-3 — Public site & booking flow (highest traffic) — 15 views

| Current mobile view | Logical name → `client_view(…, 'mobile')` | Route | In `mobile_pages` | Controller |
|---|---|---|---|---|
| `mobile.agent-registration.form` | `agent-registration.form` | `—` | — | `Frontend/AgentRegistrationController@create` |
| `mobile.agent-registration.landing` | `agent-registration.landing` | `—` | — | `Frontend/AgentRegistrationController@landing` |
| `mobile.agent-registration.submitted` | `agent-registration.submitted` | `—` | — | `Frontend/AgentRegistrationController@submitted` |
| `mobile.bookings.confirmation` | `bookings.confirmation` | `—` | — | `Frontend/BookingController@confirmation` |
| `mobile.bookings.passengers` | `bookings.passengers` | `—` | — | `Frontend/BookingController@passengers` |
| `mobile.bookings.review` | `bookings.review` | `—` | — | `Frontend/BookingController@review` |
| `mobile.customer.bookings.guest-show` | `customer.bookings.guest-show` | `—` | — | `Frontend/GuestBookingLookupController@showGuestBooking` |
| `mobile.customer.profile.edit` | `customer.profile.edit` | `—` | — | `Controllers/ProfileController@edit` |
| `mobile.flights.details` | `flights.details` | `—` | — | `Frontend/FlightController@resultsOfferDetails` |
| `mobile.flights.results` | `flights.results` | `—` | — | `Frontend/FlightController@results` |
| `mobile.flights.return-options` | `flights.return-options` | `—` | — | `Frontend/FlightController@returnOptions` |
| `mobile.guest.booking-lookup` | `guest.booking-lookup` | `—` | — | `Frontend/GuestBookingLookupController@showLookupForm` |
| `mobile.home` | `home` | `—` | — | `Frontend/HomeController@index` |
| `mobile.public.about` | `public.about` | `—` | — | `Frontend/SupportController@about` |
| `mobile.support.index` | `support.index` | `—` | — | `Frontend/SupportController@support` |

### MA-4 — Customer portal — 6 views

| Current mobile view | Logical name → `client_view(…, 'mobile')` | Route | In `mobile_pages` | Controller |
|---|---|---|---|---|
| `mobile.customer.bookings.index` | `customer.bookings.index` | `customer.bookings.index` | on | `Customer/CustomerBookingController@index` |
| `mobile.customer.bookings.show` | `customer.bookings.show` | `customer.bookings.show` | on | `Customer/CustomerBookingController@show` |
| `mobile.customer.support.create` | `customer.support.create` | `customer.support.tickets.create` | on | `Customer/SupportTicketController@create` |
| `mobile.customer.support.index` | `customer.support.index` | `customer.support.tickets.index` | on | `Customer/SupportTicketController@index` |
| `mobile.customer.support.show` | `customer.support.show` | `customer.support.tickets.show` | on | `Customer/SupportTicketController@show` |
| `mobile.dashboard.customer` | `dashboard.customer` | `customer.dashboard` | on | `Customer/CustomerBookingController@dashboard` |

### MA-5 — Agent portal — 25 views

| Current mobile view | Logical name → `client_view(…, 'mobile')` | Route | In `mobile_pages` | Controller |
|---|---|---|---|---|
| `mobile.agent.accounting.ledger.index` | `agent.accounting.ledger.index` | `agent.accounting.ledger.index` | on | `Agent/AccountingLedgerController@index` |
| `mobile.agent.accounting.ledger.show` | `agent.accounting.ledger.show` | `agent.accounting.ledger.show` | on | `Agent/AccountingLedgerController@show` |
| `mobile.agent.agency.edit` | `agent.agency.edit` | `agent.agency.edit` | on | `Agent/AgentAgencyController@edit` |
| `mobile.agent.agency.show` | `agent.agency.show` | `agent.agency.show` | on | `Agent/AgentAgencyController@show` |
| `mobile.agent.bookings.create` | `agent.bookings.create` | `agent.bookings.create` | on | `Agent/AgentBookingController@create` |
| `mobile.agent.bookings.index` | `agent.bookings.index` | `agent.bookings.index` | on | `Agent/AgentBookingController@index` |
| `mobile.agent.bookings.show` | `agent.bookings.show` | `agent.bookings.show` | on | `Agent/AgentBookingController@show` |
| `mobile.agent.commissions.index` | `agent.commissions.index` | `agent.commissions.index` | on | `Agent/AgentCommissionController@index` |
| `mobile.agent.commissions.statement` | `agent.commissions.statement` | `agent.commissions.statements.show` | on | `Agent/AgentCommissionController@showStatement` |
| `mobile.agent.deposits.create` | `agent.deposits.create` | `agent.deposits.create` | on | `Agent/AgentDepositController@create` |
| `mobile.agent.deposits.index` | `agent.deposits.index` | `agent.deposits.index` | on | `Agent/AgentDepositController@index` |
| `mobile.agent.finance.statement.show` | `agent.finance.statement.show` | `agent.finance.statement.show` | on | `Agent/FinanceStatementController@show` |
| `mobile.agent.ledger.index` | `agent.ledger.index` | `agent.ledger.index` | on | `Agent/AgentLedgerController@index` |
| `mobile.agent.reports.index` | `agent.reports.index` | `agent.reports.index` | on | `Agent/AgentReportsController@index` |
| `mobile.agent.staff.create` | `agent.staff.create` | `agent.staff.create` | on | `Agent/AgentStaffController@create` |
| `mobile.agent.staff.edit` | `agent.staff.edit` | `agent.staff.edit` | on | `Agent/AgentStaffController@edit` |
| `mobile.agent.staff.index` | `agent.staff.index` | `agent.staff.index` | on | `Agent/AgentStaffController@index` |
| `mobile.agent.support.create` | `agent.support.create` | `agent.support.tickets.create` | on | `Agent/SupportTicketController@create` |
| `mobile.agent.support.index` | `agent.support.index` | `agent.support.tickets.index` | on | `Agent/SupportTicketController@index` |
| `mobile.agent.support.show` | `agent.support.show` | `agent.support.tickets.show` | on | `Agent/SupportTicketController@show` |
| `mobile.agent.travelers.create` | `agent.travelers.create` | `agent.travelers.create` | on | `Agent/SavedTravelerController@create` |
| `mobile.agent.travelers.edit` | `agent.travelers.edit` | `agent.travelers.edit` | on | `Agent/SavedTravelerController@edit` |
| `mobile.agent.travelers.index` | `agent.travelers.index` | `agent.travelers.index` | on | `Agent/SavedTravelerController@index` |
| `mobile.agent.wallet.show` | `agent.wallet.show` | `agent.wallet.show` | on | `Agent/AgentWalletController@show` |
| `mobile.dashboard.agent` | `dashboard.agent` | `agent.dashboard` | on | `Agent/DashboardController@index` |

### MA-6 — Auth / account entry — 5 views

| Current mobile view | Logical name → `client_view(…, 'mobile')` | Route | In `mobile_pages` | Controller |
|---|---|---|---|---|
| `mobile.auth.forgot-password` | `auth.forgot-password` | `—` | — | `Auth/PasswordResetLinkController@create` |
| `mobile.auth.login` | `auth.login` | `—` | — | `Auth/AuthenticatedSessionController@create` |
| `mobile.auth.login-otp` | `auth.login-otp` | `—` | — | `Auth/LoginOtpController@create` |
| `mobile.auth.register` | `auth.register` | `—` | — | `Auth/RegisteredUserController@create` |
| `mobile.auth.reset-password` | `auth.reset-password` | `—` | — | `Auth/NewPasswordController@create` |
## 5. Phase plan (revised by the MA-0 data)

**MA-0's audit changed my own plan.** I had scoped MA-3 as "customer" — but the data shows **15 of 51
views are the public site + booking flow** (home, flights results/details, passengers/review/
confirmation, agent-registration, support, about). For an OTA that is the **highest-traffic mobile
surface and the revenue path**, so it goes first.

| Phase | Scope | Views | Why this order |
|---|---|---|---|
| **MA-0** | Audit & architecture (this doc) | — | Decisions locked before code |
| **MA-1** | Foundation — `mobile` area, toggle, `default-mobile` shim. **Zero visual change** | — | Proves the fallback before any pixel moves |
| **MA-2** | JetPK compact app shell + `app.css` (tab bar, sticky header, sheets, safe-area, ≥44px, tokenised focus) | shell | Everything downstream renders inside it |
| **MA-3** | **Public site & booking flow** | **15** | Highest traffic + revenue path |
| **MA-4** | Customer portal | **6** | Post-booking self-service |
| **MA-5** | Agent portal | **25** | Largest surface; B2B users tolerate the fallback longest |
| **MA-6** | Auth/entry parity **+ QA + compiled package** | **5** + reports | 9 viewports, a11y, parity, toggle QA, final ZIP |

**Ship rule:** migrate by **complete journey**, never scattered pages — a user must not cross an
app-styled → legacy seam mid-booking. MA-3's 15 views are one journey and should land together.

## 6. App shell IA (MA-2 spec — same theme, compact)

- **Bottom tab bar** (primary nav, role-aware, ≥44px targets, `env(safe-area-inset-bottom)`).
- **Sticky compact header** — title + back affordance + one contextual action.
- **Sheets** for filters/actions instead of desktop modals.
- **Cards over tables** — never hide financial/operational data (the dashboard programme's hard rule).
- **Tokens only** — `var(--brand-*)`/`color-mix()`; no hardcoded `#00843D`. Reuse the existing
  `jp-*` token vocabulary so the app theme *is* the same theme, compacted.
- **Focus:** tokenised `:focus-visible` (copy `themes/frontend/jetpakistan/css/forms.css` — already correct).
- **Reuse** `ota-mobile-app.css`'s proven safe-area/`100dvh` patterns rather than reinventing them.

## 7. Assets & cache-busting

| Asset | Plan |
|---|---|
| `public/themes/mobile/jetpakistan-app/css/app.css` | **New in MA-2.** Own inline `$jpMobileAssetVersion`, bumped on every edit |
| `public/css/ota-mobile-app.css` | **Untouched** (shared) — never bump |
| `layouts/mobile-app` | **Untouched** (shared) |

## 8. Risks

1. **Controller migration is the gate** — 51 one-line changes; backward-compatible, but backend scope.
2. `AREAS` is a PHP const ⇒ registering the area is app code (one line), not config-only.
3. Two mobile design systems coexist during MA-3→MA-5; the toggle + journey-based shipping contain it.
4. **Not execution-verified** — no PHP/browser here. Lint + the MA-1 proof runs are mandatory.

---
