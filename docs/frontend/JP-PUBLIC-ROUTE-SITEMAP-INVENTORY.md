# JP-PUBLIC-ROUTE-SITEMAP-INVENTORY

Phase: **JP-PUBLIC-NEXT-THEME-01**
Branch: `phase/jetpk-public-next-theme-rebuild`
Baseline: `111b2925f12369dbcbef139c9b251726a5a785fd`
Authority: [docs/architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md](../architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md) — decisions in §16
Status: **Phase A inventory, closed by JP-PUBLIC-NEXT-THEME-01A decisions — no runtime implementation**

---

## 1. Measured totals

All figures in this table are the **measured Phase A baseline** at
`111b2925f12369dbcbef139c9b251726a5a785fd`. They are not adjusted by the
approved decisions; planned future figures are recorded separately in §1.1.

| Metric | Value |
|---|---|
| Next.js page routes (`frontend/app/**/page.tsx`) | **64** |
| Public / unauthenticated Next routes | **38** |
| Authenticated Customer routes | **11** |
| Agent + Agent Staff routes | **15** |
| Laravel route entries (`artisan route:list --json`) | **584** |
| Laravel unique URIs | **519** |
| Laravel `api/public/*` endpoints | **13** |
| Laravel redirect-controller routes | **14** |
| Mock Shell coverage of real Next routes | **11 / 64 = 17.2%** |
| Mock Shell coverage of public Next routes | **11 / 38 = 28.9%** |

Next.js structural facts:

- `layout.tsx` files: **6** (root, `(public)`, `(auth)`, `flights`, `customer`, `agent`)
- `error.tsx`: 1, `not-found.tsx`: 1
- `middleware.ts`: **none**
- `route.ts` (Next Route Handlers): **none**
- `loading.tsx` / `template.tsx`: **none**
- Route groups `(public)` and `(auth)` do **not** appear in URLs
- No catch-all `[...slug]` or optional catch-all segments

### Development-only routes (excluded from production route count)

| Route | Source | Indexing | Notes |
|---|---|---|---|
| `/__dev/jetpk-theme-lab` (rewrite → `/dev/jetpk-theme-lab`) | `frontend/app/dev/jetpk-theme-lab/page.tsx` | `noindex,nofollow` | Phase B visual lab; gated by `isThemeLabAllowed()`; not in navigation or sitemap; **not counted** in the planned production target of **65** routes |

Command used for Laravel figures:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan route:list --json
```

### 1.1 Planned target figures (approved, not yet measured)

| Metric | Value | Basis |
|---|---|---|
| **Measured current Next page routes** | **64** | Counted at the baseline commit |
| **Approved future planned route** | **`/flights/fare-selection`** | Architecture §16 decision 4 |
| **Planned target Next page routes** | **65** | 64 + 1, unless another route is retired; subject to implementation verification |

`/verify-email` is an approved future Next page (decision 2) that replaces a
route Laravel already owns, so it does not increase the planned target beyond 65
on its own; its final count is confirmed at implementation. No route is approved
for retirement at this time.

---

## 2. Field definitions

Every route record below carries these fifteen fields, split across two tables per family for readability.

**Identity table:** exact URL, source file, access level, dynamic parameters, canonical URL, indexing rule, page family.

**Contract table:** current implementation, backend authority, applicable mockup, proposed template, decision, compatibility requirement, fallback risk, missing dependencies.

Access levels: `Public`, `Guest-only` (redirects away when authenticated), `Customer`, `Agent/Agent Staff`.

Decisions: `Keep`, `Rebuild`, `Redirect`, `Retire`.

Backend authority is always Laravel. Paths shown as `/api/...` or `/booking/...` are reached from the browser through the same-origin rewrite `/laravel/:path*` defined in `frontend/next.config.ts`.

---

## 3. Root and utility routes (2)

### Identity

| URL | Source file | Access | Parameters | Canonical | Indexing | Family |
|---|---|---|---|---|---|---|
| `/` | `frontend/app/page.tsx` | Public | — | `/` | index | Home |
| `/access-denied` | `frontend/app/access-denied/page.tsx` | Public | — | none | noindex (robots.txt disallow) | Utility |

### Contract

| URL | Current implementation | Backend authority | Mockup | Proposed template | Decision | Compatibility | Fallback risk | Missing dependencies |
|---|---|---|---|---|---|---|---|---|
| `/` | Home feature components + `SearchPanel` equivalent | `GET /api/public/content/homepage`, `GET /api/public/content/config`, `GET /airports/search` | `public/mockups/home.png` | `landing` | Rebuild | Must remain the canonical flight-search entry point; `/flights` and `/flights/search` redirect here | **High** — Laravel still registers `GET /` → `Frontend\HomeController@index` (Blade) | Confirmed nginx/proxy ownership of `/` |
| `/access-denied` | Static shell page | Session shape from `GET /api/public/auth/session` | none | `default-content` | Keep | Target of `requireCustomerPortalAccess` / `requireAgentPortalAccess` denial | Low | Explicit page-level `robots: noindex` (currently only robots.txt disallow) |

---

## 4. Authentication routes (7)

### Identity

| URL | Source file | Access | Parameters | Canonical | Indexing | Family |
|---|---|---|---|---|---|---|
| `/login` | `frontend/app/(auth)/login/page.tsx` | Guest-only | — | `/login` | index | Auth |
| `/login/otp` | `frontend/app/(auth)/login/otp/page.tsx` | Guest-only | — | none | should be noindex | Auth |
| `/register` | `frontend/app/(auth)/register/page.tsx` | Guest-only | — | `/register` | index | Auth |
| `/forgot-password` | `frontend/app/(auth)/forgot-password/page.tsx` | Guest-only | — | `/forgot-password` | index | Auth |
| `/reset-password/[token]` | `frontend/app/(auth)/reset-password/[token]/page.tsx` | Guest-only | `token` | none | must be noindex | Auth |
| `/agent/register` | `frontend/app/(auth)/agent/register/page.tsx` | Public | — | `/agent/register` | index | Auth |
| `/agent/register/submitted` | `frontend/app/(auth)/agent/register/submitted/page.tsx` | Public | — | none | must be noindex | Auth |

### Contract

| URL | Current implementation | Backend authority | Mockup | Proposed template | Decision | Compatibility | Fallback risk | Missing dependencies |
|---|---|---|---|---|---|---|---|---|
| `/login` | Auth feature form, CSRF via `laravelJsonFetch` | `POST /login`, `GET /api/public/auth/session`, `GET /api/public/content/csrf-token` | `public/mockups/login.png` | `auth` (shell-owned) | Rebuild | Emitted by `AuthEmailRenderer::loginCta` → `route('login')`; portal guards redirect here | Medium — Laravel `routes/auth.php` serves Blade login | — |
| `/login/otp` | OTP challenge form | `GET /api/public/auth/otp-challenge`, `POST /login/otp`, `POST /login/otp/resend` | none | `auth` | Rebuild | **OTP demo behavior must not be removed** (architecture §3) | Medium | Approved OTP visual state; page-level noindex |
| `/register` | Customer registration form | `POST /register`, `GET /api/public/auth/registration-security-challenge`, `GET /api/public/content/turnstile-config` | `public/mockups/signup.png` | `auth` | Rebuild | `/register/customer` redirects here | Medium | — |
| `/forgot-password` | Password request form | `POST /forgot-password` | none | `auth` | Rebuild | `/password/forgot` redirects here; emitted by `AuthSecurityEmailPayloadFactory` → `route('password.request')` | Medium | Mockup or shared auth design family |
| `/reset-password/[token]` | Token reset form | `POST /reset-password` | none | `auth` | Rebuild | Token arrives by email; URL shape must not change | Medium | Page-level noindex |
| `/agent/register` | Agent application form | `POST /agent/register` (module `agent_applications`) | none | `auth` | Rebuild | `/register/agent` and `/agent-network` redirect into this flow | Medium | Mockup or shared auth design family |
| `/agent/register/submitted` | Confirmation screen | none (static post-submit) | none | `auth` | Keep | Terminal state of agent application | Low | Page-level noindex |

**Approved future auth route (decision 2), not counted in the measured 64:**

| URL | Owner | Access | Canonical | Indexing | Family | Decision |
|---|---|---|---|---|---|---|
| `/verify-email` | **Next.js** | Public | none | **`noindex,nofollow`** | Auth | **Build** as a Next notice/result page |

**Auth routes that remain Laravel-authoritative:**

| Laravel URL | Route name | Approved disposition |
|---|---|---|
| `/verify-email/{id}/{hash}` | `verification.verify` (signed) | **Remains a Laravel-authoritative signed action.** Laravel verifies the signature and account, then redirects to a Next result or login state. In the final architecture this action must never render the legacy Blade theme. **Signature verification must not move into Next.js.** |
| `/logout` | `logout` | Laravel-authoritative; portal shell links `/laravel/logout` |
| `/confirm-password`, `/password` | password confirm/update | Laravel action authority; Next presentation to be confirmed in Phase G |
| `/auth/*` social redirect + callback | social login | Laravel-authoritative. **Decision 11:** social-login controls render only when an authoritative provider is enabled and its callback is functional; otherwise they are omitted from the UI. |
| `/password/force-change` | forced rotation | Laravel-authoritative; referenced in `dashboard-allowlist.ts` |

---

## 5. Public content and CMS routes (11)

### Identity

| URL | Source file | Access | Parameters | Canonical | Indexing | Family |
|---|---|---|---|---|---|---|
| `/about-us` | `frontend/app/(public)/about-us/page.tsx` | Public | — | from CMS SEO | from CMS SEO | CMS/content |
| `/contact` | `frontend/app/(public)/contact/page.tsx` | Public | — | `/contact` (hard-coded) | `index,follow` (hard-coded) | Support/Contact |
| `/faq` | `frontend/app/(public)/faq/page.tsx` | Public | — | from CMS SEO | from CMS SEO | Support/FAQ |
| `/support` | `frontend/app/(public)/support/page.tsx` | Public | — | from CMS SEO | from CMS SEO | Support |
| `/privacy` | `frontend/app/(public)/privacy/page.tsx` | Public | — | from CMS SEO | from CMS SEO | Legal |
| `/terms` | `frontend/app/(public)/terms/page.tsx` | Public | — | from CMS SEO | from CMS SEO | Legal |
| `/sitemap` | `frontend/app/(public)/sitemap/page.tsx` | Public | — | `/sitemap` | `index,follow` | Utility |
| `/lookup-booking` | `frontend/app/(public)/lookup-booking/page.tsx` | Public | — | `/lookup-booking` | robots.txt disallow | Manage booking |
| `/pages/[slug]` | `frontend/app/(public)/pages/[slug]/page.tsx` | Public | `slug` | `/pages/{slug}` or `cms_pages.canonical_url` | from `cms_pages.robots` | CMS/content |
| `/legal/[slug]` | `frontend/app/(public)/legal/[slug]/page.tsx` | Public | `slug` | from CMS SEO | noindex when page missing | Legal |
| `/[slug]` | `frontend/app/(public)/[slug]/page.tsx` | Public | `slug` | from CMS SEO | from CMS SEO | CMS/content |

### Contract

| URL | Current implementation | Backend authority | Mockup | Proposed template | Decision | Compatibility | Fallback risk | Missing dependencies |
|---|---|---|---|---|---|---|---|---|
| `/about-us` | Managed-page components + `publicSeoToMetadata` | `GET /api/public/content/pages/about` | `public/mockups/about.png` | `hero-content` | Rebuild | The legacy Laravel `/contact` → `/about-us` redirect is removed at route-ownership closure (decision 1) | **High** — Laravel `about` → `Frontend\SupportController@about` (Blade) | — |
| `/contact` | Dedicated contact page + form | `GET /api/public/content/site-contact`, `POST /support` | none | `contact` | **Keep and rebuild — canonical** | **Approved (decision 1):** `/contact` is the canonical dedicated Contact page, uses the `contact` template and remains indexable. The Laravel `permanentRedirect('/contact' → '/about-us')` is legacy and must be removed or replaced during route-ownership closure. Header and footer links remain valid. | High until the legacy redirect is removed | Removal or replacement of the legacy Laravel redirect |
| `/faq` | Managed-page components | `GET /api/public/content/pages/faq` | none | `faq` | Rebuild | **Approved (decision 9):** Flight Status is removed from target navigation; Baggage is removed or relabelled as FAQs only where its visible label is FAQs. No misleading capability label may point here. | High | — |
| `/support` | Support topics + contact channels | `GET /api/public/content/pages/support`, `GET /api/public/content/support/categories`, `POST /support` | `public/mockups/support.png` | `hero-content` | Rebuild | Module-gated `platform.module:support_system` | **High** — Laravel `support` Blade route | Contact-form fallback currently hrefs `/laravel/support` |
| `/privacy` | Managed-page components | `GET /api/public/content/pages/privacy` | none | `policy` | Rebuild | `/pages/privacy-policy` redirects here | High | — |
| `/terms` | Managed-page components | `GET /api/public/content/pages/terms` | none | `policy` | Rebuild | `/pages/terms-and-conditions` redirects here | High | — |
| `/sitemap` | HTML link list | `GET /api/public/content/sitemap-routes` | none | `default-content` | Keep | Distinct from Laravel `GET /sitemap.xml` | Low | — |
| `/lookup-booking` | Guest lookup form | `POST /lookup-booking` (`booking.lookup`) | `public/mockups/manage-booking.png` | `hero-content` | Rebuild | `/booking-lookup` redirects here; emitted in booking emails via `route('booking.lookup')` | **High** — Laravel `GuestBookingLookupController` Blade | Approved guest-result page design |
| `/pages/[slug]` | `CmsPageRenderer` with `dangerouslySetInnerHTML` on `bodyHtml` | `GET /api/public/content/cms/{slug}` | none | resolved by registry, default `default-content` | Rebuild | Legacy CMS URL shape; `canonical_url` may override | **High** — Laravel `pages.show` → `CmsPageController` Blade | Sanitizer + `.jp-cms-content` (see CMS contract) |
| `/legal/[slug]` | `CustomClientPageRenderer`, structured sections | `GET /api/public/content/custom/{slug}` | none | `policy` | Rebuild | In-page alias map: `refund`→`refund-policy`, `cookies`→`cookie-policy`, `cancellation`→`cancellation-policy`, `booking-terms` | High | Alias map should move into the template registry |
| `/[slug]` | `CustomClientPageRenderer`; reserved first segments call `notFound()` | `GET /api/public/content/custom/{slug}` | none | resolved by registry, default `default-content` | Rebuild | Mirrors Laravel catch-all `GET /{slug}` → `ClientManagedPageController@customShow` | **Critical** — this is the only catch-all; unmatched Next-only URLs land in the Laravel CMS catch-all instead of 404 | Reserved-path list must stay in sync with `reserved-public-paths.ts` |

---

## 6. Flight search routes (2)

### Identity

| URL | Source file | Access | Parameters | Canonical | Indexing | Family |
|---|---|---|---|---|---|---|
| `/flights/results` | `frontend/app/flights/results/page.tsx` | Public | query string (origin, destination, dates, pax, cabin) | none | robots.txt disallow, no page meta | Flight search |
| `/flights/return-options` | `frontend/app/flights/return-options/page.tsx` | Public | query string | none | robots.txt disallow, no page meta | Fare selection / return options |

### Contract

| URL | Current implementation | Backend authority | Mockup | Proposed template | Decision | Compatibility | Fallback risk | Missing dependencies |
|---|---|---|---|---|---|---|---|---|
| `/flights/results` | Results feature with filters, sort, offer cards | `GET /flights/results/search`, `/flights/results/data`, `/flights/results/nearby-dates`, `/flights/results/offer`, `POST /flights/results/revalidate-offer` | `public/mockups/flight-results.png` | shell-owned (not CMS) | Rebuild | Emitted by `AbandonedFlightSearchEmailSender` → `route('flights.results', ...)`; `/flights` and `/flights/search` are compatibility redirects (decision 3) | **High** — Laravel `FlightController` Blade results still registered | Page-level `robots: noindex,nofollow`; supplier data unchanged |
| `/flights/return-options` | Return-combination selector | `GET /flights/return-options/data`, `POST /flights/select-return-combo` | **none — the Fare Selection mockup must not be mapped here** | shell-owned | Rebuild | **Approved (decision 4):** presents supplier-returned or supplier-validated return combinations. Outbound and return offers must **never** be stitched manually. | High | Design from the shared design system, not from the Fare Selection mockup |

### Approved future route (decision 4), not counted in the measured 64

| Field | Value |
|---|---|
| Exact URL | `/flights/fare-selection` |
| Source file | To be created at `frontend/app/flights/fare-selection/page.tsx` |
| Access | Public (session-scoped offer context) |
| Parameters | Offer / itinerary reference via query or session |
| Canonical | none |
| Indexing | `noindex,nofollow` |
| Page family | Fare selection |
| Current implementation | **None — does not exist today** |
| Backend authority | Authoritative offer retrieval and revalidation: `GET /flights/results/offer`, `POST /flights/results/revalidate-offer` |
| Applicable mockup | `public/mockups/fare-selection.png` — **this route owns the approved Fare Selection mockup** |
| Proposed template | shell-owned |
| Decision | **Build as a new dedicated route** |
| Compatibility | Applies to a selected one-way offer or a validated return pair; placed **before** `/booking/passengers` |
| Fallback risk | New URL — Laravel reserved-path protection and the proxy boundary must keep it out of the CMS catch-all |
| Missing dependencies | Confirmed fare-family payload contract from the offer/revalidation endpoints |

`/flights/return-options` and `/flights/fare-selection` are separate routes with
separate workflows and must not be merged.

Related Laravel-only flight endpoints with no Next page: `flights/details/{id}`, `POST flights/multicity/inquiry`, `airports/search`.

`/flights/search` is retained **only** as a compatibility redirect. Its approved
target is `/#flight-search` (decision 3), which is also the canonical search
target for all Customer and Agent CTAs.

---

## 7. Booking checkout and payment routes (10)

### Identity

| URL | Source file | Access | Parameters | Canonical | Indexing | Family |
|---|---|---|---|---|---|---|
| `/booking/passengers` | `frontend/app/(public)/booking/passengers/page.tsx` | Public (session-scoped) | — | none | robots.txt disallow | Booking checkout |
| `/booking/review` | `frontend/app/(public)/booking/review/page.tsx` | Public (session-scoped) | — | none | robots.txt disallow | Booking checkout |
| `/booking/payment` | `frontend/app/(public)/booking/payment/page.tsx` | Public (session-scoped) | — | none | robots.txt disallow | Payment |
| `/booking/payment/manual` | `frontend/app/(public)/booking/payment/manual/page.tsx` | Public (session-scoped) | — | none | robots.txt disallow | Payment |
| `/booking/payment/card` | `frontend/app/(public)/booking/payment/card/page.tsx` | Public (session-scoped) | — | none | robots.txt disallow | Payment |
| `/booking/payment/status` | `frontend/app/(public)/booking/payment/status/page.tsx` | Public (session-scoped) | query | none | robots.txt disallow | Payment |
| `/booking/payment/return` | `frontend/app/(public)/booking/payment/return/page.tsx` | Public | gateway query | none | robots.txt disallow | Payment |
| `/booking/confirmation` | `frontend/app/(public)/booking/confirmation/page.tsx` | Public (session-scoped) | — | none | **page-level noindex, nofollow** | Confirmation |
| `/booking/invoice` | `frontend/app/(public)/booking/invoice/page.tsx` | Public (session-scoped) | — | none | robots.txt disallow | Confirmation/Invoice |
| `/booking/status` | `frontend/app/(public)/booking/status/page.tsx` | Public (session-scoped) | — | none | robots.txt disallow | Booking checkout |

### Contract

| URL | Current implementation | Backend authority | Mockup | Proposed template | Decision | Compatibility | Fallback risk | Missing dependencies |
|---|---|---|---|---|---|---|---|---|
| `/booking/passengers` | Passenger form, stepper, order summary | `GET\|POST /booking/passengers` (`booking.passengers`) | `public/mockups/passengers.png` | shell-owned | Rebuild | Module `customer_checkout`; `/flights/fare-selection` is placed immediately before this route (decision 4) | **High** — Laravel Blade `booking/passengers` | Stepper renders the Seats step **only** when the authoritative seat capability is true (decision 5) |
| `/booking/review` | Review + policy acknowledgement | `GET\|POST /booking/review?format=json`, `GET /booking/checkout-state` | `public/mockups/review.png` | shell-owned | Rebuild | Fare-change accept/decline via `POST /booking/{booking}/accept-updated-fare` and `decline-updated-fare` | High | — |
| `/booking/payment` | **Canonical payment-method selector** (decision 6) | `GET /booking/checkout-state?format=json` | `public/mockups/payment.png` | shell-owned | **Rebuild as the canonical selector** — no longer an internal redirect | Entry point for both payment workflows | **High** — no Laravel GET route; direct entry currently reaches the `/{slug}` CMS catch-all | Laravel reserved-path protection and the final proxy boundary must exclude `/booking/*` from the CMS catch-all |
| `/booking/payment/manual` | Manual Payment workflow: bank transfer, Easypaisa, JazzCash instructions and proof submission | `POST /booking/...?format=json` | `public/mockups/payment.png` | shell-owned | Rebuild | **Preserved as a compatibility route** (decision 6) | **High** — Next-only URL | Reserved-path protection; proxy boundary |
| `/booking/payment/card` | **AbhiPay secure handoff only** — no card fields rendered | `POST {startEndpoint}?format=json` (dynamic), AbhiPay start | `public/mockups/payment.png` (card-details panel **not** implemented) | shell-owned | Rebuild | **Preserved as a compatibility route** (decision 6). **Next.js must never collect a card number, CVV or expiry.** | **High** — Next-only URL | Reserved-path protection; proxy boundary |
| `/booking/payment/status` | Payment-status state | `GET /booking/payment/status?format=json` | none | shell-owned | Rebuild | Backed by a real Laravel route | Medium | — |
| `/booking/payment/return` | Gateway-return state | AbhiPay callback + `/payment/success\|cancel\|decline` | none | shell-owned | Rebuild | Gateway return URLs must not change without payment approval; payment callbacks stay Laravel-owned | **High** — Laravel serves `frontend/payments/result` Blade | Confirmed gateway return URL ownership |
| `/booking/confirmation` | Success screen | `GET /booking/confirmation?format=json` | `public/mockups/success.png` | shell-owned | Rebuild | Emitted in booking emails | High | — |
| `/booking/invoice` | Invoice view | `GET /booking/invoice?format=json` | none | shell-owned | Rebuild | Document download stays Laravel-authoritative | High | Approved invoice/print design |
| `/booking/status` | Booking state view | `GET /booking/checkout-state?format=json` | none | shell-owned | Rebuild | Next-only URL | High | Clarify overlap with `/booking/payment/status` |

**Seat capability (decision 5).** `/booking/seats` must **not** be created or ported while no authoritative seat map exists. The current capability is `seat_map_available=false`, so the current journey omits both the seat page and the Seats step. Seats are **capability-gated, not permanently removed**: the design system may support a conditional Seats step that appears only when an authoritative future capability becomes true. The Mock Shell fixture seat map and seat 3C must never be ported.

**Payment security constraint (decision 6).** No Next.js route in this family may render a card-number, CVV or expiry field. `/booking/payment/card` performs an AbhiPay secure handoff only. Any card-details form depicted in the payment mockup is explicitly not implemented.

**Catch-all protection (decision 6).** Laravel reserved-path protection and the final proxy boundary must prevent `/booking/payment`, `/booking/payment/manual`, `/booking/payment/card`, `/booking/payment/return` and `/booking/status` from entering the CMS catch-all `GET /{slug}`.

---

## 8. Group ticketing routes (6)

### Identity

| URL | Source file | Access | Parameters | Canonical | Indexing | Family |
|---|---|---|---|---|---|---|
| `/groups/search` | `frontend/app/(public)/groups/search/page.tsx` | Public | query/facets | `/groups/search` | index | Groups |
| `/groups/[packageId]` | `frontend/app/(public)/groups/[packageId]/page.tsx` | Public | `packageId` | `/groups/{packageId}` | index | Groups |
| `/groups/[packageId]/passengers` | `frontend/app/(public)/groups/[packageId]/passengers/page.tsx` | Customer/Agent (Laravel `auth`) | `packageId` | none | should be noindex | Groups |
| `/groups/booking/[bookingRef]/review` | `frontend/app/(public)/groups/booking/[bookingRef]/review/page.tsx` | Authenticated | `bookingRef` | none | should be noindex | Groups |
| `/groups/booking/[bookingRef]/payment` | `frontend/app/(public)/groups/booking/[bookingRef]/payment/page.tsx` | Authenticated | `bookingRef` | none | should be noindex | Groups |
| `/groups/booking/[bookingRef]/confirmation` | `frontend/app/(public)/groups/booking/[bookingRef]/confirmation/page.tsx` | Authenticated | `bookingRef` | none | should be noindex | Groups |

### Contract

| URL | Current implementation | Backend authority | Mockup | Proposed template | Decision | Compatibility | Fallback risk | Missing dependencies |
|---|---|---|---|---|---|---|---|---|
| `/groups/search` | Search + facets + results list | `GET /groups/search/data`, `/groups/search/results`, `/groups/search/facets`, `/groups/facets` | none | shell-owned | Rebuild | Module `platform.module:public_umrah_groups`; `/umrah-groups/*` aliases redirect here; page key `group-search` exists in `ClientPageKeys` | **High** — Laravel group Blade views | Mockup or shared listing design family |
| `/groups/[packageId]` | Package detail | `GET /groups/package/{id}?format=json` | none | shell-owned | Rebuild | Linked from search results | High | Mockup or shared detail design family |
| `/groups/[packageId]/passengers` | Group passenger capture | `GET\|POST /groups/{packageId}/passengers` | none | shell-owned | Rebuild | Laravel enforces `auth` | High | Page-level noindex |
| `/groups/booking/[bookingRef]/review` | Group review | `GET\|POST /groups/booking/{ref}/review` | none | shell-owned | Rebuild | — | High | Page-level noindex |
| `/groups/booking/[bookingRef]/payment` | Group payment | `GET\|POST /groups/booking/{ref}/payment` | none | shell-owned | Rebuild | — | High | Page-level noindex |
| `/groups/booking/[bookingRef]/confirmation` | Group confirmation | `GET /groups/booking/{ref}/confirmation?format=json`, `GET /groups/booking/{ref}/status` | none | shell-owned | Rebuild | — | High | Page-level noindex |

Public/agent distinction: group search and package detail are public; every passenger/review/payment/confirmation step is gated by Laravel `auth` and is reachable by both Customer and Agent account types.

---

## 9. Customer portal routes (11)

All eleven are guarded server-side by `requireCustomerPortalAccess()` in `frontend/features/auth/server/customer-portal-access.ts` and inherit `robots: { index: false, follow: false }` from `frontend/app/customer/layout.tsx`. Backend authority is Laravel `routes/customer.php` (middleware `web`, `auth`, `agency.context`, `account.type:customer`, `customer.email.portal.verified`). No mockup exists for any of them. All are portal pages, **not** CMS pages, so no CMS template applies. Decision for every row: **Rebuild** on the shared shell.

| URL | Source file | Parameters | Backend authority | Fallback risk | Missing dependencies |
|---|---|---|---|---|---|
| `/customer` | `frontend/app/customer/page.tsx` | — | redirect only | Low | — |
| `/customer/dashboard` | `frontend/app/customer/dashboard/page.tsx` | — | `GET /customer?format=json` | **High** — Laravel Blade dashboard | Overview CTA must retarget to `/#flight-search` (decision 3) |
| `/customer/bookings` | `frontend/app/customer/bookings/page.tsx` | filters/paging | `GET /customer/bookings` | High | Approved list/table design |
| `/customer/bookings/[reference]` | `frontend/app/customer/bookings/[reference]/page.tsx` | `reference` | `GET /customer/bookings/{ref}?format=json` | High | Emitted by `CustomerFacingEmailRenderer::customerBookingCta` |
| `/customer/payments` | `frontend/app/customer/payments/page.tsx` | filters/paging | `GET /customer/payments` | High | — |
| `/customer/invoices` | `frontend/app/customer/invoices/page.tsx` | filters/paging | `GET /customer/invoices`, `/customer/invoices/{ref}?format=json` | High | Document download stays Laravel-authoritative |
| `/customer/profile` | `frontend/app/customer/profile/page.tsx` | — | `GET /customer/profile?format=json`, `POST /profile` (`_method=PATCH`) | High | — |
| `/customer/security` | `frontend/app/customer/security/page.tsx` | — | `POST /password` (`_method=PUT`) | High | — |
| `/customer/support` | `frontend/app/customer/support/page.tsx` | filters/paging | `GET\|POST /customer/support/tickets` (module `support_system`) | High | — |
| `/customer/support/[reference]` | `frontend/app/customer/support/[reference]/page.tsx` | `reference` | ticket detail + reply | High | — |
| `/customer/notifications` | `frontend/app/customer/notifications/page.tsx` | paging | `GET /customer/notifications`, `/customer/notifications/unread-summary?format=json` | High | — |

Laravel customer routes without a Next page (**missing deeper pages**): saved travelers (`saved_travelers` module), payment-proof upload, promo apply/remove, cancellation request, customer document download.

---

## 10. Agent and Agent Staff portal routes (15)

All fifteen are guarded server-side by `requireAgentPortalAccess()` in `frontend/features/auth/server/agent-portal-access.ts`, which admits both `agent` and `agent_staff`, and inherit `robots: { index: false, follow: false }` from `frontend/app/agent/layout.tsx`. Backend authority is Laravel `routes/agent.php` (middleware `web`, `auth`, `agency.context`, `account.type:agent,agent_staff`, plus `agent.permission:*` / `agent.admin`). Visible navigation and capability flags come from Laravel `capabilities` in `GET /agent?format=json` — RBAC must remain Laravel-authoritative. No mockup exists for any of them. Decision for every row: **Rebuild** on the shared shell.

| URL | Source file | Parameters | Backend authority | Fallback risk | Missing dependencies |
|---|---|---|---|---|---|
| `/agent` | `frontend/app/agent/page.tsx` | — | redirect only | Low | — |
| `/agent/dashboard` | `frontend/app/agent/dashboard/page.tsx` | — | `GET /agent?format=json` (incl. capabilities/navigation) | **High** — Laravel Blade dashboard | Overview CTA must retarget to `/#flight-search` (decision 3) |
| `/agent/bookings` | `frontend/app/agent/bookings/page.tsx` | filters/paging | `GET /agent/bookings` (`agent.bookings.view`) | High | — |
| `/agent/bookings/[reference]` | `frontend/app/agent/bookings/[reference]/page.tsx` | `reference` | `GET /agent/bookings/{ref}?format=json` | High | — |
| `/agent/wallet` | `frontend/app/agent/wallet/page.tsx` | — | `GET /agent/wallet?format=json` | High | Wallet behavior must not change during the rebuild |
| `/agent/wallet/ledger` | `frontend/app/agent/wallet/ledger/page.tsx` | filters/paging | `GET /agent/ledger` | High | — |
| `/agent/deposits` | `frontend/app/agent/deposits/page.tsx` | filters/paging | `GET /agent/deposits` | High | — |
| `/agent/deposits/new` | `frontend/app/agent/deposits/new/page.tsx` | — | `GET /agent/deposits/create?format=json`, `POST /agent/deposits` | High | — |
| `/agent/payments` | `frontend/app/agent/payments/page.tsx` | filters/paging | `GET /agent/payments` | High | — |
| `/agent/invoices` | `frontend/app/agent/invoices/page.tsx` | filters/paging | `GET /agent/invoices` | High | — |
| `/agent/profile` | `frontend/app/agent/profile/page.tsx` | — | `GET /agent/profile?format=json`, `POST /profile` | High | Edit permitted only when capability flags allow |
| `/agent/security` | `frontend/app/agent/security/page.tsx` | — | `POST /password` | High | — |
| `/agent/support` | `frontend/app/agent/support/page.tsx` | filters/paging | agent support tickets (`support_system`) | High | — |
| `/agent/support/[reference]` | `frontend/app/agent/support/[reference]/page.tsx` | `reference` | ticket detail + reply | High | — |
| `/agent/notifications` | `frontend/app/agent/notifications/page.tsx` | paging | agent notifications | High | — |

Laravel agent routes without a Next page (**missing deeper pages**): agency management, agent staff management, reports, finance, agent travelers.

`/agent/register` and `/agent/register/submitted` are public guest pages and are counted in §4, not here.

---

## 11. Laravel compatibility and redirect routes (14 redirect-controller entries)

| From | To | Type | Next-side requirement |
|---|---|---|---|
| `/contact` | `/about-us` | permanentRedirect | **Legacy — remove or replace** at route-ownership closure. `/contact` is the canonical Contact page (decision 1). |
| `/pages/terms-and-conditions` | `/terms` | redirect | Preserve |
| `/pages/privacy-policy` | `/privacy` | redirect | Preserve |
| `/register/customer` | `/register` | redirect | Preserve |
| `/register/agent` | `/agent/register/apply` | redirect | Preserve |
| `/password/forgot` | `/forgot-password` | redirect | Preserve |
| `/booking-lookup` | `/lookup-booking` | redirect | Preserve |
| `/flights` | `/` | redirect | Preserve; retarget to `/#flight-search` at closure |
| `/flights/search` | `/` | redirect (`flights.search`) | **Retarget to `/#flight-search`** (decision 3). Retained **only** as a compatibility redirect; Customer and Agent CTAs must target `/#flight-search` directly. |
| `/agent-network` | `/agent/register` | named `agent-network` | Preserve |
| `/devcp` | `/dev/cp` | redirect | Out of public scope |
| `/umrah-groups/*` | `/groups/*` | redirect | Preserve |
| admin `/` | `/admin/dashboard` | redirect | Out of public scope |
| staff `/` | `/staff/dashboard` | redirect | Out of public scope |

---

## 12. Laravel `api/public/*` contracts consumed by the frontend (13)

| Endpoint | Route name | Consumed by |
|---|---|---|
| `GET /api/public/content/csrf-token` | `api.public.content.csrf` | All mutating requests |
| `GET /api/public/content/turnstile-config` | `...turnstile-config` | `/register`, `/agent/register` |
| `GET /api/public/content/site-contact` | `...site-contact` | `/contact`, footer |
| `GET /api/public/content/support/categories` | `...support-categories` | `/support` |
| `GET /api/public/content/pages/{pageKey}` | `...managed-page` | `/about-us`, `/support`, `/faq`, `/terms`, `/privacy`, global |
| `GET /api/public/content/cms/{slug}` | `...cms-page` | `/pages/[slug]` |
| `GET /api/public/content/custom/{slug}` | `...custom-page` | `/[slug]`, `/legal/[slug]` |
| `GET /api/public/content/config` | `...config` | Shell, footer, SEO defaults |
| `GET /api/public/content/homepage` | `...homepage` | `/` |
| `GET /api/public/content/sitemap-routes` | `...sitemap-routes` | `app/sitemap.ts`, `/sitemap` |
| `GET /api/public/auth/session` | `api.public.auth.session` | Portal guards, shell session state |
| `GET /api/public/auth/otp-challenge` | `api.public.auth.otp-challenge` | `/login/otp` |
| `GET /api/public/auth/registration-security-challenge` | `api.public.auth.registration-security-challenge` | `/register` |

Allowed managed page keys (`PublicContentApiPresenter::allowedManagedPageKeys`): `about`, `support`, `faq`, `terms`, `privacy`, `global`.

Full page-key set (`App\Support\Client\ClientPageKeys`): `home`, `about`, `support`, `group-search`, `login`, `register`, `footer`, `global`, `terms`, `privacy`, `faq`, `booking-lookup`, `agent-registration`, plus `custom:{slug}`.

**CMS-capable page count: 13 fixed page keys + unbounded `custom:{slug}` and `cms_pages` slugs.** Next currently exposes CMS content through 8 routes: `/about-us`, `/support`, `/faq`, `/terms`, `/privacy`, `/pages/[slug]`, `/legal/[slug]`, `/[slug]`.

---

## 13. Sitemap, robots, canonical and indexing

| Source | Location | Behavior |
|---|---|---|
| XML sitemap (authoritative) | Laravel `GET /sitemap.xml` → `PublicSitemapController` | Built from `PublicContentApiPresenter::sitemapRoutes()` + `config('app.url')` |
| Next XML sitemap | `frontend/app/sitemap.ts` (`force-dynamic`) | Fetches `sitemap-routes`; falls back to `/`, `/about-us`, `/contact`, `/support`, `/faq`, `/terms`, `/privacy` |
| HTML sitemap | `frontend/app/(public)/sitemap/page.tsx` | Renders the same route list as links |
| Laravel robots | `public/robots.txt` | Disallow `/customer`, `/agent`, `/dashboard`, `/booking`, `/testdash`; sitemap `https://www.jetpakistan.com/sitemap.xml` |
| Next robots | `frontend/app/robots.ts` | Non-production: `Disallow: /`. Production: disallow `/customer`, `/agent`, `/dashboard`, `/booking`, `/flights/results`, `/flights/return-options`, `/lookup-booking`, `/access-denied`, `/laravel`, `/testdash` |

**Approved robots authority (decision 8).** `frontend/app/robots.ts` is the **final public robots authority**. Laravel `public/robots.txt` is aligned to it or retired at cutover. Until cutover the two lists differ: the Next list additionally disallows `/flights/results`, `/flights/return-options`, `/lookup-booking` and `/access-denied`.

### Page-level `noindex,nofollow` requirement (decision 8)

Transactional, tokenized, private and portal pages require page-level
`noindex,nofollow` metadata. Robots disallow directives are crawl instructions
and are **not** a substitute. Applicable page families:

| Page family | Routes requiring `noindex,nofollow` |
|---|---|
| Booking checkout | `/booking/passengers`, `/booking/review`, `/booking/status` |
| Payment | `/booking/payment`, `/booking/payment/manual`, `/booking/payment/card`, `/booking/payment/status`, `/booking/payment/return` |
| Confirmation / Invoice | `/booking/confirmation` (already set), `/booking/invoice` |
| Flight search | `/flights/results` |
| Fare selection | `/flights/return-options`, `/flights/fare-selection` (approved future route) |
| Manage booking | `/lookup-booking` and any guest booking detail state |
| Groups (authenticated steps) | `/groups/[packageId]/passengers`, `/groups/booking/[bookingRef]/review`, `/groups/booking/[bookingRef]/payment`, `/groups/booking/[bookingRef]/confirmation` |
| Auth (tokenized and transient) | `/login/otp`, `/reset-password/[token]`, `/agent/register/submitted`, `/verify-email` (approved future route) |
| Customer portal | All 11 routes (already set at the layout) |
| Agent portal | All 15 routes (already set at the layout) |
| Utility | `/access-denied` |
| CMS | `/legal/[slug]` when the page is missing (already set) |

Public marketing and CMS content pages remain indexable, including `/`,
`/about-us`, `/contact`, `/faq`, `/support`, `/terms`, `/privacy`, `/sitemap`,
`/groups/search`, `/groups/[packageId]`, `/pages/[slug]`, `/[slug]`, `/login`,
`/register`, `/forgot-password` and `/agent/register`.

Explicit page-level noindex today:

1. `frontend/app/customer/layout.tsx` — whole customer tree
2. `frontend/app/agent/layout.tsx` — whole agent tree
3. `frontend/app/(public)/booking/confirmation/page.tsx`
4. `/legal/[slug]` when the page is missing

`noIndexMetadata` exists in `frontend/features/public-content/utils/seo-metadata.ts` but is **used by no page**. Every other private or transactional route relies on robots.txt disallow alone, which is a crawl directive rather than an indexing directive.

Canonical sources: `publicSeoToMetadata` builds canonicals from `NEXT_PUBLIC_APP_URL`; CMS canonicals come from `cms_pages.canonical_url` or the derived `/pages/{slug}`; `/contact` hard-codes its own canonical.

---

## 14. Navigation and footer configuration

Source: `frontend/lib/navigation.ts`, types in `frontend/types/navigation.ts`.

**Header (`primaryNavigation`)** — measured today, with the approved target:

| Item | Current href | Approved target (decisions 3, 9) |
|---|---|---|
| Flights → Search Flights | `/` | `/#flight-search` |
| Flights → Flight Status | `/faq` | **Removed** from target navigation until a real authoritative feature exists |
| Flights → Manage Booking | `/lookup-booking` | unchanged |
| Groups | `/groups/search` | unchanged |
| Support → Help Center | `/support` | unchanged |
| Support → Contact Us | `/contact` | unchanged — `/contact` is canonical (decision 1) |
| Support → FAQs | `/faq` | unchanged — the visible label is FAQs, so the destination is honest |

**Footer (`footerColumns`)** — measured today, with the approved target:

| Column | Current | Approved target |
|---|---|---|
| Explore | `/`, `/groups/search`, `/lookup-booking` | Search link retargets to `/#flight-search` |
| Company | `/about-us`, `/sitemap` | unchanged |
| Support | `/support`, `/contact`, `/faq`, `/lookup-booking`, Baggage → `/faq` | **Baggage is removed**, or relabelled as FAQs only where its visible label is FAQs (decision 9) |
| Legal | `/terms`, `/privacy` | unchanged |
| Social | external absolute URLs | unchanged |

No misleading capability label may point at `/faq`.

**Hrefs outside `navigation.ts` that have no Next page:**

| Href | Where | Approved disposition |
|---|---|---|
| `/flights/search` | Customer and Agent overview CTAs | **Retarget the CTAs to `/#flight-search`** (decision 3). `/flights/search` survives only as a compatibility redirect. |
| `/verify-email` | Auth allowlist / registration fallback | **Becomes a Next notice/result page** with `noindex,nofollow` (decision 2) |
| `/admin/dashboard`, `/staff/dashboard`, `/password/force-change`, `/account/legacy` | `dashboard-allowlist.ts` | Laravel-owned by design, not Next routes |

---

## 15. Fallback and leak risk summary

### Approved route ownership (decision 7)

| Owner | Scope |
|---|---|
| **Next.js** | Normal public browser pages; CMS pages; Customer portal; Agent and Agent Staff portal; themed error and not-found states |
| **Laravel** | `/laravel/*` actions and APIs; authentication, session and CSRF authority; signed verification actions; payment callbacks; supplier, search, booking, PNR and ticketing; downloads and operational actions |

Blade remains **temporary** until parity is proven. Public Blade routes are gated
or retired in **Phase H**, not during the theme build.

### Outstanding risks under that model

| Risk | Detail |
|---|---|
| **Blade dual stack** | Laravel still registers the full public Blade map, including `GET /` and the catch-all `GET /{slug}`. No nginx configuration is committed to the repository to enforce Next ownership. Any request reaching Laravel directly renders Blade. Closed in Phase H per decision 7. |
| **CMS catch-all absorption** | Next-only URLs (`/booking/payment`, `/booking/payment/manual`, `/booking/payment/card`, `/booking/payment/return`, `/booking/status`, and the future `/flights/fare-selection`) have no Laravel GET route and would fall into `ClientManagedPageController@customShow` rather than returning 404. **Laravel reserved-path protection and the final proxy boundary must prevent this** (decision 6). |
| **Signed verification rendering** | `/verify-email/{id}/{hash}` stays Laravel-authoritative, but in the final architecture it must never render the legacy Blade theme; it verifies and redirects to a Next result or login state (decision 2). |
| **Master / Parwaaz branding** | No live email or public UI copy found. Remaining occurrences are audits, tests, config blocklists, dashboard Blade comments and defensive CSS in `public/themes/frontend/jetpakistan/css/booking.css`. Internal only — no user-facing leak identified in this audit. |
| **Unknown CMS template** | No `template` column exists on `cms_pages`, `client_pages` or `client_page_settings`; template resolution must be frontend-side and must never fall back to Blade (see the CMS contract). |

---

## 16. Missing deeper pages

Real Laravel capability with no Next page today:

1. Email verification notice — **approved to be built as a Next page** with `noindex,nofollow` (decision 2). The signed landing `/verify-email/{id}/{hash}` stays Laravel-authoritative and redirects into a Next result or login state.
2. Password confirm / forced password change
3. Social login callback and error states
4. Guest booking detail after lookup (documents, cancellations, promos)
5. Flight detail (`flights/details/{id}`)
6. Multi-city inquiry
7. Customer saved travelers
8. Customer payment-proof upload
9. Customer promo apply/remove
10. Customer cancellation request
11. Agent agency management
12. Agent staff management
13. Agent reports
14. Agent finance
15. Agent travelers
16. Seat selection — **capability-gated**, not permanently removed. Absent while `seat_map_available=false`; a conditional Seats step may be introduced only when an authoritative capability becomes true (decision 5).
17. `/flights/fare-selection` — **approved future route** (decision 4)

---

## 17. Unsupported and dead links

| Link | Location | Approved disposition |
|---|---|---|
| `/flights/search` | Customer and Agent overview CTAs | Retarget CTAs to `/#flight-search`; keep `/flights/search` as a compatibility redirect (decision 3) |
| `/verify-email` | Auth allowlist / registration fallback | Build as a Next notice/result page with `noindex,nofollow` (decision 2) |
| Header "Flight Status" | `frontend/lib/navigation.ts` | **Remove** from target navigation (decision 9) |
| Footer "Baggage" | `frontend/lib/navigation.ts` | **Remove**, or relabel as FAQs only where the visible label is FAQs (decision 9) |
| `/contact` | Header and footer | **Resolved** — `/contact` is canonical and indexable; the Laravel redirect is legacy and is removed or replaced (decision 1) |
| Mock Shell quick actions (Change Flight, Add Baggage, Check Flight Status, Request Support) | `manage-booking` mockup | Map each to a supported destination or remove; no unsupported action ships |
| Contact-form fallback `/laravel/support` | Contact page | Blade is temporary; replaced by the authoritative `POST /support` path before Phase H |
| Portal "Sign out" `/laravel/logout` | Portal shell | Acceptable; Laravel-authoritative by decision 7 |

---

## 18. Decision roll-up

| Decision | Count | Notes |
|---|---|---|
| Keep | 3 | `/access-denied`, `/sitemap`, `/agent/register/submitted` |
| Rebuild | 61 | All remaining measured Next routes, including `/booking/payment`, which becomes the canonical payment-method selector rather than an internal redirect |
| Redirect (Laravel-side, preserve) | 14 | See §11; `/flights/search` retargets to `/#flight-search` |
| Retire | 0 | No Next route is approved for retirement |
| **Build (approved future routes)** | **2** | `/flights/fare-selection` (decision 4) and `/verify-email` (decision 2) |
| Blocked pending approval | 0 | All ten Phase A findings are closed by the JP-PUBLIC-NEXT-THEME-01A decisions |

Measured routes total 64 (3 keep + 61 rebuild). The planned target is **65** Next
page routes once `/flights/fare-selection` exists, subject to implementation
verification. `/booking/seats` is not created.
