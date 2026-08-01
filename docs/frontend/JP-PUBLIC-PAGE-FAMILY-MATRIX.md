# JP-PUBLIC-PAGE-FAMILY-MATRIX

Phase: **JP-PUBLIC-NEXT-THEME-01**
Branch: `phase/jetpk-public-next-theme-rebuild`
Baseline: `111b2925f12369dbcbef139c9b251726a5a785fd`
Authority: [docs/architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md](../architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md) — decisions in §16
Companion: [JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md](JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md)
Status: **Phase A inventory, closed by JP-PUBLIC-NEXT-THEME-01A decisions — no runtime implementation**

---

## 1. Purpose

The route inventory records every route individually. This matrix groups the same 64 measured Next.js routes into fifteen page families so that each family can be designed once, approved once and implemented once. A family is the unit of visual approval; a route is the unit of correctness.

Family coverage adds up to the measured totals: 38 public, 11 Customer, 15 Agent/Agent Staff, **64 measured in all**.

Approved future routes are listed separately and are **not** counted in the measured 64.

---

## 2. Family index

| # | Family | Measured routes | Approved future routes | Access | Architecture phase | Mockup availability |
|---|---|---|---|---|---|---|
| 1 | Home | 1 | — | Public | D | Full |
| 2 | CMS/content | 3 | — | Public | C, E | Partial |
| 3 | Legal | 3 | — | Public | E | None |
| 4 | Support / FAQ / Contact | 3 | — | Public | E | Partial |
| 5 | Flight search | 1 | — | Public | F | Full |
| 6 | Fare selection / return options | 1 | **1** — `/flights/fare-selection` | Public | F | Full, owned by the future route |
| 7 | Booking checkout | 3 | — | Session-scoped | F | Partial |
| 8 | Payment | 5 | — | Session-scoped | F | Partial |
| 9 | Confirmation / Invoice | 2 | — | Session-scoped | F | Partial |
| 10 | Manage booking | 1 | — | Public | F | Full |
| 11 | Groups | 6 | — | Mixed | F | None |
| 12 | Auth | 7 | **1** — `/verify-email` | Guest-only / Public | G | Partial |
| 13 | Customer portal | 11 | — | Customer | G | None |
| 14 | Agent portal | 15 | — | Agent / Agent Staff | G | None |
| 15 | Utility / error / sitemap / not-found | 2 (+2 non-route files) | — | Public | B, H | None |
| | **Total** | **64 measured** | **2 approved future** | | | |

Counted access split: families 1–12 and 15 contribute the 38 public routes; family 13 contributes 11; family 14 contributes 15.

**Planned target: 65 Next page routes** once `/flights/fare-selection` exists, subject to implementation verification. `/verify-email` replaces a route Laravel already owns, so its effect on the final count is confirmed at implementation. No route is approved for retirement.

---

## 3. Family definitions

### Family 1 — Home

**Routes:** `/`

**Shell and component requirements:** `PublicShell`, `PublicHeader`, `PublicFooter`, `PublicContainer`, `PublicSection`, `PublicSectionHeader`, search panel, trust chips, destination cards, offer cards, why-book grid, support call-to-action, travel-inspiration cards, `PublicImageSlot`, `PublicButton`, `PublicCard`.

**Backend authority:** `GET /api/public/content/homepage`, `GET /api/public/content/config`, `GET /airports/search`.

**Mockup:** `public/mockups/home.png`.

**Proposed template:** `landing`.

**Notes:** The homepage is the only search entry point; `/flights` and `/flights/search` redirect here. This family owns the airport autocomplete and trip-type tabs, which must bind to real Laravel airport search rather than the Mock Shell's hardcoded LHE→JED values.

**Phase:** D — build the static visual composition first, then bind content, navigation and search behavior.

---

### Family 2 — CMS/content

**Routes:** `/about-us`, `/pages/[slug]`, `/[slug]`, plus the shared renderer used by family 3.

**Shell and component requirements:** `CmsPageRenderer`, `CmsSection`, `CmsHero`, `CmsRichText`, `CmsImage`, `CmsCardGrid`, `CmsStats`, `CmsTimeline`, `CmsFaq`, `CmsCallout`, `CmsGallery`, plus `.jp-cms-content` for sanitized legacy HTML.

**Backend authority:** `GET /api/public/content/pages/{pageKey}`, `GET /api/public/content/cms/{slug}`, `GET /api/public/content/custom/{slug}`.

**Mockup:** `public/mockups/about.png` for About; none for generic CMS pages.

**Proposed templates:** `hero-content` for About; registry-resolved with `default-content` fallback for slug pages.

**Notes:** This is the family that makes future CMS pages inherit the theme automatically. `/[slug]` is the only catch-all and mirrors the Laravel catch-all, so its reserved-path list must stay synchronized with `frontend/features/public-content/utils/reserved-public-paths.ts`. Governed by [JP-CMS-PAGE-TEMPLATE-AND-DEFAULT-STYLING-CONTRACT.md](JP-CMS-PAGE-TEMPLATE-AND-DEFAULT-STYLING-CONTRACT.md).

**Phase:** C for the renderer, registry, blocks and `.jp-cms-content`; E for the concrete pages.

---

### Family 3 — Legal

**Routes:** `/terms`, `/privacy`, `/legal/[slug]`

**Shell and component requirements:** `PublicShell`, long-form `.jp-cms-content` typography, table of contents, last-updated stamp, `PublicCallout` for notices.

**Backend authority:** `GET /api/public/content/pages/terms`, `.../privacy`, `GET /api/public/content/custom/{slug}`.

**Mockup:** none — uses the shared long-form content design family.

**Proposed template:** `policy`.

**Notes:** `/pages/terms-and-conditions` and `/pages/privacy-policy` redirect into this family and must keep working. The alias map currently hard-coded in the `/legal/[slug]` page (`refund`, `cookies`, `cancellation`, `booking-terms`) should move into the template registry during Phase C. Missing legal pages already emit noindex, which is the correct behavior and must be preserved.

**Phase:** E.

---

### Family 4 — Support / FAQ / Contact

**Routes:** `/support`, `/faq`, `/contact`

**Shell and component requirements:** `PublicShell`, `PageHero` equivalent, support-topic card grid, accordion FAQ, contact-channel cards, `PublicFormField`, form validation and success states, emergency-support callout.

**Backend authority:** `GET /api/public/content/pages/support`, `.../faq`, `GET /api/public/content/support/categories`, `GET /api/public/content/site-contact`, `POST /support`.

**Mockup:** `public/mockups/support.png`; none for FAQ or Contact.

**Proposed templates:** `hero-content` for Support, `faq` for FAQ, **`contact` for Contact**.

**Notes (approved):** `/contact` is the canonical dedicated Contact page, uses the `contact` template and remains indexable (decision 1). The legacy Laravel `permanentRedirect('/contact' → '/about-us')` is removed or replaced during route-ownership closure. Header and footer links to `/contact` remain valid.

Navigation is corrected per decision 9: Flight Status is removed from the target navigation until a real authoritative feature exists, and Baggage is removed or relabelled as FAQs only where its visible label is FAQs. No misleading capability label points at `/faq`.

The contact form's Blade fallback (`/laravel/support`) is replaced by the authoritative `POST /support` path before Phase H.

**Phase:** E.

---

### Family 5 — Flight search

**Routes:** `/flights/results`

**Shell and component requirements:** results shell with filter sidebar, sort tabs, offer cards, fare-family strips, compare-fares control, load-more, empty state, error state, loading skeletons, `PublicBadge`.

**Backend authority:** `GET /flights/results/search`, `/flights/results/data`, `/flights/results/nearby-dates`, `/flights/results/offer`, `POST /flights/results/revalidate-offer`.

**Mockup:** `public/mockups/flight-results.png`.

**Proposed template:** shell-owned, not CMS.

**Notes:** Supplier behavior must not change. All counts, prices, airlines and availability come from Laravel; no Mock Shell fixture value may survive into this family. The page needs an explicit page-level noindex to complement the robots.txt disallow.

**Phase:** F.

---

### Family 6 — Fare selection / return options

This family contains **two separate routes with two separate workflows** (decision 4). They must not be merged.

#### 6a — `/flights/return-options` (measured, exists today)

**Purpose:** presents supplier-returned or supplier-validated return combinations.

**Shell and component requirements:** itinerary pairing header, combination cards, selected state, price-delta labels, `PublicSummaryCard`.

**Backend authority:** `GET /flights/return-options/data`, `POST /flights/select-return-combo`.

**Mockup:** **none.** The Fare Selection mockup must **not** be mapped onto this route.

**Proposed template:** shell-owned.

**Constraint:** outbound and return offers must **never** be stitched manually. Only supplier-returned or supplier-validated combinations may be presented.

#### 6b — `/flights/fare-selection` (approved future route, not in the measured 64)

**Purpose:** dedicated fare-family selection.

**Applies to:** a selected one-way offer, or a validated return pair produced by 6a.

**Position in the journey:** immediately **before** `/booking/passengers`.

**Shell and component requirements:** itinerary summary header, fare-family cards with benefit lists, selected state, price-delta labels, compare-fares control, `PublicSummaryCard`.

**Backend authority:** authoritative offer retrieval and revalidation — `GET /flights/results/offer`, `POST /flights/results/revalidate-offer`.

**Mockup:** `public/mockups/fare-selection.png` — **this route owns the approved Fare Selection mockup.**

**Proposed template:** shell-owned.

**Indexing:** `noindex,nofollow`.

**Phase:** F.

---

### Family 7 — Booking checkout

**Routes:** `/booking/passengers`, `/booking/review`, `/booking/status`

**Shell and component requirements:** `PublicStepper` with a **conditional** Seats step, traveler form blocks, contact-information block, `PublicFormField`, fare-rules disclosure, policy-acknowledgement checkboxes, `PublicSummaryCard` order sidebar, important-notices callout.

**Backend authority:** `GET|POST /booking/passengers`, `GET|POST /booking/review?format=json`, `GET /booking/checkout-state?format=json`, `POST /booking/{booking}/accept-updated-fare`, `POST /booking/{booking}/decline-updated-fare`.

**Mockup:** `public/mockups/passengers.png`, `public/mockups/review.png`.

**Proposed template:** shell-owned.

**Seat capability (decision 5):** the current capability is `seat_map_available=false`, so the current journey omits the seat page and the Seats step. Seats are **capability-gated, not permanently removed**. The design system may support a conditional Seats step that renders only when an authoritative future capability becomes true. `/booking/seats` must not be created while no authoritative seat map exists, and the Mock Shell fixture seat map and seat 3C must never be ported.

**Notes:** `/flights/fare-selection` sits immediately before this family. Order-summary totals must come from Laravel checkout state, never from the Mock Shell's fixed figures. Fare-change accept/decline is an existing authoritative behavior that must survive the rebuild unchanged.

**Phase:** F.

---

### Family 8 — Payment

**Final route model (decision 6):**

| Route | Responsibility |
|---|---|
| `/booking/payment` | **Canonical payment-method selector** |
| `/booking/payment/manual` | Manual Payment workflow |
| `/booking/payment/card` | **AbhiPay secure handoff only** |
| `/booking/payment/status` | Payment-status state |
| `/booking/payment/return` | Gateway-return state |

Five measured routes. `/booking/payment` is a rendered selector page, not an internal redirect. `/manual` and `/card` are preserved as compatibility routes.

**Shell and component requirements:** payment-method selector, manual-instruction cards, proof-submission affordance, security assurances, `PublicSummaryCard`, pending/processing/failed/success states, gateway-return interstitial.

**Explicitly not built:** a direct card-details form. **Next.js must never collect a card number, CVV or expiry.** `/booking/payment/card` performs an AbhiPay secure handoff only. The card-details panel depicted in the payment mockup is not implemented.

**Backend authority:** `GET /booking/checkout-state?format=json`, `POST {startEndpoint}?format=json`, `GET /booking/payment/status?format=json`, AbhiPay start and callback, `/payment/success|cancel|decline`. Payment callbacks remain Laravel-owned.

**Mockup:** `public/mockups/payment.png`, minus the card-details panel.

**Proposed template:** shell-owned.

**Indexing:** all five routes require page-level `noindex,nofollow`.

**Notes:** Payment behavior must not change during the rebuild. Four of these URLs exist only in Next and have no Laravel GET route. Laravel reserved-path protection and the final proxy boundary must prevent them from entering the CMS catch-all; this must be closed before Phase H. No fake payment control from the Mock Shell may be carried over.

**Phase:** F.

---

### Family 9 — Confirmation / Invoice

**Routes:** `/booking/confirmation`, `/booking/invoice`

**Shell and component requirements:** success hero, booking-summary card, passenger-summary card, itinerary card, payment-information card, what-next stepper, itinerary delivery actions, print-safe invoice layout.

**Backend authority:** `GET /booking/confirmation?format=json`, `GET /booking/invoice?format=json`; document downloads remain Laravel-authoritative.

**Mockup:** `public/mockups/success.png`; none for the invoice.

**Proposed template:** shell-owned.

**Notes:** `/booking/confirmation` already carries a correct page-level noindex and must keep it. The Mock Shell's confirmation screen contains a fixture PNR and inert download and email buttons; every action here must be wired to a real Laravel endpoint or omitted.

**Phase:** F.

---

### Family 10 — Manage booking

**Routes:** `/lookup-booking`

**Shell and component requirements:** hero with lookup form, `PublicFormField`, quick-action card grid, help and security assurance panels, trust strip.

**Backend authority:** `POST /lookup-booking` (`booking.lookup`), guest access, documents, cancellations and promo endpoints.

**Mockup:** `public/mockups/manage-booking.png`.

**Proposed template:** `hero-content`.

**Notes:** `/booking-lookup` redirects here and this URL is emitted in booking emails, so it must not change. The quick-action cards in the mockup (Change Flight, Add Baggage, Check Flight Status, Request Support) must each be mapped to a supported destination or removed; several have no supported route today. The guest booking detail screen reached after a successful lookup is a missing deeper page.

**Phase:** F.

---

### Family 11 — Groups

**Routes:** `/groups/search`, `/groups/[packageId]`, `/groups/[packageId]/passengers`, `/groups/booking/[bookingRef]/review`, `/groups/booking/[bookingRef]/payment`, `/groups/booking/[bookingRef]/confirmation`

**Shell and component requirements:** faceted search sidebar, package listing cards, package detail layout, group passenger capture, group review, group payment, group confirmation. Reuses the booking stepper and summary card patterns from families 7–9.

**Backend authority:** `GET /groups/search/data`, `/groups/search/results`, `/groups/search/facets`, `/groups/facets`, `GET /groups/package/{id}?format=json`, `GET|POST /groups/{packageId}/passengers`, `GET|POST /groups/booking/{ref}/review`, `GET|POST /groups/booking/{ref}/payment`, `GET /groups/booking/{ref}/confirmation?format=json`, `GET /groups/booking/{ref}/status`.

**Mockup:** none — the Mock Shell links Groups to `href="#"`.

**Proposed template:** shell-owned; the `group-search` page key exists in `ClientPageKeys` for editorial copy.

**Notes:** Search and package detail are public; every subsequent step is gated by Laravel `auth` and is open to both Customer and Agent accounts. The four authenticated steps need page-level noindex. `/umrah-groups/*` aliases redirect into this family and must keep working. Gated by `platform.module:public_umrah_groups`.

**Phase:** F.

---

### Family 12 — Auth

**Measured routes (7):** `/login`, `/login/otp`, `/register`, `/forgot-password`, `/reset-password/[token]`, `/agent/register`, `/agent/register/submitted`

**Approved future route (1):** `/verify-email` — a Next notice/result page with `noindex,nofollow` (decision 2).

**Shell and component requirements:** split auth layout with a benefits panel, auth card, `PublicFormField`, password-visibility control, conditional social-login buttons, OTP entry with resend timer, multi-step agent application, submitted confirmation state, verification notice and result states.

**Backend authority:** `POST /login`, `/login/otp`, `/login/otp/resend`, `/register`, `/agent/register`, `/forgot-password`, `/reset-password`; `GET /api/public/auth/session`, `/otp-challenge`, `/registration-security-challenge`, `/api/public/content/turnstile-config`, `/api/public/content/csrf-token`.

**Mockup:** `public/mockups/login.png`, `public/mockups/signup.png`.

**Proposed template:** shell-owned auth layout.

**Email verification (decision 2):** `/verify-email` becomes a Next notice/result page. `/verify-email/{id}/{hash}` remains a Laravel-authoritative signed action — Laravel verifies the signature and account, then redirects to a Next result or login state. In the final architecture that signed action must never render the legacy Blade theme. **Signature verification must not move into Next.js.**

**Social login (decision 11):** social-login controls are conditional on authoritative enabled providers and functional callbacks. Where a provider is not enabled or its callback is not functional, the control is **omitted**, not disabled or faked.

**Notes:** OTP demo behavior must not be removed. Turnstile and the registration security challenge must remain wired. `/login/otp`, `/reset-password/[token]`, `/agent/register/submitted` and `/verify-email` all require page-level `noindex,nofollow`.

**Phase:** G.

---

### Family 13 — Customer portal

**Routes:** `/customer`, `/customer/dashboard`, `/customer/bookings`, `/customer/bookings/[reference]`, `/customer/payments`, `/customer/invoices`, `/customer/profile`, `/customer/security`, `/customer/support`, `/customer/support/[reference]`, `/customer/notifications`

**Shell and component requirements:** portal shell with side navigation, page header, stat cards, filterable data tables, detail layouts, form sections, notification list, empty/loading/error states.

**Backend authority:** Laravel `routes/customer.php` behind `web`, `auth`, `agency.context`, `account.type:customer`, `customer.email.portal.verified`. Access is enforced server-side by `requireCustomerPortalAccess()`.

**Mockup:** none.

**Proposed template:** none — these are application pages, not CMS pages.

**Notes:** Already `noindex,nofollow` via the layout, which is correct and satisfies decision 8. The dashboard overview CTA must be retargeted to the canonical search target **`/#flight-search`** (decision 3); `/flights/search` survives only as a compatibility redirect. Missing deeper pages: saved travelers, payment-proof upload, promo apply/remove, cancellation request, document download.

**Phase:** G.

---

### Family 14 — Agent portal

**Routes:** `/agent`, `/agent/dashboard`, `/agent/bookings`, `/agent/bookings/[reference]`, `/agent/wallet`, `/agent/wallet/ledger`, `/agent/deposits`, `/agent/deposits/new`, `/agent/payments`, `/agent/invoices`, `/agent/profile`, `/agent/security`, `/agent/support`, `/agent/support/[reference]`, `/agent/notifications`

**Shell and component requirements:** same portal shell as family 13, plus wallet balance cards, ledger tables, deposit submission forms and capability-gated navigation.

**Backend authority:** Laravel `routes/agent.php` behind `web`, `auth`, `agency.context`, `account.type:agent,agent_staff`, plus `agent.permission:*` and `agent.admin`. Access is enforced server-side by `requireAgentPortalAccess()`, which admits both `agent` and `agent_staff`.

**Mockup:** none.

**Proposed template:** none — application pages.

**Notes:** Agent and Agent Staff share one route tree. Visible navigation and every edit affordance are driven by the Laravel `capabilities` payload from `GET /agent?format=json`; RBAC must remain Laravel-authoritative and must not be reimplemented in the frontend. Wallet, deposit and ledger behavior must not change during the rebuild. The dashboard overview CTA must be retargeted to `/#flight-search` (decision 3). Already `noindex,nofollow` via the layout, satisfying decision 8. Missing deeper pages: agency management, staff management, reports, finance, travelers.

**Phase:** G.

---

### Family 15 — Utility / error / sitemap / not-found

**Routes:** `/access-denied`, `/sitemap`

**Non-route files in this family:** `frontend/app/error.tsx`, `frontend/app/not-found.tsx`.

**Shell and component requirements:** minimal shell-wrapped message layout, `PublicCallout`, recovery links, HTML sitemap link list.

**Backend authority:** `GET /api/public/content/sitemap-routes` for `/sitemap`; session shape for `/access-denied`.

**Mockup:** none.

**Proposed template:** `default-content`.

**Notes:** `not-found.tsx` is the safety net for the whole public surface and must never render Blade, Master or Parwaaz content. `/access-denied` needs an explicit page-level noindex. This family is also where the visual lab (Phase B) proves error, empty and loading states before any page family is built.

**Phase:** B for the state catalog; H for final no-fallback regression.

---

## 4. Family to architecture phase mapping

| Phase | Scope | Families |
|---|---|---|
| **B — Design system and visual lab** | Noindex development catalog: typography, colors, buttons, fields, tabs, cards, image slots, alerts, callouts, CMS blocks, booking stepper, summary cards, header/footer, light/dark, responsive states | Cross-cutting; state proofs for 15 |
| **C — Shared shell and CMS renderer** | `PublicShell`, tokens, `CmsPageRenderer`, structured blocks, `.jp-cms-content`, template registry, safe states | 2, and the shell used by all families |
| **D — Homepage** | Static composition first, then content, navigation and search binding | 1 |
| **E — Public content pages** | About, Support, policies, generic CMS pages, supported destination/offer/article pages | 2, 3, 4 |
| **F — Search and booking families** | Results; return options and the new fare-selection route; passengers, review, payment, success; manage booking; capability-gated Seats step | 5, 6a, 6b, 7, 8, 9, 10, 11 |
| **G — Authentication and portals** | Login, register, verification notice and reset flows, Customer pages, Agent and Agent Staff pages | 12, 13, 14 |
| **H — No-fallback and final regression** | Gate or retire public Blade routes; align or retire Laravel `robots.txt`; remove the legacy `/contact` redirect; close CMS catch-all absorption; verify no Master/Parwaaz leakage, no dead navigation, no fake controls, CMS inheritance, intentional redirects, noindex, visual and functional tests, accessibility, responsive and dark behavior | All |

---

## 5. Implementation sequence

The sequence follows dependency order, not route count.

1. **B** — Approve the design system and visual lab. Nothing else may start until tokens, typography and component states are signed off.
2. **C** — Build the shared shell and the CMS renderer. Families 2, 3 and 4 cannot begin without the template registry and `.jp-cms-content`.
3. **D** — Homepage. It is the highest-traffic page, has a complete mockup and exercises the shell, search panel and card grids that later families reuse.
4. **E** — Public content pages. These validate that new CMS pages inherit the theme automatically.
5. **F** — Search and booking. Highest risk: supplier, fare, payment and PNR behavior must remain untouched. Build in flow order — results, return options, passengers, review, payment, confirmation, invoice, manage booking, then groups.
6. **G** — Authentication and portals. Auth first, then Customer, then Agent and Agent Staff, because portal guards depend on the session contract established by the auth family.
7. **H** — Final regression across all fifteen families.

---

## 6. Closed decisions and remaining implementation obligations

All Phase A blockers are closed by the JP-PUBLIC-NEXT-THEME-01A decisions. What remains is implementation work, scheduled by phase.

| Family | Approved decision | Implementation obligation | Due by |
|---|---|---|---|
| 4 — Support/FAQ/Contact | `/contact` is canonical, uses the `contact` template, stays indexable | Remove or replace the legacy Laravel `/contact` → `/about-us` redirect | Phase H |
| 4 — Support/FAQ/Contact | Flight Status removed; Baggage removed or relabelled as FAQs | Update `frontend/lib/navigation.ts` | Phase E |
| 6a — Return options | Supplier-validated combinations only | Never stitch outbound and return offers manually; do not apply the Fare Selection mockup here | Phase F |
| 6b — Fare selection | New dedicated route owning the Fare Selection mockup | Create `/flights/fare-selection` before `/booking/passengers` | Phase F |
| 7 — Booking checkout | Seats are capability-gated | Conditional Seats step driven by an authoritative capability; `seat_map_available=false` today | Phase F |
| 8 — Payment | Five-route model; no card-details form | Never collect card number, CVV or expiry; keep `/manual` and `/card` as compatibility routes | Phase F |
| 8 — Payment | Catch-all protection | Laravel reserved-path protection plus the final proxy boundary | Phase H |
| 10 — Manage booking | No unsupported actions | Map each quick-action card to a supported destination or remove it | Phase F |
| 12 — Auth | `/verify-email` becomes a Next page; signed action stays Laravel | Build the notice/result page; Laravel redirects into it; signature verification stays in Laravel | Phase G |
| 12 — Auth | Conditional social login | Omit controls without an enabled provider and a functional callback | Phase G |
| 13, 14 — Portals | Canonical search target is `/#flight-search` | Retarget Customer and Agent CTAs | Phase G |
| 2 — CMS/content | Frontend template resolution approved; no database column | Implement the registry frontend-side; a Dev CP-selectable field is deferred to a separate backend phase | Phase C |
| All | `frontend/app/robots.ts` is the robots authority | Align or retire Laravel `public/robots.txt`; add page-level `noindex,nofollow` to every transactional, tokenized, private and portal family | Phase H |
| All | Next owns pages; Laravel owns actions and APIs | Gate or retire public Blade routes; Blade is temporary until parity is proven | Phase H |
