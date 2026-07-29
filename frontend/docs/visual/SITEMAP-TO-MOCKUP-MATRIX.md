# Sitemap-to-Mockup Matrix

Phase: **JP-UI-01**  
Match scale: **0** absent · **1** unrelated · **2** partial · **3** broadly similar · **4** close gaps · **5** parity (evidence required)

## Public routes

| Route | Page name | Canonical mockup | Mockup file | Desktop mockup | Mobile mockup | Next.js page | Blade fallback | Operational | Visual match | Responsive match | Theme | Hardcoding risk | Asset readiness | Animation readiness | Phase |
|-------|-----------|------------------|-------------|----------------|---------------|--------------|----------------|-------------|--------------|-------------------|-------|-----------------|-----------------|---------------------|-------|
| `/` | Homepage | Homepage | `(1).png` | Yes | No | Yes | Yes (`/`) | Live | **2** | **2** | Light only | High (fixtures) | Partial SVGs | Partial | JP-UI-03 |
| `/about-us` | About | About | `(2).png` | Yes | No | Yes | Yes | Live | **3** | **3** | Light only | Medium | Partial | Partial | JP-UI-03 |
| `/support` | Support | Support | `(3).png` | Yes | No | Yes | Yes | Live | **3** | **3** | Light only | Low-Med | Partial | Partial | JP-UI-03 |
| `/contact` | Contact | Support (partial) | `(3).png` | Partial | No | Yes | Yes | Live | **3** | **3** | Light only | Low | N/A | Low | JP-UI-03 |
| `/faq` | FAQ | Support (partial) | `(3).png` | Partial | No | Yes | Yes | Live | **3** | **3** | Light only | CMS | N/A | Low | JP-UI-03 |
| `/terms`, `/privacy` | Legal | — | — | No | No | Yes | Yes | Live | **2** | **3** | Light only | CMS | N/A | None | JP-UI-03 |
| `/lookup-booking` | Manage booking | Manage booking | `678318b0…png` | Yes | No | Yes | Yes | Live | **3** | **3** | Light only | Low | Hero slot | Low | JP-UI-05 |
| `/login` | Login | Login | `542ee36d…png` | Yes | No | Yes | Yes | Live | **3** | **3** | Light only | Low | Illustration slot | Low | JP-UI-05 |
| `/register` | Sign up | Sign up | `0896e3e1…png` | Yes | No | Yes | Yes | Live | **3** | **3** | Light only | Low | Illustration slot | Low | JP-UI-05 |
| `/forgot-password` | Password recovery | — | — | No | No | Yes | Yes | Live | **2** | **3** | Light only | Low | N/A | None | JP-UI-05 |
| `/groups/search` | Group Ticketing | — | — | No | No | Yes | Yes | Live | **2** | **2** | Light only | Med | Partial | Low | JP-UI-04 |
| `/sitemap` | HTML sitemap | — | — | No | No | Yes | Yes | Live | **2** | **3** | Light only | Nav-driven | N/A | None | JP-UI-03 |

## Flight booking routes

| Route | Page name | Canonical mockup | Next.js | Blade | Operational | Visual | Responsive | Phase |
|-------|-----------|------------------|---------|-------|-------------|--------|------------|-------|
| `/flights/results` | Search results | `520bfb29…png` | Yes | Yes | Live | **2** | **2** | JP-UI-04 |
| `/flights/return-options` | Return pair view | Results (partial) | Yes | Yes | Live | **2** | **2** | JP-UI-04 |
| Inline fare families / details drawer | Fare selection | `6ea78679…png` | Partial (on results) | Yes | Live | **2** | **2** | JP-UI-04 |
| `/booking/passengers` | Passengers | `(4).png` | Yes | Yes | Live | **3** | **3** | JP-UI-04 |
| *(no route)* | Seat selection | `45f39a0b…png` | **No** | No | **Unsupported** | **0** | **0** | JP-OPS + JP-UI-04 |
| `/booking/review` | Review | `64460b63…png` | Yes | Yes | Live | **3** | **3** | JP-UI-04 |
| `/booking/payment/manual` | Payment | `ab903350…png` | Yes | Yes | Live | **3** | **3** | JP-UI-04 |
| `/booking/payment/card` | Card payment | Payment (partial) | Yes | Yes | Live | **3** | **3** | JP-UI-04 |
| `/booking/confirmation` | Success | `(5).png` | Yes | Yes | Live | **3** | **3** | JP-UI-04 |
| `/booking/invoice` | Invoice | — | Yes | Yes | Live | **2** | **3** | JP-UI-04 |
| `/booking/payment/status` | Payment status | — | Yes | Yes | Live | **2** | **3** | JP-UI-04 |

## Private portals (inventory only)

| Prefix | Auth | Mockup | Visual match | Phase |
|--------|------|--------|--------------|-------|
| `/customer/*` | Customer | None (dashboard family) | **1–2** | JP-UI-05 |
| `/agent/*` | Agent / Agent Staff | None | **1–2** | JP-UI-05 |
| Admin / Staff (`routes/admin.php`, `staff.php`) | Blade-primary | None | Not audited in JP-UI-01 | JP-OPS |

## Route naming notes

- Repository contract uses `/about-us`, `/lookup-booking`, `/register` — not mockup URL text.
- Mockup header shows Hotels/Offers/Travel Services — **not** current Next.js nav (`lib/navigation.ts`).
- Payment hub `/booking/payment` redirects to `/booking/payment/manual`.
- Seat step appears in progress fixtures as `seat_extras` but remains **skipped/upcoming** when `seat_map_available: false`.

## Indexability (public SEO contract)

Indexable: `/`, `/about-us`, `/support`, `/contact`, `/faq`, `/terms`, `/privacy`, `/sitemap`, CMS slugs.  
Noindex typical: auth, booking checkout, payment, confirmation, lookup form post-results, dashboards.

## Average match ratings (canonical mapped pages)

| Area | Desktop visual | Mobile responsive |
|------|----------------|-------------------|
| Homepage | 2 | 2 |
| Public CMS (About/Support) | 3 | 3 |
| Auth | 3 | 3 |
| Results + fare | 2 | 2 |
| Checkout family | 3 | 3 |
| Manage booking | 3 | 3 |
| Seat selection | 0 (no route) | 0 |

**No page rated 5** — screenshot evidence for parity not claimed in JP-UI-01.
