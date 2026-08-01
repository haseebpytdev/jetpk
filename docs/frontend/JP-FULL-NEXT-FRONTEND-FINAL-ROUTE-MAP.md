# JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP

Phase: **JP-FULL-NEXT-FRONTEND-01C**
Visual status: **MANUALLY ACCEPTED WITH DEFERRED VISUAL POLISH**
Branch: `phase/jetpk-full-next-frontend-ui-integration`
Machine-readable: [JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json](./JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json)

## Why count = 67

| Classification | Count | Notes |
|---|---:|---|
| **Total `app/**/page.tsx` files** | **67** | Authoritative filesystem inventory |
| Production browser routes | 66 | Excludes `/dev/jetpk-theme-lab` |
| Dev-only (excluded from deploy) | 1 | `/dev/jetpk-theme-lab` |
| Redirect-only | 3 | `/agent`, `/customer`, `/booking/payment` |
| Dynamic routes | 13 | `[slug]`, `[token]`, `[packageId]`, `[bookingRef]`, `[reference]` |
| Metadata routes (not `page.tsx`) | 2 | `robots.ts`, `sitemap.ts` (+ `sitemap.xml` handler) |
| Forbidden / absent | 2 | `/preview`, `/booking/seats` → 404 |
| API/action routes in App Router | 0 | Laravel remains authoritative |

**Reporting convention:** “67 routes” = full Next.js App Router page inventory on disk (includes dev lab). **66** = deployable production browser pages.

## Verified exclusions

| Path | Status |
|---|---|
| `/preview` | **404** — retired |
| `/booking/seats` | **404** — `seat_map_available=false` |
| `/dev/jetpk-theme-lab` | Dev-gated; excluded from production count |
| `/agent` | **307 → `/agent/dashboard`** |
| `/customer` | **307 → `/customer/dashboard`** |
| `/flights/search` | Compat redirect to `/#flight-search` (not a `page.tsx`) |

## Added in integration

- `/flights/fare-selection` — branded fare revalidation before passengers
- `/verify-email` — notice/result; `noindex,nofollow`; reserved from CMS catch-all

## Composition coverage

See [JP-FULL-NEXT-FRONTEND-PAGE-COMPOSITION-COVERAGE.md](./JP-FULL-NEXT-FRONTEND-PAGE-COMPOSITION-COVERAGE.md):

- **Fully adapted:** 4 routes (6% of production)
- **Shared-theme-only:** 59 routes (tokens + family shells)
- **Redirect:** 3 routes
- **Deferred:** 1 dev lab route

## Progress stepper (standard booking)

`Search → Results → Fare Selection → Travelers → Review → Payment → Success`
Seats omitted when Laravel marks `seat_extras` skipped.
