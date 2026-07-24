# JETPK Canonical UI Deletion Safety Review

Branch: `fix/jetpk-canonical-responsive-ui-cms`
Baseline: `5f97dc6c512d59ac2106c2bfa9854da2dc1210c8`

## Summary

| Classification | Count |
|----------------|------:|
| LEGACY_MOBILE_CONTROLLER_OR_SERVICE | 2 |
| OBSOLETE_CONFIG | 1 |
| LEGACY_MOBILE_ASSET | 5 |
| LEGACY_MOBILE_LAYOUT | 5 |
| LEGACY_MOBILE_TOGGLE | 4 |
| LEGACY_MOBILE_VIEW | 81 |
| TEST_ONLY | 2 |

**UNEXPECTED_DELETION count:** 0
**Runtime deletion count:** 98

## Verdict

All deletions are legacy mobile-app shell artifacts. No canonical JetPakistan responsive view, shared business logic, booking/checkout/supplier/PNR/payment/ticketing/cancellation/OTP implementation, or canonical public asset was removed without replacement.

## Deleted paths

- `app/Http/Controllers/Frontend/MobileViewController.php` — LEGACY_MOBILE_CONTROLLER_OR_SERVICE
- `app/Support/Ui/MobileViewPreference.php` — LEGACY_MOBILE_CONTROLLER_OR_SERVICE
- `config/ota-mobile.php` — OBSOLETE_CONFIG
- `public/css/ota-mobile-app.css` — LEGACY_MOBILE_ASSET
- `public/css/v2/ota-mobile-app-v2.css` — LEGACY_MOBILE_ASSET
- `public/js/ota-mobile-app.js` — LEGACY_MOBILE_ASSET
- `public/js/v2/ota-mobile-app-v2.js` — LEGACY_MOBILE_ASSET
- `public/themes/mobile/jetpakistan-app/css/app.css` — LEGACY_MOBILE_ASSET
- `resources/views/layouts/mobile-app.blade.php` — LEGACY_MOBILE_LAYOUT
- `resources/views/layouts/partials/desktop-mobile-link.blade.php` — LEGACY_MOBILE_TOGGLE
- `resources/views/layouts/partials/mobile-app-bottom-nav.blade.php` — LEGACY_MOBILE_LAYOUT
- `resources/views/layouts/partials/mobile-app-desktop-link.blade.php` — LEGACY_MOBILE_TOGGLE
- `resources/views/layouts/partials/mobile-app-top-bar.blade.php` — LEGACY_MOBILE_LAYOUT
- `resources/views/layouts/partials/mobile-viewport-reconcile.blade.php` — LEGACY_MOBILE_TOGGLE
- `resources/views/mobile/agent-registration/form.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent-registration/landing.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent-registration/submitted.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/accounting/ledger/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/accounting/ledger/show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/agency/edit.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/agency/show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/bookings/create.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/bookings/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/bookings/show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/commissions/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/commissions/statement.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/deposits/create.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/deposits/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/finance/statement/show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/ledger/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/partials/agent-booking-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/partials/agent-status-pill.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/partials/deposit-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/partials/ledger-row-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/partials/payment-summary-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/partials/permission-chip.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/partials/wallet-summary-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/profile/edit.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/reports/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/staff/create.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/staff/edit.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/staff/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/support/create.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/support/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/support/show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/travelers/_form.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/travelers/create.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/travelers/edit.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/travelers/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/agent/wallet/show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/auth/forgot-password.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/auth/login.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/auth/register.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/auth/reset-password.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/bookings/confirmation.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/bookings/partials/price-breakdown-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/bookings/partials/selected-flight-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/bookings/partials/traveller-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/bookings/passengers.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/bookings/review.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/components/alert.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/components/form-field.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/bookings/guest-show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/bookings/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/bookings/show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/partials/booking-status-pill.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/partials/booking-summary-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/partials/payment-summary-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/partials/support-ticket-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/profile/edit.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/support/create.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/support/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/support/show.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/travelers/_form.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/travelers/create.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/travelers/edit.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/customer/travelers/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/dashboard/agent.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/dashboard/customer.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/dashboard/partials/booking-list-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/dashboard/partials/quick-action-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/dashboard/partials/stat-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/details.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/partials/details-fare-breakdown.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/partials/details-fare-summary.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/partials/details-flight-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/partials/details-segment-timeline.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/partials/filter-drawer.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/partials/result-card.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/results.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/flights/return-options.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/guest/booking-lookup.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/home.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/public/about.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/mobile/support/index.blade.php` — LEGACY_MOBILE_VIEW
- `resources/views/themes/frontend/jetpakistan/partials/mobile-app-view-link.blade.php` — LEGACY_MOBILE_TOGGLE
- `resources/views/themes/mobile/default-mobile/layouts/mobile-app.blade.php` — LEGACY_MOBILE_LAYOUT
- `resources/views/themes/mobile/jetpakistan-app/layouts/mobile-app.blade.php` — LEGACY_MOBILE_LAYOUT
- `tests/Feature/Ui/MobileViewPreferenceTest.php` — TEST_ONLY
- `tests/Unit/Ui/MobileViewportPreferenceTest.php` — TEST_ONLY

## Controller dispositions

| Controller | Disposition |
|------------|-------------|
| `app/Http/Controllers/Agent/AccountingLedgerController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/AgentAgencyController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/AgentBookingController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/AgentCommissionController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/AgentDepositController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/AgentLedgerController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/AgentReportsController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/AgentStaffController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/AgentWalletController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/DashboardController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/FinanceStatementController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/SavedTravelerController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Agent/SupportTicketController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Auth/LoginOtpController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Auth/NewPasswordController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Auth/PasswordResetLinkController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Customer/CustomerBookingController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Customer/SavedTravelerController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Customer/SupportTicketController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Frontend/AgentRegistrationController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Frontend/BookingController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Frontend/FlightController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Frontend/GuestBookingLookupController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Frontend/HomeController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/Frontend/MobileViewController.php` | DELETED — view-preference switching only; no business logic |
| `app/Http/Controllers/Frontend/SupportController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
| `app/Http/Controllers/ProfileController.php` | PASS — removed `shouldUseMobileShell()` branch only; canonical responsive view retained; validation, services, redirects, authorization, and response data unchanged |
