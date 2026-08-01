# JetPakistan Public Next.js Theme Rebuild Architecture

## 1. Decision

Continue with **Next.js for the final public frontend**.

Use the standalone JetPakistan Mock Shell as a **visual and component scaffold**, not as a replacement application and not as a source of business logic.

Laravel remains authoritative for authentication/session/CSRF, RBAC and ownership, supplier search, fare revalidation, booking/PNR/ticketing, payment, wallet/deposits/refunds, CMS/configuration, queues, jobs, emails and webhooks.

The Next.js public frontend remains responsible for public rendering, Customer and Agent-facing presentation, responsive behavior, theme/components, accessibility and consuming Laravel-authoritative APIs.

## 2. Why this is faster

The failed JP-UI-06 work should not be patched further.

Use this sequence:

1. archive the rejected branch state;
2. start a clean Next.js rebuild from authoritative main;
3. use the Mock Shell as the visual starter kit;
4. build one reusable public design system;
5. add a CMS page renderer so new CMS pages inherit the theme;
6. audit every public and deeper route before page work;
7. implement page families against the same shell and tokens;
8. reconnect existing Laravel-backed behavior without changing approved markup.

## 3. Repository and safety

Primary repository:

```text
C:\Users\khadi\ota-jetpk
```

Visual scaffold reference:

```text
C:\Users\khadi\JetPakistan-NextJS-Mock-Shell
```

Protected:

```text
C:\Users\khadi\ota
C:\Users\khadi\Backup Safe
```

Rules:

- Never modify `C:\Users\khadi\ota`.
- Never modify, move, rename or delete files in `C:\Users\khadi\Backup Safe`.
- Do not deploy.
- Do not modify production, DNS or server configuration.
- Do not remove OTP demo behavior.
- Do not change supplier, booking, payment, PNR, ticketing, refund or wallet behavior during this rebuild.
- Do not create a second production frontend.
- Do not copy the Mock Shell wholesale over `frontend/`.

## 4. Archive rejected work and start clean

```powershell
cd C:\Users\khadi\ota-jetpk

git status
git branch --show-current
```

If the rejected work is uncommitted:

```powershell
git switch -c archive/jp-ui-06-rejected-20260801
git add -A
git commit -m "archive: preserve rejected JP-UI-06 visual implementation"
git push -u jetpk archive/jp-ui-06-rejected-20260801
```

Then:

```powershell
git switch main
git pull --ff-only jetpk main
git rev-parse HEAD
git switch -c phase/jetpk-public-next-theme-rebuild
```

Expected main baseline:

```text
111b2925f12369dbcbef139c9b251726a5a785fd
```

Do not merge the archive branch.

## 5. Mandatory public route and sitemap audit

Audit before implementation.

### Next.js sources

- `frontend/app/**/page.tsx`
- `frontend/app/**/layout.tsx`
- route groups and dynamic segments
- redirects and middleware
- metadata, canonical and noindex behavior
- public shell usage
- Customer and Agent portal routes

### Laravel sources

- `routes/web.php`
- other loaded route files
- named routes used by Next.js
- public API/presenter endpoints
- auth/session endpoints
- booking lookup endpoints
- CMS/page configuration endpoints

Run a route list only with the documented local PHP executable:

```powershell
<LOCAL_PHP> artisan route:list
```

### Navigation/CMS sources

Audit:

- `frontend/lib/navigation.ts`
- footer navigation
- CMS page keys
- page settings
- sitemap generator
- robots
- redirects and compatibility routes
- public links from dashboard/Dev CP
- links emitted in emails

## 6. Required route inventory

Create:

```text
docs/frontend/JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md
```

For each route record:

| Field | Meaning |
|---|---|
| Route | Exact URL pattern |
| Route name | Laravel/Next identifier |
| Source | Next page, Laravel fallback, redirect, compatibility route |
| Access | Public, Customer, Agent, Agent Staff |
| Parameters | IDs, slugs, references, tokens |
| Canonical URL | SEO/navigation canonical |
| Indexing | index/noindex |
| Page family | Home, CMS, listing, detail, search, booking, auth, portal |
| Current implementation | Existing page/component |
| Backend authority | API, presenter, session or CMS |
| Visual mockup | Exact mockup or shared design family |
| Decision | Keep, rebuild, redirect, retire |
| Fallback risk | Blade/Master/Parwaaz risk |
| Notes | Missing route or unsupported action |

Distinguish:

- required final public pages;
- deeper flow pages;
- compatibility routes;
- temporary routes;
- private portal pages;
- pages to retire;
- pages that need a CMS template but no unique mockup.

## 7. Known route families to verify

These are expected and must be confirmed, not assumed complete.

### Public shell/content

```text
/
/about-us
/support
```

Also audit legal, privacy, terms, refund/cancellation, FAQ, contact, CMS slug, destination, offer and article pages.

### Search/booking

```text
/flights/results
/flights/return-options
/flights/fare-selection
/booking/passengers
/booking/review
/booking/payment
/booking/confirmation
/lookup-booking
```

`/flights/return-options` and `/flights/fare-selection` are **separate routes and
separate workflows** (see §16 decision 4).

Audit compatibility routes:

```text
/booking/payment/manual
/booking/payment/card
/booking/payment/status
/booking/payment/return
```

`/booking/payment` is the canonical payment-method selector. Next.js must never
collect a card number, CVV or expiry; `/booking/payment/card` is an AbhiPay
secure handoff only (see §16 decision 6).

Do not create `/booking/seats`. Seat capability is conditional on an
authoritative seat map. The current capability is `seat_map_available=false`, so
the current journey omits the seat page and the Seats step (see §16 decision 5).

### Authentication

```text
/login
/register
/verify-email
```

Audit password reset, email verification, OTP, agent registration, approval, logout and callback/error routes.

`/verify-email` becomes a Next.js notice/result page with `noindex,nofollow`.
`/verify-email/{id}/{hash}` remains a Laravel-authoritative signed action.
Signature verification must never move into Next.js (see §16 decision 2).

### Groups

Audit:

```text
/groups/search
```

Also group details, booking handoff, calculators and public/agent distinctions.

### Customer/Agent portals

Inventory all routes under:

```text
/customer/**
/agent/**
```

These are authenticated application pages, not CMS pages.

## 8. Public design system architecture

Recommended structure:

```text
frontend/
  styles/
    tokens.css
    public-theme.css
    cms-content.css
    utilities.css

  components/
    public/
      PublicShell.tsx
      PublicHeader.tsx
      PublicFooter.tsx
      PublicContainer.tsx
      PublicSection.tsx
      PublicSectionHeader.tsx
      PublicButton.tsx
      PublicCard.tsx
      PublicImageSlot.tsx
      PublicBadge.tsx
      PublicCallout.tsx
      PublicFormField.tsx
      PublicTabs.tsx
      PublicStepper.tsx
      PublicSummaryCard.tsx

    cms/
      CmsPageRenderer.tsx
      CmsHero.tsx
      CmsRichText.tsx
      CmsImage.tsx
      CmsCardGrid.tsx
      CmsStats.tsx
      CmsTimeline.tsx
      CmsFaq.tsx
      CmsCallout.tsx
      CmsGallery.tsx
      CmsSection.tsx

  lib/
    cms/
      block-types.ts
      page-template-registry.ts
      normalize-cms-page.ts
      sanitize-cms-html.ts
```

Names may follow existing conventions, but responsibilities must remain separated.

## 9. Default CSS for future CMS pages

Do not allow CMS authors to inject arbitrary Tailwind classes or JSX.

### Preferred: structured blocks

```ts
type CmsBlock =
  | { type: "hero"; /* ... */ }
  | { type: "richText"; /* ... */ }
  | { type: "image"; /* ... */ }
  | { type: "cardGrid"; /* ... */ }
  | { type: "stats"; /* ... */ }
  | { type: "timeline"; /* ... */ }
  | { type: "faq"; /* ... */ }
  | { type: "callout"; /* ... */ }
  | { type: "gallery"; /* ... */ };
```

`CmsPageRenderer` maps blocks to approved themed components.

### Compatibility: sanitized HTML

Existing CMS HTML must render only inside:

```html
<article class="jp-cms-content">...</article>
```

`cms-content.css` styles headings, paragraphs, links, lists, tables, blockquotes, images, figures/captions, rules, code/pre and approved media.

Sanitize HTML before rendering.

Raw CMS HTML must not control:

- shell;
- scripts;
- arbitrary styles;
- event handlers;
- forms;
- unapproved iframes;
- header/footer;
- global layout.

## 10. Page template registry

Recommended templates:

```text
default-content
hero-content
landing
faq
contact
policy
destination
offer
article-index
article-detail
```

Unknown templates must safely fall back to `default-content`, not Blade, Master or Parwaaz.

## 11. Mock Shell integration rules

Use from the standalone shell:

- approximate component structure;
- route/page coverage;
- shared shell ideas;
- card/form boundaries;
- preview/catalog organization;
- useful CSS starting points.

Do not use from it:

- business logic;
- auth;
- API calls;
- booking state;
- supplier values;
- fake routes;
- unsupported navigation;
- fake newsletter behavior;
- fake seat selection;
- hardcoded production content.

Create:

```text
docs/frontend/JP-MOCK-SHELL-INTEGRATION-MAP.md
```

For each adapted file record source, target, visual purpose, retained logic, required adaptation, unsupported content removed and tests affected.

## 12. Implementation sequence

### Phase A — Inventory only

Deliver:

- route/sitemap inventory;
- page-family matrix;
- Mock Shell integration map;
- CMS/template requirements;
- missing deeper pages;
- fallback risks.

No runtime implementation.

### Phase B — Design system and visual lab

Create a noindex development catalog for:

- typography;
- colors;
- buttons;
- fields;
- tabs;
- cards;
- image slots;
- alerts;
- callouts;
- CMS blocks;
- booking stepper;
- summary cards;
- header/footer;
- light/dark;
- responsive states.

Approve this before page implementation.

### Phase C — Shared shell and CMS renderer

Implement the public shell, global tokens, CMS renderer, structured blocks, `.jp-cms-content`, template registry and safe states.

### Phase D — Homepage

Build static visual composition first, then bind homepage content, navigation and search behavior.

### Phase E — Public content pages

Implement About, Support, policies, generic CMS pages and supported destination/offer/article pages.

### Phase F — Search/booking families

Implement Results + Fare Selection; Passengers + Review + Payment + Success; Manage Booking; seat-unavailable state.

### Phase G — Authentication and portals

Implement Login, Register, verification/reset flows, Customer pages and Agent/Agent Staff pages.

### Phase H — No-fallback and final regression

Verify no Master/Parwaaz leakage, no unintended Blade fallback, no dead navigation, no fake controls, CMS inheritance, intentional redirects, noindex, full visual tests, functional tests, accessibility and responsive/dark behavior.

## 13. Acceptance criteria

The rebuild is not complete until:

- every public route is inventoried;
- every navigation link points to a supported route;
- every CMS page uses the public shell;
- new CMS pages inherit the default design automatically;
- unsupported blocks fail safely;
- arbitrary CMS scripts/styles are rejected;
- mockup-backed pages are manually approved;
- private pages are noindex;
- Laravel remains authoritative;
- no production behavior is simulated;
- no accidental Blade/Master/Parwaaz fallback remains;
- builds and targeted tests pass.

## 14. Cursor audit prompt — run first

```text
Phase JP-PUBLIC-NEXT-THEME-01
PUBLIC ROUTE, SITEMAP, CMS TEMPLATE, AND MOCK-SHELL INTEGRATION AUDIT

Repository:
C:\Users\khadi\ota-jetpk

Reference scaffold:
C:\Users\khadi\JetPakistan-NextJS-Mock-Shell

Work only in the JetPakistan repository.
The scaffold and Backup Safe are read-only references.

Do not implement runtime code.
Do not copy Mock Shell files yet.
Do not modify Laravel behavior.
Do not modify dashboard.
Do not deploy.
Do not merge.

Audit:

1. every Next.js route under frontend/app;
2. every relevant Laravel web route and named route;
3. frontend navigation and footer configuration;
4. CMS/page-setting keys and page presenters;
5. sitemap, robots, metadata and canonical behavior;
6. redirects and compatibility routes;
7. Customer and Agent public-facing portal routes;
8. group-ticketing public and authenticated routes;
9. routes linked from email, booking, payment and support flows;
10. current Blade/Master/Parwaaz fallback risks;
11. every page represented in the standalone Mock Shell;
12. every audited real route missing from the Mock Shell.

Create:

docs/frontend/JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md
docs/frontend/JP-PUBLIC-PAGE-FAMILY-MATRIX.md
docs/frontend/JP-MOCK-SHELL-INTEGRATION-MAP.md
docs/frontend/JP-CMS-PAGE-TEMPLATE-AND-DEFAULT-STYLING-CONTRACT.md
docs/phases/JP-PUBLIC-NEXT-THEME-01-AUDIT-SUMMARY.md

For every route record:

- exact URL;
- route/source file;
- access level;
- route parameters;
- canonical URL;
- indexing rule;
- page family;
- current implementation;
- authoritative backend/data contract;
- applicable mockup;
- proposed template;
- keep/rebuild/redirect/retire decision;
- fallback/leak risk;
- missing dependencies.

The CMS contract must define:

- structured block rendering;
- sanitized rich HTML compatibility;
- default CMS typography and spacing;
- page template registry;
- safe unknown-template behavior;
- no arbitrary scripts/styles;
- light/dark and responsive behavior;
- accessibility requirements.

Return:

- exact route count;
- public route count;
- authenticated Customer route count;
- Agent/Agent Staff route count;
- compatibility/redirect count;
- CMS page count;
- missing deeper-page list;
- unsupported/dead-link list;
- Mock Shell coverage percentage;
- files created;
- findings requiring approval.

Stop after documentation.
Do not begin implementation.
```

## 15. Immediate next action

1. Archive rejected JP-UI-06 work.
2. Create `phase/jetpk-public-next-theme-rebuild` from clean main.
3. Run the audit prompt.
4. Review the route inventory before approving implementation.

## 16. Approved decisions (JP-PUBLIC-NEXT-THEME-01A)

These decisions close the ten findings raised by the Phase A audit. They are
binding on Phases B–H and override any earlier statement in this document.

### 1. `/contact`

`/contact` remains the canonical dedicated Contact page. It uses the `contact`
template and remains indexable. The Laravel `permanentRedirect('/contact' →
'/about-us')` is legacy and must be removed or replaced during route-ownership
closure. Header and footer links to `/contact` remain valid.

### 2. Email verification

`/verify-email` becomes a Next.js notice/result page with `noindex,nofollow`.
`/verify-email/{id}/{hash}` remains a Laravel-authoritative signed action:
Laravel verifies the signature and account, then redirects to a Next result or
login state. In the final architecture the signed action must never render the
legacy Blade theme. Signature verification must not move into Next.js.

### 3. Search CTA

The canonical search target is `/#flight-search`. Customer and Agent CTAs must
eventually target `/#flight-search`. `/flights/search` remains only as a
compatibility redirect to `/#flight-search`.

### 4. Fare-selection routes

`/flights/return-options` and `/flights/fare-selection` are separate routes with
separate workflows.

`/flights/return-options` presents supplier-returned or supplier-validated return
combinations. Outbound and return offers must never be stitched manually.

`/flights/fare-selection` is a new dedicated fare-family selection route. It
applies to a selected one-way offer or a validated return pair, performs
authoritative offer retrieval and revalidation, and sits before
`/booking/passengers`. It owns the approved Fare Selection mockup.

The Fare Selection mockup must not be mapped onto `/flights/return-options`.

Route counting: the measured Phase A baseline of **64** Next page routes is
retained. `/flights/fare-selection` is recorded as one approved future route,
giving a **planned target of 65** Next page routes unless another route is
retired.

### 5. Seat capability

Never create or port `/booking/seats` while no authoritative seat map exists.
The current capability is `seat_map_available=false`, so the current journey
omits the seat page and the Seats step. The design system may support a
**conditional** Seats step, enabled only when an authoritative future capability
becomes true. Seats are not permanently removed; they are capability-gated. The
Mock Shell fixture seat map and seat 3C must never be ported.

### 6. Payment

Final route model:

| Route | Responsibility |
|---|---|
| `/booking/payment` | Canonical payment-method selector |
| `/booking/payment/manual` | Manual Payment workflow |
| `/booking/payment/card` | AbhiPay secure handoff only |
| `/booking/payment/status` | Payment-status state |
| `/booking/payment/return` | Gateway-return state |

All references to a direct card-details form are removed. **Next.js must never
collect a card number, CVV or expiry.** `/manual` and `/card` are preserved as
compatibility routes. Laravel reserved-path protection and the final proxy
boundary must prevent these routes from entering the CMS catch-all.

### 7. Route ownership

Next.js owns normal public browser pages, CMS pages, the Customer portal, the
Agent and Agent Staff portal, and themed error and not-found states.

Laravel owns `/laravel/*` actions and APIs, authentication/session/CSRF
authority, signed verification actions, payment callbacks, supplier, search,
booking, PNR and ticketing, and downloads and operational actions.

Blade remains temporary until parity is proven. Public Blade routes are gated or
retired in **Phase H**, not during the theme build.

### 8. Robots

`frontend/app/robots.ts` is the final public robots authority. Laravel
`public/robots.txt` is aligned or retired at cutover. Transactional, tokenized,
private and portal pages require page-level `noindex,nofollow` metadata.

### 9. Navigation

Remove Flight Status from the target navigation until a real authoritative
feature exists. Remove Baggage, or relabel it as FAQs only where its visible
label is FAQs. Misleading capability labels must not point at `/faq`.

### 10. CMS template field

Frontend template resolution is approved for this rebuild. No database template
column is added. A Dev CP-selectable template field is deferred to a separately
approved backend phase.

### 11. Additional corrections

- Auth social-login controls are conditional on authoritative enabled providers
  and functional callbacks; otherwise they are omitted.
- `destination`, `offer` and `article-index`/`article-detail` templates may be
  registered but must not create unsupported routes or fixture content.
- `CmsAction.href`, card links and image sources require the same allowlisted URL
  and host validation as sanitized HTML.
- The Mock Shell remains a visual and component scaffold only.
- No shell file is copied wholesale.
