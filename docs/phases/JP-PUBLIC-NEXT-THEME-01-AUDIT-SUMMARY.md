# JP-PUBLIC-NEXT-THEME-01 — AUDIT SUMMARY

## Phase name

**JP-PUBLIC-NEXT-THEME-01 — Public Route, Sitemap, CMS Template and Mock-Shell Integration Audit**

Architecture Phase A (inventory only) plus the "Cursor audit prompt — run first" section of
[docs/architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md](../architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md).

Closed by **JP-PUBLIC-NEXT-THEME-01A — Audit Decision Closure and Document Correction**.
The approved decisions are recorded in architecture §16 and summarised below.

## Branch name

`phase/jetpk-public-next-theme-rebuild`

## Objective

Inventory every public, deeper, portal and compatibility route before any theme implementation begins; define the CMS page-template and default-styling contract; and map the read-only JetPakistan Next.js Mock Shell against the real application so that visual work in Phases B–H proceeds from evidence rather than assumption.

---

## Preconditions confirmed

| Check | Result |
|---|---|
| Current branch | `phase/jetpk-public-next-theme-rebuild` |
| Current HEAD | `111b2925f12369dbcbef139c9b251726a5a785fd` |
| `jetpk/main` HEAD | `111b2925f12369dbcbef139c9b251726a5a785fd` — identical, baseline matches |
| Working-tree state at start | Clean except untracked `docs/architecture/` |
| Architecture file | Exists, read in full before any other action |
| Mock Shell reference folder | `C:\Users\khadi\JetPakistan-NextJS-Mock-Shell` exists; **not modified** |
| Local PHP 8.3 executable | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`, located from `docs/phases/JP-UI-05B-...-SUMMARY.md` |
| Backup Safe | Not accessed, not modified |

---

## Included scope

- Every Next.js route under `frontend/app`, plus layouts, route groups, dynamic segments, middleware and redirects
- Relevant Laravel web routes and named routes
- Public API and presenter endpoints consumed by frontend pages
- Header and footer navigation configuration
- CMS and page-setting keys, models and presenters
- Sitemap, robots, canonical and noindex behavior
- Authentication, password, OTP and verification routes
- Customer routes
- Agent and Agent Staff routes
- Group-ticketing routes
- Search, fare, booking, payment and lookup compatibility routes
- Routes linked by email, booking, payment and support workflows
- Blade, Master OTA and Parwaaz fallback and leak risks
- Every page represented in the standalone Mock Shell
- Every real route missing from the Mock Shell

## Excluded scope

- All runtime implementation
- Admin, staff and Dev CP surfaces beyond confirming route ownership
- The dashboard application
- Any CSS, JS, TS, TSX, PHP or Blade change
- Laravel behavior, supplier, booking, payment, PNR, ticketing, refund and wallet logic
- Copying or modifying any Mock Shell file
- Laravel tests and any broad test suite
- Phase B visual lab and the Homepage
- Deployment and merge

Phase 01A additionally excluded any runtime change: the closure was documentation
correction and phase closure only. A commit and a feature-branch push were
performed in 01A; no merge and no deployment occurred.

---

## Returned counts

All figures are the **measured Phase A baseline** at
`111b2925f12369dbcbef139c9b251726a5a785fd`. The approved decisions do not alter
them; planned future figures are recorded separately under "Route-count effect of
the decisions" above.

### Route counts

| Metric | Value |
|---|---|
| **Exact total route count (Next.js page routes, measured)** | **64** |
| **Public route count** | **38** |
| **Authenticated Customer route count** | **11** |
| **Agent / Agent Staff route count** | **15** |
| **Compatibility / redirect count** | **14** Laravel redirect-controller routes |
| **CMS-capable page count** | **13** fixed page keys (`home`, `about`, `support`, `group-search`, `login`, `register`, `footer`, `global`, `terms`, `privacy`, `faq`, `booking-lookup`, `agent-registration`) plus unbounded `custom:{slug}` and `cms_pages` slugs, surfaced through **8** Next routes |
| Laravel route entries | **584** |
| Laravel unique URIs | **519** |
| Laravel `api/public/*` endpoints | **13** |
| **Mock Shell page coverage** | **11 / 64 = 17.2%** of real routes; **11 / 38 = 28.9%** of public routes |

Public route breakdown (38): 2 root/utility, 7 auth, 11 public content and CMS, 10 booking and payment, 6 groups, 2 flight search.

Next.js structure: 6 layouts, 1 `error.tsx`, 1 `not-found.tsx`, **no** `middleware.ts`, **no** `route.ts`, **no** `loading.tsx` or `template.tsx`, **no** catch-all segments.

### Missing deeper-page list

1. Email verification notice — `/verify-email` — **approved to be built as a Next page** with `noindex,nofollow` (decision 2)
2. Email verification landing — `/verify-email/{id}/{hash}` — **stays Laravel-authoritative**; verifies and redirects into a Next result or login state
3. Password confirm and forced password change
4. Social login callback and error states
5. Guest booking detail after lookup (documents, cancellations, promos)
6. Flight detail — `flights/details/{id}`
7. Multi-city inquiry
8. Customer saved travelers
9. Customer payment-proof upload
10. Customer promo apply and remove
11. Customer cancellation request
12. Agent agency management
13. Agent staff management
14. Agent reports
15. Agent finance
16. Agent travelers
17. Seat selection — **capability-gated**, not permanently removed; absent while `seat_map_available=false` (decision 5)
18. `/flights/fare-selection` — **approved future route** (decision 4)

### Unsupported and dead-link list

| Link | Location | Problem found | Approved disposition |
|---|---|---|---|
| `/flights/search` | Customer and Agent overview CTAs | No Next page; Laravel redirects to `/` | Retarget CTAs to `/#flight-search`; keep as a compatibility redirect (D3) |
| `/verify-email` | Auth allowlist and registration fallback | No Next page; renders Blade | Build as a Next notice/result page, `noindex,nofollow` (D2) |
| Header "Flight Status" | `frontend/lib/navigation.ts` | Points at `/faq` | **Remove** from target navigation (D9) |
| Footer "Baggage" | `frontend/lib/navigation.ts` | Points at `/faq` | **Remove**, or relabel as FAQs only where the visible label is FAQs (D9) |
| `/contact` | Header and footer | Indexed Next page contradicted by a Laravel permanent redirect | `/contact` is canonical and indexable; the legacy redirect is removed or replaced (D1) |
| `/booking/payment/manual`, `/card`, `/return`, `/booking/status` | Next-only URLs | No Laravel GET route; direct entry falls into the CMS catch-all | Laravel reserved-path protection plus the final proxy boundary (D6) |
| Contact-form fallback `/laravel/support` | Contact page | Deliberately renders Blade | Replaced by the authoritative `POST /support` path before Phase H (D7) |
| Mock Shell quick actions (Change Flight, Add Baggage, Check Flight Status, Request Support) | `manage-booking` mockup | No supported destinations | Map to supported destinations or remove; no misleading capability label points at `/faq` (D9) |
| Portal "Sign out" `/laravel/logout` | Portal shell | Laravel-rendered | Acceptable — Laravel owns operational actions (D7) |

### Page-family implementation sequence

| Order | Phase | Families |
|---|---|---|
| 1 | **B** — Design system and visual lab | Cross-cutting tokens, components and states; must be approved before anything else |
| 2 | **C** — Shared shell and CMS renderer | CMS/content (2); shell consumed by all families |
| 3 | **D** — Homepage | Home (1) |
| 4 | **E** — Public content pages | CMS/content (2), Legal (3), Support/FAQ/Contact (4) |
| 5 | **F** — Search and booking | Flight search (5), Return options (6a), **new Fare selection route (6b)**, Booking checkout (7), Payment (8), Confirmation/Invoice (9), Manage booking (10), Groups (11) |
| 6 | **G** — Authentication and portals | Auth (12) including the new `/verify-email` page, Customer portal (13), Agent portal (14) |
| 7 | **H** — No-fallback and final regression | All fifteen families, including Utility (15); gate or retire public Blade routes, align or retire Laravel `robots.txt`, remove the legacy `/contact` redirect, close CMS catch-all absorption |

---

## Investigation findings

### Route ownership is currently dual-stack

Laravel still registers the complete public Blade map, including `GET /` and the catch-all `GET /{slug}`, while Next.js defines parallel pages for the same URLs. The intended production topology is Next on the public host with `/laravel/*` proxying to Laravel, but no nginx configuration is committed to the repository and `docs/staging-deployment.md` still shows a classic Laravel-only `try_files` arrangement. Any request that reaches Laravel directly renders Blade.

### The frontend has no edge middleware

There is no `middleware.ts`. Access control is entirely server-side per page through `requireCustomerPortalAccess()` and `requireAgentPortalAccess()`, backed by the Laravel session via `GET /api/public/auth/session`. This is sound, but it means route protection is a per-page obligation that must be re-applied to every new portal page.

### Indexing policy has two conflicting sources

`public/robots.txt` and `frontend/app/robots.ts` disagree: the Next list additionally disallows `/flights/results`, `/flights/return-options`, `/lookup-booking` and `/access-denied`. Only four surfaces carry a genuine page-level noindex, and the `noIndexMetadata` helper that exists for this purpose is used by no page.

### CMS has no template concept in the database

Neither `cms_pages`, `client_pages` nor `client_page_settings` has a `template` column. Presentation is currently implied by page key, Blade view name or React route. A registry is therefore only implementable frontend-side without a schema change.

### Branding leaks are internal only

Occurrences of Parwaaz, YoursDomain, YD Travel, haseeb-master and Master OTA appear in audits, tests, config blocklists, dashboard Blade comments and defensive CSS in `public/themes/frontend/jetpakistan/css/booking.css`. No live email template or public UI copy was found to contain them. No user-facing branding leak was identified by this audit.

### The Mock Shell contributes visual structure, not application coverage

It covers 17.2% of real routes and provides no portal design whatsoever. Twenty-six of the fifty-three uncovered routes are Customer and Agent portal pages, which is the single largest design gap in the rebuild.

---

## Root causes

1. **Incremental URL divergence.** Next pages were added for payment, status and invoice screens without matching Laravel GET routes, so those URLs exist in only one of the two stacks.
2. **No enforced ownership boundary.** Because no proxy configuration is committed, Laravel's Blade routes were never retired or gated, leaving both stacks live for the same URLs.
3. **SEO configuration split across stacks.** Robots and sitemap logic was implemented independently on each side rather than in one authoritative place.
4. **CMS presentation implied rather than declared.** Without a template field, presentation was encoded in code paths, which is why unknown or new pages have no defined rendering contract.
5. **Navigation authored ahead of destinations.** "Flight Status", "Baggage" and `/flights/search` were linked before the corresponding pages existed.

---

## Exact files changed

Six Markdown files, all documentation. No file deleted.

| File | Phase A | Phase 01A |
|---|---|---|
| `docs/architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md` | Read only | **Updated** — §7 corrected for the payment model, conditional seats and the two fare routes; §16 "Approved decisions" added |
| `docs/frontend/JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md` | Created | **Updated** — decisions 1–11 applied to route records, redirects, robots, navigation and the decision roll-up; §1.1 planned target figures added |
| `docs/frontend/JP-PUBLIC-PAGE-FAMILY-MATRIX.md` | Created | **Updated** — family index corrected and split into measured versus approved future; families 4, 6a/6b, 7, 8, 12, 13, 14 revised; blockers replaced by scheduled obligations |
| `docs/frontend/JP-MOCK-SHELL-INTEGRATION-MAP.md` | Created | **Updated** — fare-selection reclassified as an approved future route owning its mockup; seats made conditional; card-data collection prohibited; integration rules extended |
| `docs/frontend/JP-CMS-PAGE-TEMPLATE-AND-DEFAULT-STYLING-CONTRACT.md` | Created | **Updated** — frontend resolution approved, backend column deferred, §3.2.1 URL and host validation added, registration-is-not-publication rule added |
| `docs/phases/JP-PUBLIC-NEXT-THEME-01-AUDIT-SUMMARY.md` | Created | **Updated** — "Approved architecture decisions" section replaces the open findings |

## Routes changed

**None.** No route file was touched in either stack.

## Database changes

**None.** No migration was created, modified or run.

## Backend changes

**None.** No PHP file was modified. The only backend interaction was one read-only `artisan route:list` invocation.

## Frontend changes

**None.** No CSS, JS, TS, TSX or Blade file was modified. No Mock Shell file was copied or modified.

---

## Tests executed

**None**, by instruction. The phase prohibited Laravel tests and any broad test suite. No Playwright, PHPUnit, build or lint run was performed.

## Assertion counts

Not applicable — no tests were executed. Counts reported in this summary are measured inventory figures, not test assertions.

## Screenshots

None captured. Phase A is documentation-only; no rendering work was performed. The thirteen reference mockups in `C:\Users\khadi\JetPakistan-NextJS-Mock-Shell\public\mockups\` were catalogued by path only and were neither copied nor modified.

## Responsive verification

Not performed. Responsive requirements are specified in the CMS contract §8 and in the page-family matrix, and are verified in Phases B–H.

## Accessibility verification

Not performed. Accessibility requirements are specified in the CMS contract §9 and are verified in Phases B–H.

---

## Exact commands run

```powershell
git rev-parse --abbrev-ref HEAD
git rev-parse HEAD
git status --porcelain=v1 -b
git ls-remote jetpk main
git remote -v
Test-Path "C:\Users\khadi\JetPakistan-NextJS-Mock-Shell"
Test-Path "docs\architecture\JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md"

C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan route:list
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan route:list --json

git status --short
Get-Item <the five deliverables> | Select-Object FullName, Length, LastWriteTime
```

Route-list output was written to the user temp directory and analysed with read-only PowerShell grouping. No repository file was produced by those commands.

---

## Approved architecture decisions

All ten Phase A findings are **closed**. The decisions below were approved in
JP-PUBLIC-NEXT-THEME-01A, are recorded in architecture §16, and are binding on
Phases B–H.

### Decision 1 — `/contact` (closes F1)

`/contact` remains the **canonical dedicated Contact page**. It uses the `contact`
template and remains **indexable**. The Laravel `permanentRedirect('/contact' →
'/about-us')` is legacy and must be removed or replaced during route-ownership
closure. Header and footer links to `/contact` remain valid.

### Decision 2 — Email verification (closes F2)

`/verify-email` becomes a **Next.js notice/result page** with `noindex,nofollow`.
`/verify-email/{id}/{hash}` remains a **Laravel-authoritative signed action**:
Laravel verifies the signature and account, then redirects to a Next result or
login state. In the final architecture the signed action must never render the
legacy Blade theme. **Signature verification must not move into Next.js.**

### Decision 3 — Search CTA (closes F3)

The canonical search target is **`/#flight-search`**. Customer and Agent CTAs must
eventually target `/#flight-search`. `/flights/search` remains **only** as a
compatibility redirect to `/#flight-search`.

### Decision 4 — Fare-selection routes (closes F4)

`/flights/return-options` and `/flights/fare-selection` are **separate routes with
separate workflows**.

`/flights/return-options` presents supplier-returned or supplier-validated return
combinations. Outbound and return offers must **never** be stitched manually.

`/flights/fare-selection` is a **new dedicated fare-family selection route**. It
applies to a selected one-way offer or a validated return pair, performs
authoritative offer retrieval and revalidation, and sits **before**
`/booking/passengers`. It **owns the approved Fare Selection mockup**, which must
not be mapped onto `/flights/return-options`.

### Decision 5 — Seat capability (closes F5)

Never create or port `/booking/seats` while no authoritative seat map exists. The
current capability is **`seat_map_available=false`**, so the current journey omits
the seat page and the Seats step. The design system may support a **conditional**
Seats step, enabled only when an authoritative future capability becomes true.
Seats are **capability-gated, not permanently removed**. The fixture seat map and
seat 3C must never be ported.

### Decision 6 — Payment (closes F6)

Final route model: `/booking/payment` is the canonical payment-method selector;
`/booking/payment/manual` is the Manual Payment workflow; `/booking/payment/card`
is an **AbhiPay secure handoff only**; `/booking/payment/status` is the
payment-status state; `/booking/payment/return` is the gateway-return state.

All references to a direct card-details form are removed. **Next.js must never
collect a card number, CVV or expiry.** `/manual` and `/card` are preserved as
compatibility routes. Laravel reserved-path protection and the final proxy
boundary must prevent these routes from entering the CMS catch-all.

### Decision 7 — Route ownership (closes F7)

**Next.js owns** normal public browser pages, CMS pages, the Customer portal, the
Agent and Agent Staff portal, and themed error and not-found states.

**Laravel owns** `/laravel/*` actions and APIs, authentication/session/CSRF
authority, signed verification actions, payment callbacks, supplier, search,
booking, PNR and ticketing, and downloads and operational actions.

Blade remains **temporary** until parity is proven. Public Blade routes are gated
or retired in **Phase H**, not during the theme build.

### Decision 8 — Robots (closes F8)

`frontend/app/robots.ts` is the **final public robots authority**. Laravel
`public/robots.txt` is aligned or retired at cutover. Transactional, tokenized,
private and portal pages require page-level `noindex,nofollow` metadata. The
applicable families are Booking checkout, Payment, Confirmation/Invoice, Flight
search, Fare selection, Manage booking, authenticated Groups steps, tokenized and
transient Auth pages, the Customer portal, the Agent portal, Utility
(`/access-denied`) and missing CMS legal pages. The full route list is in
[JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md](../frontend/JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md) §13.

### Decision 9 — Navigation (closes F9)

**Remove Flight Status** from the target navigation until a real authoritative
feature exists. **Remove Baggage**, or relabel it as FAQs only where its visible
label is FAQs. Misleading capability labels must not point at `/faq`.

### Decision 10 — CMS template field (closes F10)

**Frontend template resolution is approved** for this rebuild. **No database
template column is added.** A Dev CP-selectable template field is **deferred to a
separately approved backend phase**.

### Decision 11 — Additional corrections

- Auth **social-login controls are conditional** on authoritative enabled
  providers and functional callbacks; otherwise they are omitted.
- `destination`, `offer` and article templates **may be registered but must not
  create unsupported routes or fixture content**.
- **`CmsAction.href`, card links and image sources require the same allowlisted
  URL and host validation** as sanitized HTML.
- The **Mock Shell remains a visual and component scaffold only**.
- **No shell file is copied wholesale.**

### Route-count effect of the decisions

| Figure | Value |
|---|---|
| **Measured current routes** | **64** (unchanged; measured at the baseline commit) |
| **Approved future planned route** | **`/flights/fare-selection`** |
| **Planned target route count** | **65**, subject to implementation verification |

`/verify-email` is also approved as a Next page, but it replaces a route Laravel
already owns, so its effect on the final count is confirmed at implementation. No
route is approved for retirement. `/booking/seats` is not created.

---

## Known limitations

- Route counts reflect the working tree at `111b2925f12369dbcbef139c9b251726a5a785fd`; any later branch will need a refresh.
- The Laravel figure of 584 counts route entries as reported by `route:list`, which includes multiple method registrations for a single URI; the 519 unique-URI figure is provided alongside it.
- Admin, staff and Dev CP routes were counted but not individually inventoried, as they are outside the public rebuild scope.
- Runtime rendering was not exercised. Fallback risks are derived from route registration and configuration, not from live request tracing.
- The Mock Shell was inspected as source only; it was not built or run.

## Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Blade leaks into a public URL during the rebuild | High | Decision 7 — Next owns pages, Laravel owns actions; public Blade gated or retired in Phase H with a no-fallback check |
| A mockup is implemented against the wrong route | High | Decision 4 — the Fare Selection mockup belongs to `/flights/fare-selection` and must not be mapped onto `/flights/return-options` |
| Fake seat capability is introduced from the shell | High | Decision 5 — `/booking/seats` is not created; the Seats step is capability-gated and the fixture map and seat 3C are never ported |
| Card data is collected by the frontend | High | Decision 6 — Next.js never collects card number, CVV or expiry; `/booking/payment/card` is an AbhiPay handoff only |
| Payment routes absorbed by the CMS catch-all | High | Decision 6 — Laravel reserved-path protection plus the final proxy boundary, closed by Phase H |
| Signed verification renders legacy Blade | Medium | Decision 2 — Laravel verifies and redirects into a Next result state; the signed action must never render the legacy theme |
| Duplicate or contradictory indexing signals reach search engines | Medium | Decision 8 — `robots.ts` is authoritative; Laravel `robots.txt` aligned or retired at cutover; page-level `noindex,nofollow` on all listed families |
| Fixture values from the shell leak into production UI | Medium | Must-not-copy list enforced at review; no shell file is copied wholesale |
| Portal design gap underestimated | Medium | 26 of 53 uncovered routes are portal pages; size Phase G accordingly |
| CMS pages diverge visually without a template contract | Medium | Decision 10 — frontend template resolution implemented in Phase C before any content page |
| Unsupported routes created by registering templates | Low | Decision 11 — registration is not publication; no fixture content |

## Rollback instructions

The closure commit contains only Markdown. To revert it:

```powershell
cd C:\Users\khadi\ota-jetpk
git revert --no-edit <closure-commit-sha>
```

To discard the branch work entirely without touching `main`:

```powershell
git switch main
git branch -D phase/jetpk-public-next-theme-rebuild
git push jetpk --delete phase/jetpk-public-next-theme-rebuild
```

No runtime file, route, migration or configuration was touched in Phase A or
Phase 01A, so no other rollback action exists. `main` and `jetpk/main` are
unaffected, since the branch was never merged.

## Commit SHA

Recorded in the phase closure report for JP-PUBLIC-NEXT-THEME-01A. The commit is
`docs: close JetPakistan public Next theme architecture audit` on
`phase/jetpk-public-next-theme-rebuild`. `main` and `jetpk/main` remain at
`111b2925f12369dbcbef139c9b251726a5a785fd`.

---

## Final status

**Phase A audit complete. Decisions approved and recorded. Documentation closed.**

All ten findings (F1–F10) are closed by the eleven approved decisions in
architecture §16, restated above. No runtime code was written, no test was run,
and no merge was performed. Production, the Mock Shell and Backup Safe were not
touched.

Phase B — the design system and visual lab — may begin on approval. It has not
been started: no visual lab exists, no page has been implemented, and no Mock
Shell file has been copied.

`FINAL_FAIL` is not reported for this phase: Phase A defines no runtime
acceptance criteria, and the acceptance criteria in architecture §13 are
evaluated at Phase H.
