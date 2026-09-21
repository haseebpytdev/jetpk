# Short URL architecture (WP7)

**Status:** Design + SEO alias slice  
**Date:** 2026-09-21  
**Constraint:** Do not break soft-nav / Traveler / Return Pair / Segmented. Prefer additive short refs; keep legacy long URLs working (§35).

## Three classes

| Class | Indexable | Shape | Authority |
|---|---|---|---|
| A — Public SEO | yes | Human short paths | CMS + managed catalog; sitemap only these |
| B — Transactional | no | Opaque short refs | Server-side `public_short_refs` (planned) |
| C — Share | no | Opaque signed/expiring | Same store, purpose=`share`; no open redirects |

## Class A — SEO (this slice)

| Canonical today | Short alias | Policy |
|---|---|---|
| `/about-us` | `/about` | 308 → `/about-us` (canonical stays `/about-us` until nav/CMS migrate) |
| `/support` | — | already short |
| `/faq` | — | already short |
| `/groups` | — | already short |
| `/terms`, `/privacy` | — | already short |

`/contact` remains **non-indexable** 308 → `/about-us` (KEEP from SEO audit).

Future: migrate catalog canonical from `/about-us` → `/about`, then reverse redirect. Not in this slice.

## Class B — Transactional (implemented for flight search; soft-nav default deferred)

| Purpose | Target path | Resolves to |
|---|---|---|
| Flight search session | `/flights/s/{ref}` | Same results shell; `search_id` is internal prop/state only |
| Booking flow | `/b/{ref}` | Draft/session — only if entropy already insufficient |
| Group booking | `/g/{ref}` | Group booking session |
| Voucher / guest | `/v/{token}` | Prefer existing guest access token if already strong |

### Search short-ref requirements (§33)

- Refresh / back-forward / Return Pair / Segmented / Traveler handoff must keep working
- TTL on ref (aligned with `FlightSearchResultStore::SESSION_TTL_SECONDS`); expired → in-place “Search expired” UI (no silent re-search, **no** `search_id` in URL, **no** redirect to `/flights/results?search_id=`)
- Mint-once on search init (`PublicShortRefService::mintFlightSearch` + reverse cache map); return `short_ref` + `short_url` in init JSON; resolve server-side; **do not** Base64 criteria into the URL
- Browser URL on short route stays `/flights/s/{ref}` (filter/sort query allowed without `search_id`)
- Legacy `/flights/results?…&search_id=` remains valid forever for bookmarks/emails
- Cache: `CACHE_STORE=file` on a single host is acceptable when the FS is shared across workers; multi-host needs a shared cache store

### Implementation order (do not invert)

1. Reserve first segments: `b`, `g`, `v`, `l` (+ document `flights/s`)
2. Persist short refs (cache-backed Class B for search TTL; DB store optional later)
3. Mint short code when search_id created; return `short_ref` / `short_url` in init JSON
4. Next route `/flights/s/[ref]` resolves SSR → same `FlightResultsPage` with `initialSearchId` + `shortRef` (**no redirect**)
5. Soft-nav + same-SHA perf recert **required** before making short URL the default browser URL from search submit
6. Booking `/b/{ref}` only after audit proves current URLs leak sensitive params

## Class C — Share

`/l/{code}` → typed internal destination only (booking confirmation, public package). Never arbitrary URL. Reference: unmerged `PublicShareLink` comment in `AiShoppingTools`.

## Security (§31)

- Cryptographically secure random codes (not sequential, not reversible DB id hash alone)
- No PII in URL
- Rate-limit resolve endpoints
- Robots: all Class B/C `noindex`

## Reserved prefixes

`about` already reserved. Add: `b`, `g`, `v`, `l` to Laravel + Next reserved lists before any CMS slug can claim them.

## Soft-nav / perf gate

Any default browser URL change for `/flights/results` requires host soft-nav N≥10 and same-SHA Traveler/Return/Pair recert on the deploy SHA.
