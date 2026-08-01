# JP-MOCK-SHELL-INTEGRATION-MAP

Phase: **JP-PUBLIC-NEXT-THEME-01**
Branch: `phase/jetpk-public-next-theme-rebuild`
Baseline: `111b2925f12369dbcbef139c9b251726a5a785fd`
Authority: [docs/architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md](../architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md) — decisions in §16
Reference scaffold: `C:\Users\khadi\JetPakistan-NextJS-Mock-Shell` — **read-only, not modified by this phase**
Status: **Phase A inventory, closed by JP-PUBLIC-NEXT-THEME-01A decisions — no files copied, no runtime implementation**

The Mock Shell remains a visual and component scaffold only. **No shell file is copied wholesale** (decision 11).

---

## 1. What the Mock Shell is and is not

The standalone shell is a presentation-only Next.js App Router project. It is a visual and component scaffold, never a replacement application and never a source of business logic.

| Property | Value |
|---|---|
| Framework | Next 15.5.7, React 19.1.0 |
| Language | Plain `.js` — no TypeScript |
| Styling | One hand-written stylesheet, `app/globals.css` — **no Tailwind, no PostCSS** |
| App routes | 14 (13 mockup families + `/preview`) |
| Components | 13 flat files in `components/`, no subfolders |
| Fixture data | `lib/mock-data.js` plus inline page constants |
| Mockup images | 13 PNGs in `public/mockups/` |
| API calls | **None** — no `fetch`, no XHR, no server actions, no route handlers |
| Auth | **None** |
| Middleware | **None** |
| CMS rendering | **None** |
| robots / sitemap / noindex | **None** |
| Simulated async | **None** — no `setTimeout` fakes; fake behavior is inert UI |

The target repository, by contrast, uses TypeScript, Tailwind plus `frontend/styles/tokens.css`, server-side portal guards, a `/laravel/:path*` rewrite with XSRF CSRF, and thirteen `api/public/*` contracts. Nothing may be copied file-for-file.

---

## 2. Coverage summary

### Measured baseline (unchanged)

| Metric | Value |
|---|---|
| Real Next routes covered by the shell | **11** |
| Total real Next routes | **64** |
| **Coverage of real routes** | **11 / 64 = 17.2%** |
| Total public Next routes | **38** |
| **Coverage of public routes** | **11 / 38 = 28.9%** |
| Shell pages that map to a route that exists today | 11 of 13 mockup families (84.6%) |
| Shell pages with no counterpart that exists today | 3 (`/flights/fare-selection`, `/booking/seats`, `/preview`) |
| Real routes absent from the shell | **53** |

### Planned coverage after decision 4

`/flights/fare-selection` is an approved future route that **owns** the Fare Selection mockup, so the shell page becomes legitimate once the route is built.

| Metric | Value |
|---|---|
| Planned covered routes | **12** |
| Planned target route count | **65** |
| **Planned coverage** | **12 / 65 = 18.5%**, subject to implementation verification |
| Shell pages that remain without a counterpart | 2 (`/booking/seats`, `/preview`) |

The measured baseline figures above are **not** revised by this projection.

---

## 3. Covered real routes (11)

| Real route | Shell page | Mockup PNG | Adaptation required |
|---|---|---|---|
| `/` | `app/page.js` | `public/mockups/home.png` | Bind hero, destinations, offers, inspiration and search panel to `GET /api/public/content/homepage` and `GET /airports/search`. Remove all fixture prices and routes. |
| `/about-us` | `app/about-us/page.js` | `public/mockups/about.png` | Replace hardcoded mission, timeline and stats with `GET /api/public/content/pages/about`. Convert to CMS blocks so the page stays editable. |
| `/support` | `app/support/page.js` | `public/mockups/support.png` | Bind topics to `GET /api/public/content/support/categories`, FAQ accordion and contact channels to `pages/support` and `site-contact`, and the form to `POST /support`. |
| `/flights/results` | `app/flights/results/page.js` | `public/mockups/flight-results.png` | Replace the three fixture offers and the hardcoded "12 flights found" with real supplier results. Filters, sort and paging must drive real query state. |
| `/booking/passengers` | `app/booking/passengers/page.js` | `public/mockups/passengers.png` | Bind to `GET\|POST /booking/passengers`. The progress strip renders a **conditional** Seats step that appears only when an authoritative seat capability is true; with `seat_map_available=false` it is omitted (decision 5). |
| `/booking/review` | `app/booking/review/page.js` | `public/mockups/review.png` | Bind to `GET\|POST /booking/review?format=json`. Remove the fixture traveler and contact values. Wire the fare-change accept/decline path. |
| `/booking/payment` | `app/booking/payment/page.js` | `public/mockups/payment.png` | `/booking/payment` is the **canonical payment-method selector** (decision 6). The mockup's method selector, manual-instruction cards and security assurances are adopted. **The card-details panel is not implemented** — Next.js must never collect a card number, CVV or expiry, and `/booking/payment/card` is an AbhiPay secure handoff only. No payment behavior may change. |
| `/booking/confirmation` | `app/booking/confirmation/page.js` | `public/mockups/success.png` | Bind to `GET /booking/confirmation?format=json`. Remove the fixture PNR. Every action button must reach a real endpoint or be omitted. |
| `/login` | `app/login/page.js` | `public/mockups/login.png` | Use the real auth form, CSRF and session contract. Retain OTP demo behavior downstream. |
| `/register` | `app/register/page.js` | `public/mockups/signup.png` | Bind to `POST /register` plus Turnstile and the registration security challenge. |
| `/lookup-booking` | `app/lookup-booking/page.js` | `public/mockups/manage-booking.png` | Bind to `POST /lookup-booking`. Each quick-action card must map to a supported destination or be removed. |

---

## 4. Shell pages without a route that exists today (3)

| Shell route | Status | Rule |
|---|---|---|
| `/flights/fare-selection` | **Approved as a future real route** (decision 4) | `/flights/fare-selection` becomes a new dedicated fare-family selection route that applies to a selected one-way offer or a validated return pair, performs authoritative offer retrieval and revalidation, and sits before `/booking/passengers`. **It owns the approved Fare Selection mockup.** The mockup must **not** be mapped onto `/flights/return-options`, which is a separate workflow presenting supplier-returned or supplier-validated return combinations and must never stitch outbound and return offers manually. |
| `/booking/seats` | **Must not be created or ported** | No authoritative seat map exists; the current capability is `seat_map_available=false`, so the current journey omits the seat page and the Seats step. Seats are **capability-gated, not permanently removed** — the design system may support a conditional Seats step only when an authoritative future capability becomes true (decision 5). The shell's seat page, its 42-seat fixture array and its "3C" selection must never be ported. |
| `/preview` | **Development catalog only** | `app/preview/page.js`, `components/PreviewGallery.js` and `public/preview.html` are shell tooling. The equivalent in this repository is the Phase B visual lab, which must be noindex and must be built fresh rather than copied. |

---

## 5. Real routes absent from the Mock Shell (53)

All 53 must be designed from the shared design system rather than from a mockup.

### Utility (1)

1. `/access-denied`

### Auth (5)

2. `/login/otp`
3. `/forgot-password`
4. `/reset-password/[token]`
5. `/agent/register`
6. `/agent/register/submitted`

### Public content, legal and CMS (8)

7. `/contact`
8. `/faq`
9. `/privacy`
10. `/terms`
11. `/sitemap`
12. `/pages/[slug]`
13. `/legal/[slug]`
14. `/[slug]`

### Flight search (1)

15. `/flights/return-options` — remains absent from the shell. The Fare Selection mockup belongs to `/flights/fare-selection`, not to this route, so this workflow is designed from the shared design system.

### Booking and payment (6)

16. `/booking/payment/manual`
17. `/booking/payment/card`
18. `/booking/payment/status`
19. `/booking/payment/return`
20. `/booking/invoice`
21. `/booking/status`

### Groups (6)

22. `/groups/search`
23. `/groups/[packageId]`
24. `/groups/[packageId]/passengers`
25. `/groups/booking/[bookingRef]/review`
26. `/groups/booking/[bookingRef]/payment`
27. `/groups/booking/[bookingRef]/confirmation`

### Customer portal (11)

28. `/customer`
29. `/customer/dashboard`
30. `/customer/bookings`
31. `/customer/bookings/[reference]`
32. `/customer/payments`
33. `/customer/invoices`
34. `/customer/profile`
35. `/customer/security`
36. `/customer/support`
37. `/customer/support/[reference]`
38. `/customer/notifications`

### Agent and Agent Staff portal (15)

39. `/agent`
40. `/agent/dashboard`
41. `/agent/bookings`
42. `/agent/bookings/[reference]`
43. `/agent/wallet`
44. `/agent/wallet/ledger`
45. `/agent/deposits`
46. `/agent/deposits/new`
47. `/agent/payments`
48. `/agent/invoices`
49. `/agent/profile`
50. `/agent/security`
51. `/agent/support`
52. `/agent/support/[reference]`
53. `/agent/notifications`

The shell provides **no** portal design at all. Families 13 and 14 — 26 of the 53 absent routes — are the single largest design gap in the rebuild.

The approved future route `/verify-email` (decision 2) is also absent from the shell and is not counted in the 53, since it is not part of the measured 64.

---

## 6. Reusable visual and component architecture

Structural ideas worth adopting. Each is a pattern to reimplement in TypeScript against Tailwind and the repository tokens, not a file to copy.

| Shell file | Visual purpose | Target responsibility | Retained | Discarded |
|---|---|---|---|---|
| `components/Shell.js` | Header + `<main>` + optional footer | `PublicShell.tsx` | Composition boundary | Nothing operational |
| `components/SiteHeader.js` | Sticky public header: brand, nav, theme, currency, login, Book Now | `PublicHeader.tsx` | Layout, sticky behavior, brand placement | `href="#"` Groups link, inert PKR button |
| `components/SiteFooter.js` | Green gradient footer, brand, four link columns | `PublicFooter.tsx` | Column structure, gradient treatment | `href="#"` legal links, newsletter placeholder copy |
| `components/AuthShell.js` | Split auth layout: visual panel + form card | Auth layout for family 12 | Split proportions, benefits panel | Inert form buttons |
| `components/PageHero.js` | Eyebrow, title, copy, action, decorative art | `PublicSection` + hero variant | Hero anatomy, decorative slot | Hardcoded copy |
| `components/BookingProgress.js` | Numbered booking step strip | `PublicStepper.tsx` | Step-strip visual language; the Seats step becomes **conditional** on an authoritative capability and is omitted while `seat_map_available=false` | **The fixed 8-step `bookingSteps` fixture array** |
| `components/OrderSummary.js` | Sticky booking summary sidebar | `PublicSummaryCard.tsx` | Sticky sidebar layout, line-item rhythm | **All money values and the inert CTA** |
| `components/SearchPanel.js` | Tabbed flight search panel | Home search panel | Tab layout, field grouping, swap-control placement | **All hardcoded route, date and passenger values; the no-op search button** |
| `components/FlightCard.js` | One flight-offer row | Results offer card | Row anatomy: airline, times, duration, stops, price | **The inert Select button** |
| `components/FormControls.js` | `Field` and `SelectField` primitives | `PublicFormField.tsx` | Label/control pairing | No validation, error or help states exist — must be added |
| `components/ThemeProvider.js` | `light` / `dark` via `data-theme` + localStorage | Repository theme layer | The `data-theme` attribute strategy | The `jp-mock-theme` storage key |
| `components/ThemeToggle.js` | Sun/moon toggle | Header theme control | Control placement | — |
| `components/PreviewGallery.js` | Grid of mockup cards | Phase B visual lab | Catalog organization idea | The mockup fixture list |

Components the shell does **not** provide and which must be designed fresh: `PublicContainer`, `PublicSectionHeader`, `PublicBadge`, `PublicCallout`, `PublicTabs`, `PublicImageSlot`, the entire `cms/` block set, all portal shell and table components, and every empty, loading, error and disabled state.

---

## 7. Styling requiring mockup refinement

The shell defines tokens as plain CSS custom properties. The repository uses Tailwind plus `frontend/styles/tokens.css`. These must be reconciled by mapping, not by importing `globals.css`.

Shell light tokens (`app/globals.css` `:root`):

```css
--bg: #f6faf8;          --surface: #ffffff;
--surface-soft: #eef6f2; --surface-strong: #dcece4;
--text: #10231d;        --muted: #667a72;
--border: #d8e5df;      --brand: #0b6b3a;
--brand-2: #3fb62b;     --brand-dark: #06532d;
--radius: 18px;         --radius-sm: 12px;
--container: 960px;     --font: Inter, ui-sans-serif, system-ui, ...;
```

Shell dark tokens (`html[data-theme="dark"]`):

```css
--bg: #08140f;          --surface: #0e2119;
--surface-soft: #132a20; --surface-strong: #193428;
--text: #f5fff9;        --muted: #a9beb4;
--border: #274537;      --brand: #53c83a;
--brand-2: #72d64d;     --brand-dark: #2c9b2e;
```

Refinement required before adoption:

| Item | Issue | Required action |
|---|---|---|
| `--container: 960px` | Too narrow for the results, portal and table families | Set a repository container scale; do not inherit 960px |
| Brand greens | Also hardcoded outside tokens: footer gradient `#086236` → `#064b2b`, About CTA `#0f7d42` | Consolidate into tokens; no raw hex in components |
| Dark-mode brand shift | `--brand` moves from `#0b6b3a` to `#53c83a` | Verify AA contrast in both themes before adoption |
| Inter font | Loaded via CSS stack, not `next/font` | Use the repository font strategy |
| Breakpoints | Only `900px` and `640px` | Extend to the repository's desktop, tablet and mobile contract |
| Focus states | Not defined in the shell | Add `:focus-visible` styling; no global focus suppression, no persistent blue or cyan glow |
| Shadows and radii | `--shadow`, `--shadow-soft`, `18px` / `12px` radii | Adopt as a starting point, then normalize into the token scale |

---

## 8. Fake or unsupported data and actions that must not be copied

Every item below is presentation-only in the shell and has no backing behavior. None may survive into this repository.

### Fixture data

| Source | Content |
|---|---|
| `lib/mock-data.js` → `destinations` | LHE–JED PKR 48,950; ISB–DXB 42,500; KHI–IST 67,800; LHE–LON 92,000; ISB–RUH 46,300 |
| `lib/mock-data.js` → `offers` | Summer Saver 20% OFF, Weekend Getaway 15% OFF, Family Travel Deal 10% OFF |
| `lib/mock-data.js` → `inspiration` | Four fabricated articles |
| `lib/mock-data.js` → `flightOffers` | Three fabricated PIA / Saudia / Emirates LHE→JED offers |
| `lib/mock-data.js` → `bookingSteps` | Eight-step array **including `Seats`** |
| `lib/mock-data.js` → `previewPages` | Shell catalog fixtures |
| `public/preview.html` | Duplicate catalog array |

### Hardcoded LHE→JED values

| Location | Value |
|---|---|
| `components/SearchPanel.js:7-22` | FROM LHE, TO JED, 20 Jun 2026, 1 Passenger Economy |
| `app/flights/fare-selection/page.js:11` | LHE→JED 09:15–13:20; fares Basic 112,500 / Value 119,200 / Flex 134,500 |
| `components/OrderSummary.js:5-14` | Route LHE→JED |

### Fixed totals

| Location | Value |
|---|---|
| `components/OrderSummary.js:5-14` | Base PKR 102,000, taxes PKR 17,200, **total PKR 119,200** |
| `app/flights/results/page.js:10` | "12 flights found" |
| `app/about-us/page.js` | `120+ Destinations`, `1M+ Travelers`, `99.2% Satisfaction` |

### Hardcoded seat map

| Location | Content |
|---|---|
| `app/booking/seats/page.js:5` | 42 seats; unavailable indices `[2,7,13,20,21,32]`; selected index 16 |
| `app/booking/seats/page.js:11` | Seat buttons and a summary displaying **3C** with no inventory or API behind it |
| `app/booking/passengers/page.js:10` | Note that seats are omitted from the operational journey |

**This is the highest-risk item in the shell.** Porting it would simulate production capability that does not exist. The fixture seat map and seat 3C must never be ported under any future capability state; when an authoritative seat map becomes available, the conditional Seats step is built against that authority, not against this fixture.

### Fake PNR and traveler identity

| Location | Value |
|---|---|
| `app/booking/confirmation/page.js:10` | Booking reference **JPK7X2C**, Payment Paid, Ticketing pending, PNR "Fixture only" |
| `app/lookup-booking/page.js:9` | Placeholder reference `JPK7X2C` |
| `app/booking/review/page.js:9` | Traveler "Khadija Example", contact `sample@example.com` |

### Inert buttons

| Location | Control |
|---|---|
| `app/login/page.js:8` | Log In |
| `app/register/page.js:8` | Create Account |
| `components/SearchPanel.js:7-22` | Search Flights; swap control at `:17` |
| `components/FlightCard.js:9` | Select |
| `app/flights/fare-selection/page.js:11` | Select fare, Continue |
| `app/booking/payment/page.js:9-13` | Continue to Secure Payment, Submit for Review, Pay Securely |
| `components/OrderSummary.js:5-14` | Continue / Pay |

### Card-data collection — prohibited outright (decision 6)

Any card-details treatment shown in the payment mockup or shell — card number, cardholder name, expiry, CVV, save-card toggle — must **not** be implemented. **Next.js must never collect a card number, CVV or expiry.** `/booking/payment/card` is an AbhiPay secure handoff only. This is a security constraint, not a styling preference, and it overrides the mockup.
| `app/lookup-booking/page.js:9` | Find Booking |
| `app/support/page.js:8-11` | Search Help, Send Message, View Support Options |
| `app/booking/confirmation/page.js:10` | Download Itinerary, Email Confirmation |
| `app/booking/review/page.js:9` | Edit controls, consent checkbox |
| `components/SiteHeader.js:24` | PKR currency button |

### `href="#"` links

| Location | Link |
|---|---|
| `components/SiteHeader.js:5-7` | Groups |
| `components/SiteFooter.js:3-8` | Groups, Terms & Conditions, Privacy Policy |
| `app/login/page.js:8` | Forgot password? |
| `app/page.js:32-33, 44` | View all offers, View all articles |

### Fake newsletter

`components/SiteFooter.js:26-28` — the "Stay Updated" block is copy only, with the newsletter form intentionally omitted. The repository footer must either implement a real subscription endpoint or omit the block entirely. No non-functional signup field may ship.

### Unsupported navigation and actions

- Header "Book Now" points at `/flights/results` with no search context — invalid in the real application. The canonical search target is **`/#flight-search`** (decision 3).
- Header "Flights" points at `/flights/results` directly, bypassing search. Retarget to `/#flight-search`.
- The lookup quick actions (Change Flight, Add Baggage, Check Flight Status, Request Support) have no supported destinations. Flight Status in particular is removed from the target navigation until a real authoritative feature exists (decision 9), and no misleading capability label may point at `/faq`.
- No shell page performs navigation between booking steps.
- Currency switching is decorative.

---

## 9. Existing JetPakistan logic that remains authoritative

The shell contributes nothing to any of the following. All of it must survive the rebuild untouched.

| Area | Authority |
|---|---|
| Session, CSRF, authentication | Laravel `web` guard, `users.account_type`; `GET /api/public/auth/session`; XSRF token via `GET /api/public/content/csrf-token`; `credentials: "include"` |
| Portal access control | `requireCustomerPortalAccess()` and `requireAgentPortalAccess()` server guards |
| RBAC | `App\Support\Agents\AgentPermission`, `App\Support\Staff\StaffPermission`, agent `capabilities` payload, `App\Policies\*` |
| Tenant scope | `agency.context` middleware |
| Feature gating | `platform.module:*` middleware |
| Supplier search, fares, revalidation | `FlightController` endpoints; unchanged by this rebuild |
| Booking, PNR, ticketing | Laravel booking controllers; unchanged |
| Payment and gateway returns | AbhiPay start/callback, `/payment/success\|cancel\|decline`; unchanged |
| Wallet, deposits, ledger, refunds | Laravel agent finance; unchanged |
| CMS content and SEO | `PublicContentApiPresenter`, `HomepagePublicContentPresenter`, `cms_pages`, `client_pages`, `client_page_settings` |
| XML sitemap | Laravel `GET /sitemap.xml` → `PublicSitemapController` |
| Emails and queued jobs | `app/Mail/*`, renderers, payload factories |
| API transport | `/laravel/:path*` rewrite in `frontend/next.config.ts` |
| OTP demo behavior | Must not be removed |

---

## 10. Adaptation record template

Architecture §11 requires that each adapted file be recorded. No file has been adapted in Phase A. Phase C onward must append rows here in this shape:

| Source (shell) | Target (repository) | Visual purpose | Retained logic | Required adaptation | Unsupported content removed | Tests affected |
|---|---|---|---|---|---|---|
| `components/ThemeProvider.js` | `features/public-theme-v2/components/PublicThemeV2Root.tsx` | Local light/dark theme on `.jp-theme-v2` | `data-jp-theme` attribute strategy | Scoped CSS tokens; separate from document `data-theme` | `jp-mock-theme` storage key; no global theme mutation | `jp-public-next-theme-02.spec.ts` |
| `components/ThemeToggle.js` | `PublicHeaderPrototype` + `PublicIconButton` | Header theme control | Toggle placement in header actions | Wired to V2 context only | — | `jp-public-next-theme-02.spec.ts` |
| `components/SiteHeader.js` | `PublicHeaderPrototype.tsx` | Sticky header, brand, nav, actions | Layout, sticky behavior, brand placement | TypeScript + scoped CSS; real nav hrefs | `href="#"` Groups link; inert PKR button | visual lab screenshots |
| `components/SiteFooter.js` | `PublicFooterPrototype.tsx` | Green gradient footer, column grid | Column structure, gradient treatment | Token-based footer colors; no newsletter | Newsletter block; `href="#"` legal links | visual lab screenshots |
| `components/FormControls.js` | `PublicTextField`, `PublicSelect`, `PublicCheckbox`, `PublicRadio` | Label/control pairing | Field anatomy | Added hint, error, aria-describedby | No validation wiring (lab only) | `jp-public-next-theme-02.spec.ts` |
| `app/globals.css` button/field rules | `features/public-theme-v2/styles/theme.css` | Button and field visual language | Primary gradient, radii, control heights | Mapped to `--jp-v2-*` tokens under `.jp-theme-v2` | Wholesale `globals.css` import | visual lab screenshots |
| `components/BookingProgress.js` | `PublicStepper.tsx` | Numbered booking step strip | Step-strip visual language | `includeSeats` prop defaults false | Fixed 8-step fixture array including Seats | `jp-public-next-theme-02.spec.ts` |
| `components/OrderSummary.js` | `PublicBookingSummary.tsx` | Sticky summary sidebar layout | Line-item rhythm, total row | Props-only API; no baked-in route/prices | LHE→JED, PKR totals, inert CTA | visual lab screenshots |
| `components/PageHero.js` | `cms-theme-v2/components/CmsHero.tsx` | Hero anatomy | Eyebrow, title, body, actions | CMS block with URL validation | Hardcoded copy | `jp-public-next-theme-02.spec.ts` |
| `app/preview/page.js` | `app/dev/jetpk-theme-lab/page.tsx` | Development component catalog | Section organization idea | Fresh implementation; production gate; noindex | Mockup PNG list; fixture PNRs/prices | `jp-public-next-theme-02.visual.spec.ts` |

---

## 11. Integration rules

1. The Mock Shell is a visual and component scaffold only. **No shell file is copied wholesale.** Reimplement in TypeScript against Tailwind and repository tokens.
2. Never import `app/globals.css`. Map tokens deliberately.
3. Never port `lib/mock-data.js` or any inline fixture constant.
4. Never create `/booking/seats` while no authoritative seat map exists. Seats are capability-gated; the fixture seat map and seat 3C are never ported.
5. Never ship an inert control. Every button, link and field must reach a real Laravel endpoint or be removed.
6. Never let a mockup override an authoritative Laravel contract or a security constraint. Where they disagree, Laravel wins and the mockup is refined.
7. Any adapted booking progress component renders the Seats step **conditionally**, driven by an authoritative capability that is currently false.
8. Never implement card-data collection. Next.js never handles a card number, CVV or expiry.
9. The Fare Selection mockup belongs to `/flights/fare-selection` and must not be mapped onto `/flights/return-options`.
10. Treat `C:\Users\khadi\JetPakistan-NextJS-Mock-Shell` as strictly read-only.
11. Mockup-backed pages require manual visual approval before merge.
12. The 53 uncovered routes are designed from the design system, not invented per page.
13. Registering a `destination`, `offer` or `article` template must not create unsupported routes or fixture content (decision 11).
