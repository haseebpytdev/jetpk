# JP-FULL-NEXT-FRONTEND Page Composition Coverage

Generated: 2026-08-01T14:10:34.531Z
Phase: **JP-FULL-NEXT-FRONTEND-01B**
Machine-readable: [JP-FULL-NEXT-FRONTEND-PAGE-COMPOSITION-COVERAGE.json](./JP-FULL-NEXT-FRONTEND-PAGE-COMPOSITION-COVERAGE.json)

## Route count reconciliation

| Category | Count |
|---|---|
| Total `page.tsx` files | 67 |
| Production browser routes (excl. dev lab) | 65 |
| Dev-only excluded | 1 |
| Redirect-only | 3 |
| Dynamic routes | 13 |
| Metadata routes (not page.tsx) | robots.ts, sitemap.ts |
| Forbidden/missing | /preview, /booking/seats |

**Why 67:** 67 = total app/**/page.tsx files on disk. Production browser routes = 66 excluding /dev/jetpk-theme-lab. Phase reports cite 67 as the full Next App Router page inventory including the dev lab route.

## Composition coverage

| Metric | Value |
|---|---|
| Fully adapted routes | 4 |
| Production routes (excl. dev) | 66 |
| Fully adapted % | 6% |

Presentation statuses: fully-adapted uses kit page composition + real adapter; shared-theme-only = tokens/shell only; redirect; intentionally-deferred.

## Per-route matrix

| Route | Family | Page component | Shell | UI kit | Adapter | Status | Mockup | Risk |
|---|---|---|---|---|---|---|---|---|
| `/agent/register` | Agent registration | AgentRegisterForm + AuthShell | AuthPageShell (jp-auth) | AgentRegisterPage | agent registration API | shared-theme-only | — | Low |
| `/agent/register/submitted` | Agent registration | AgentRegisterSubmitted | AuthPageShell (jp-auth) | AgentRegisterSubmitted | static confirmation | shared-theme-only | — | Low |
| `/forgot-password` | forgot/reset | ForgotPasswordForm + AuthShell | AuthPageShell (jp-auth) | ForgotPasswordPage | password reset API | shared-theme-only | — | Low |
| `/login/otp` | OTP | OtpForm + AuthShell | AuthPageShell (jp-auth) | OtpPage | auth OTP API | shared-theme-only | login-partial | Low |
| `/login` | Login | LoginForm + AuthShell | AuthPageShell (jp-auth) | LoginPage | auth session-service + Laravel login API | shared-theme-only | login | Medium |
| `/register` | Register | RegisterForm + AuthShell | AuthPageShell (jp-auth) | SignupPage | registration API + challenge | shared-theme-only | signup | Medium |
| `/reset-password/[token]` | forgot/reset | ResetPasswordForm + AuthShell | AuthPageShell (jp-auth) | ResetPasswordPage | password reset API | shared-theme-only | — | Low |
| `/about-us` | About | AboutUsPage | PublicShell + PublicPageHero (jp-page-hero) | AboutPage | public-content CMS V2 bridge | shared-theme-only | about | Medium |
| `/booking/confirmation` | booking confirmation | BookingConfirmationPage | BookingLayout (jp-booking-shell) | ConfirmationPage | confirmation API | shared-theme-only | confirmation | Medium |
| `/booking/invoice` | invoice | BookingInvoicePage | BookingLayout | InvoicePage | invoice API | shared-theme-only | — | Low |
| `/booking/passengers` | Passengers | PassengerDetailsPage | BookingLayout (jp-booking-shell) | PassengersPage | booking passengers API | shared-theme-only | passengers | Medium |
| `/booking/payment/card` | Card handoff | CardPaymentPage | BookingLayout | CardPaymentPage | AbhiPay handoff API | shared-theme-only | payment-partial | Low |
| `/booking/payment/manual` | Manual Payment | ManualPaymentPage | BookingLayout (jp-booking-shell) | PaymentPage | checkout-state + manual payment API | shared-theme-only | payment | Medium |
| `/booking/payment` | payment selector | PaymentSelectorPage | BookingLayout | PaymentPage | checkout-state redirect | redirect | payment | Low |
| `/booking/payment/return` | payment return | PaymentReturnPage | BookingLayout | PaymentReturnPage | payment return handler | shared-theme-only | — | Low |
| `/booking/payment/status` | payment status | PaymentStatusPage | BookingLayout | PaymentStatusPage | payment status poll API | shared-theme-only | — | Low |
| `/booking/review` | Review | BookingReviewPage | BookingLayout (jp-booking-shell) | ReviewPage | booking review API | shared-theme-only | review | Medium |
| `/booking/status` | booking status | BookingStatusPage | BookingLayout | BookingStatusPage | booking status API | shared-theme-only | — | Low |
| `/contact` | Contact | ContactPage | PublicShell | ContactPage | contact CMS + form API | shared-theme-only | support-partial | Low |
| `/faq` | FAQ/legal/CMS | FaqPage | PublicShell | FaqPage | public-content CMS | shared-theme-only | support-partial | Low |
| `/groups/booking/[bookingRef]/confirmation` | authenticated group checkout | GroupConfirmationPage | BookingLayout | GroupConfirmationPage | group confirmation API | shared-theme-only | — | Low |
| `/groups/booking/[bookingRef]/payment` | authenticated group checkout | GroupPaymentPage | BookingLayout | GroupPaymentPage | group payment API | shared-theme-only | — | Low |
| `/groups/booking/[bookingRef]/review` | authenticated group checkout | GroupReviewPage | BookingLayout | GroupReviewPage | group review API | shared-theme-only | — | Low |
| `/groups/search` | public groups | GroupSearchPage | PublicShell | GroupSearchPage | group search API | shared-theme-only | — | Medium |
| `/groups/[packageId]` | public groups | GroupPackageDetailPage | PublicShell | GroupDetailPage | group package API | shared-theme-only | — | Medium |
| `/groups/[packageId]/passengers` | authenticated group checkout | GroupPassengersPage | PublicShell / booking | GroupPassengersPage | group passengers API | shared-theme-only | — | Medium |
| `/legal/[slug]` | FAQ/legal/CMS | LegalSlugPage | PublicShell | LegalSlugPage | public-content legal API | shared-theme-only | — | Low |
| `/lookup-booking` | Manage Booking | BookingLookupPage | PublicShell | ManageBookingPage | lookup API + Turnstile | shared-theme-only | manage-booking | Medium |
| `/pages/[slug]` | FAQ/legal/CMS | CmsPageRenderer | PublicShell | CmsPage | cms-v2-bridge | fully-adapted | — | Low |
| `/privacy` | FAQ/legal/CMS | LegalPage | PublicShell | LegalPage | public-content legal | shared-theme-only | — | Low |
| `/sitemap` | FAQ/legal/CMS | SitemapPage | PublicShell | SitemapPage | navigation.ts | shared-theme-only | — | Low |
| `/support` | Support | SupportPage | PublicShell + PublicPageHero | SupportPage | public-content CMS | shared-theme-only | support | Medium |
| `/terms` | FAQ/legal/CMS | LegalPage | PublicShell | LegalPage | public-content legal | shared-theme-only | — | Low |
| `/[slug]` | FAQ/legal/CMS | CustomClientPageRenderer | PublicShell | CmsSlugPage | cms-v2-bridge + custom content API | fully-adapted | — | Low |
| `/access-denied` | access denied | AccessDeniedPage | minimal | AccessDenied | static | shared-theme-only | — | Low |
| `/agent/bookings` | Agent portal | AgentBookingsPage | PortalShell (jp-portal) | AgentBookings | agent bookings API | shared-theme-only | — | Low |
| `/agent/bookings/[reference]` | Agent portal | AgentBookingDetailPage | PortalShell (jp-portal) | AgentBookingDetail | agent booking detail API | shared-theme-only | — | Low |
| `/agent/dashboard` | Agent portal | AgentOverviewPage | PortalShell (jp-portal) | AgentDashboard | agent dashboard API | shared-theme-only | — | Medium |
| `/agent/deposits/new` | Agent portal | NewDepositPage | PortalShell (jp-portal) | NewDeposit | deposit create API | shared-theme-only | — | Low |
| `/agent/deposits` | Agent portal | DepositsPage | PortalShell (jp-portal) | AgentDeposits | deposits API | shared-theme-only | — | Low |
| `/agent/invoices` | Agent portal | AgentInvoicesPage | PortalShell (jp-portal) | AgentInvoices | invoices API | shared-theme-only | — | Low |
| `/agent/notifications` | Agent portal | AgentNotificationsPage | PortalShell (jp-portal) | AgentNotifications | notifications API | shared-theme-only | — | Low |
| `/agent` | Agent portal | redirect | n/a | n/a | Next redirect | redirect | — | Low |
| `/agent/payments` | Agent portal | AgentPaymentsPage | PortalShell (jp-portal) | AgentPayments | payments API | shared-theme-only | — | Low |
| `/agent/profile` | Agent portal | AgentProfilePage | PortalShell (jp-portal) | AgentProfile | profile API | shared-theme-only | — | Low |
| `/agent/security` | Agent portal | AgentSecurityPage | PortalShell (jp-portal) | AgentSecurity | security API | shared-theme-only | — | Low |
| `/agent/support` | Agent portal | AgentSupportPage | PortalShell (jp-portal) | AgentSupport | support API | shared-theme-only | — | Low |
| `/agent/support/[reference]` | Agent portal | AgentSupportCasePage | PortalShell (jp-portal) | AgentSupportCase | support case API | shared-theme-only | — | Low |
| `/agent/wallet/ledger` | Agent portal | WalletLedgerPage | PortalShell (jp-portal) | AgentLedger | ledger API | shared-theme-only | — | Low |
| `/agent/wallet` | Agent portal | WalletOverviewPage | PortalShell (jp-portal) | AgentWallet | wallet API | shared-theme-only | — | Low |
| `/customer/bookings` | Customer portal | CustomerBookingsPage | PortalShell (jp-portal) | CustomerBookings | customer bookings API | shared-theme-only | — | Low |
| `/customer/bookings/[reference]` | Customer portal | CustomerBookingDetailPage | PortalShell (jp-portal) | CustomerBookingDetail | customer booking detail API | shared-theme-only | — | Low |
| `/customer/dashboard` | Customer portal | DashboardOverviewPage | PortalShell (jp-portal) | CustomerDashboard | customer dashboard API | shared-theme-only | — | Medium |
| `/customer/invoices` | Customer portal | CustomerInvoicesPage | PortalShell (jp-portal) | CustomerInvoices | invoices API | shared-theme-only | — | Low |
| `/customer/notifications` | Customer portal | CustomerNotificationsPage | PortalShell (jp-portal) | CustomerNotifications | notifications API | shared-theme-only | — | Low |
| `/customer` | Customer portal | redirect | n/a | n/a | Next redirect | redirect | — | Low |
| `/customer/payments` | Customer portal | CustomerPaymentsPage | PortalShell (jp-portal) | CustomerPayments | payments API | shared-theme-only | — | Low |
| `/customer/profile` | Customer portal | CustomerProfilePage | PortalShell (jp-portal) | CustomerProfile | profile API | shared-theme-only | — | Low |
| `/customer/security` | Customer portal | CustomerSecurityPage | PortalShell (jp-portal) | CustomerSecurity | security API | shared-theme-only | — | Low |
| `/customer/support` | Customer portal | CustomerSupportPage | PortalShell (jp-portal) | CustomerSupport | support cases API | shared-theme-only | — | Low |
| `/customer/support/[reference]` | Customer portal | SupportCaseDetailPage | PortalShell (jp-portal) | SupportCaseDetail | support case API | shared-theme-only | — | Low |
| `/dev/jetpk-theme-lab` | development-only | ThemeLabPage | dev | n/a | dev gate | intentionally-deferred | — | n/a |
| `/flights/fare-selection` | Fare Selection | FareSelectionPage | BookingLayout (jp-booking-shell) | FareSelectionPage | useFlightDetails + useRevalidation (Laravel offer contract) | fully-adapted | fare-selection | Medium |
| `/flights/results` | Results | FlightResultsPage | PublicShell | ResultsPage | flight-results API + search context | shared-theme-only | results | High — filter/sort density |
| `/flights/return-options` | Return Options | ReturnOptionsPage | PublicShell | ReturnOptionsPage | return-pair validation API | shared-theme-only | results-partial | Medium |
| `/` | Homepage | HomepageContent | PublicShell (jp-page) | HomePage | getPublicSession + CMS/home fixtures | shared-theme-only | homepage | Medium — hero/search density vs mockup |
| `/verify-email` | verify-email | VerifyEmailPage | AuthPageShell (jp-auth) | VerifyEmailPage | Laravel signed verify action (authoritative) | fully-adapted | — | Low |

## Legacy composition families (shared-theme-only)

- `/agent/register` (Agent registration)
- `/agent/register/submitted` (Agent registration)
- `/forgot-password` (forgot/reset)
- `/login/otp` (OTP)
- `/login` (Login)
- `/register` (Register)
- `/reset-password/[token]` (forgot/reset)
- `/about-us` (About)
- `/booking/confirmation` (booking confirmation)
- `/booking/invoice` (invoice)
- `/booking/passengers` (Passengers)
- `/booking/payment/card` (Card handoff)
- `/booking/payment/manual` (Manual Payment)
- `/booking/payment/return` (payment return)
- `/booking/payment/status` (payment status)
- `/booking/review` (Review)
- `/booking/status` (booking status)
- `/contact` (Contact)
- `/faq` (FAQ/legal/CMS)
- `/groups/booking/[bookingRef]/confirmation` (authenticated group checkout)
- `/groups/booking/[bookingRef]/payment` (authenticated group checkout)
- `/groups/booking/[bookingRef]/review` (authenticated group checkout)
- `/groups/search` (public groups)
- `/groups/[packageId]` (public groups)
- `/groups/[packageId]/passengers` (authenticated group checkout)
- `/legal/[slug]` (FAQ/legal/CMS)
- `/lookup-booking` (Manage Booking)
- `/privacy` (FAQ/legal/CMS)
- `/sitemap` (FAQ/legal/CMS)
- `/support` (Support)
- `/terms` (FAQ/legal/CMS)
- `/access-denied` (access denied)
- `/agent/bookings` (Agent portal)
- `/agent/bookings/[reference]` (Agent portal)
- `/agent/dashboard` (Agent portal)
- `/agent/deposits/new` (Agent portal)
- `/agent/deposits` (Agent portal)
- `/agent/invoices` (Agent portal)
- `/agent/notifications` (Agent portal)
- `/agent/payments` (Agent portal)
- `/agent/profile` (Agent portal)
- `/agent/security` (Agent portal)
- `/agent/support` (Agent portal)
- `/agent/support/[reference]` (Agent portal)
- `/agent/wallet/ledger` (Agent portal)
- `/agent/wallet` (Agent portal)
- `/customer/bookings` (Customer portal)
- `/customer/bookings/[reference]` (Customer portal)
- `/customer/dashboard` (Customer portal)
- `/customer/invoices` (Customer portal)
- `/customer/notifications` (Customer portal)
- `/customer/payments` (Customer portal)
- `/customer/profile` (Customer portal)
- `/customer/security` (Customer portal)
- `/customer/support` (Customer portal)
- `/customer/support/[reference]` (Customer portal)
- `/flights/results` (Results)
- `/flights/return-options` (Return Options)
- `/` (Homepage)
