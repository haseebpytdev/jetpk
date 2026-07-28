# JETPK External Frontend Audit — Claim Verification

**Phase:** JETPK-EXTERNAL-FRONTEND-AUDIT-CLAIM-VERIFICATION  
**Audit date:** 2026-07-27  
**Method:** Read-only static codebase verification (no production access, no browser runtime, no file mutations except this report)  
**External source:** `C:\Users\khadi\Downloads\● Fetch(httpsjetpakistan.pk).txt`

---

## 1. Baseline

| Item | Value |
|------|-------|
| Branch | `main` |
| Local HEAD | `ae820f5f6b9eeb296f15b09d0996179151742c2e` |
| `jetpk/main` | `ae820f5f6b9eeb296f15b09d0996179151742c2e` |
| HEAD equals `jetpk/main` | **Yes** |
| Untracked files | Preserved (not deleted) |

Recent commits (oneline):

```
ae820f5 docs(sabre): align Phase 18K summary final HEAD with closure commit
ea73edc docs(sabre): record Phase 18K final HEAD in summary
bae0e4a docs(sabre): finalize Phase 18 deployment manifest
72c6f3f docs(sabre): strip trailing whitespace in browser gate evidence
64c493e docs(sabre): record Phase 18J corrective commit SHAs
```

**Safe commands run:**

- `php artisan route:list` (login subset)
- `php artisan about`
- `php artisan ota:route-page-health-audit --all` → `pass=22 fail=0 server_errors=0`
- `php artisan jetpk:cms-route-safety-audit` → all collision/reserved-slug metrics 0
- `php artisan jetpk:sitemap-audit --no-dispatch`

---

## 2. Active JetPakistan render tree

Active theme: `jetpakistan` via `client_theme()->frontendTheme()` and `client_view()` resolution.

| Page | Route | Controller | Final Blade | Layout | Header / drawer / footer | Primary CSS | Primary JS |
|------|-------|------------|-------------|--------|--------------------------|-------------|------------|
| Homepage | `GET /` | `HomeController@index` | `themes/frontend/jetpakistan/frontend/home.blade.php` | `layouts/frontend.blade.php` | `partials/header`, `partials/drawer`, `partials/footer` | `tokens.css`, `theme.css`, `forms.css`, `jp-search.css` | `theme.js`, `search.js`, `passengers.js`, `airport-autocomplete.js`, `jp-dates.js`, `forms.js` (all `defer`) |
| Results | `GET /flights/results` | `FlightController@results` | `themes/frontend/jetpakistan/frontend/flights/results.blade.php` → includes `frontend/flights/partials/results-page.blade.php` | same | same | `results-base.css`, `results.css`, `jp-search.css`, `flight-cards.css`, FA CDN | `results.js`, `flight-cards.js`, inline controller in `results-page` |
| Booking lookup | `GET /lookup-booking` | `GuestBookingLookupController@showLookupForm` | `frontend/booking/lookup.blade.php` | same | same | layout CSS only | layout `theme.js` |
| Login | `GET /login` | `AuthenticatedSessionController@create` | `auth/login.blade.php` → `layouts/auth` → `layouts/frontend` | nested | same | layout CSS | `theme.js`; login may use AJAX via `data-jp-login-form` |
| Register | `GET /register` | `RegisteredUserController@create` | `auth/register.blade.php` | nested auth | same | layout CSS | `theme.js` |
| Forgot password | `GET /forgot-password` | `PasswordResetLinkController@create` | `auth/forgot-password.blade.php` | nested auth | same | layout CSS | `theme.js` |
| Support | `GET /support` | `SupportController@support` | `frontend/support.blade.php` | same | same | layout CSS | `theme.js`; `public-form-validation.js` not on support |
| About | `GET /about-us` | `SupportController@about` | `frontend/about.blade.php` | same | same | layout CSS | `theme.js` |
| FAQ | `GET /faq` | `ClientManagedPageController@faq` | `frontend/faq.blade.php` | same | same | layout CSS | `theme.js` |
| Terms | `GET /terms` | `ClientManagedPageController@terms` | `frontend/legal/show.blade.php` | same | same | layout CSS | `theme.js` |
| Privacy | `GET /privacy` | `ClientManagedPageController@privacy` | `frontend/legal/show.blade.php` | same | same | layout CSS | `theme.js` |
| Agent landing | `GET /agent/register` | `AgentRegistrationController@landing` | `frontend/agent-registration/landing.blade.php` | same | same | layout CSS | `theme.js` |
| Agent apply | `GET /agent/register/apply` | `AgentRegistrationController@create` | `frontend/agent-registration/form.blade.php` | same | same | layout CSS | `public-form-validation.js` |

**Inactive paths not audited as rendered:** `resources/views/frontend/home.blade.php`, `ui/site/v2/*`, `themes/frontend/v1-classic/*`, `deployment_packages/*` copies.

---

## 3. Document / layout semantics (active layout)

Source: `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php`

| Check | Present | Evidence |
|-------|---------|----------|
| `<!DOCTYPE html>` | Yes | line 10 |
| `<html lang>` | Yes | line 11 `lang="{{ str_replace('_', '-', app()->getLocale()) }}"` |
| Viewport meta | Yes | line 14 |
| `<title>` | Yes | lines 6–8, 16 (fallback to brand name) |
| `<main>` landmark | Yes | line 101 `<main class="jp-site-main" id="jp-main">` |
| Skip link | **No** | no `skip-link` / `#jp-main` skip anchor in layout |
| Header landmark | Yes | `partials/header.blade.php` line 6 `<header>` |
| Primary nav | Yes | header line 21 `<nav aria-label="Primary">` |
| Footer landmark | Yes | `partials/footer.blade.php` line 11 `<footer>` |
| Footer `<nav>` duplicate | **No** | footer uses `.fcol` link columns, not `<nav>` |

**Homepage meta (additional):** `frontend/home.blade.php` lines 7–29 push description, canonical, Open Graph, Twitter Card tags.

**JSON-LD:** No `application/ld+json` in active jetpakistan theme views (`rg` over `resources/views/themes/frontend/jetpakistan`).

**Navigation structure:** Header `<nav>` (desktop) + mobile drawer (plain `<a>` links, no `<nav>`) + footer columns (not `<nav>`). This is responsive/landmark separation, not two identical `<nav>` blocks.

---

## 4. Forms / accessibility inventory (active pages)

### Login (`auth/login.blade.php`)

| Control | id | name | type | Label `for` | autocomplete | required |
|---------|----|------|------|-------------|--------------|----------|
| login | login | login | text | Yes (`form-group`) | username | yes |
| password | password | password | password | Yes | current-password | yes |
| remember | — | remember | checkbox | enclosing label | — | no |
| client_slug | — | client_slug | hidden | — | — | no |
| submit | — | — | submit | "Log in" text | — | — |

Errors: field-level `jp-field-error`; alert `role="alert"` for social errors. Login AJAX alert: `role="alert" aria-live="polite"`.

**Totals:** 4 inputs + 1 submit; 2 labeled via `x-jp.form-group`; 1 checkbox with enclosing label.

### Register (`auth/register.blade.php`)

7 named fields + CSRF + submit; all primary fields have `x-jp.form-group` labels. Country code: `<select aria-label="Country code">`. Password confirmation has label but **no** `aria-describedby` match-error span.

### Forgot password

1 email field with label; POST form with CSRF.

### Booking lookup

2 fields (`booking_reference`, `lookup_email`) with labels; POST + CSRF + Turnstile widget.

### Agent application (`form.blade.php`)

Full POST `<form>` with labels on all visible fields; terms checkbox uses `<label for="terms">`. Hidden fields: `last_name`, `country`, `office_address`.

### Home search (`flights-panel.blade.php` + components)

| Control | Label | Notes |
|---------|-------|-------|
| from/to display | `label for="{{id}}-display"` | combobox text + hidden code field |
| depart/return | `label id="{{id}}-label"` on date trigger button | hidden `name=depart` / `return_date`, not `type=date` |
| direct / nearby / flexible | enclosing `<label class="check">` | checkbox `hidden` inside label |
| passengers | `label id="{{widgetId}}-pax-label"` | steppers with `aria-label` on ± buttons |
| cabin | `aria-label="Cabin class"` on select | inside panel |
| adults/children/infants | compat `<select>` in `aria-hidden` block | tabindex -1 |

Form: `method="get"` `action="{{ client_route('flights.results') }}"` with `novalidate`. JS `prepareSubmit` only prevents default on pax validation failure; otherwise normal GET submit.

**Progressive enhancement (search):** Without JS, user can submit GET with hidden airport codes (empty unless manually set), hidden dates (empty unless set), and compat selects for pax — **severely degraded**, not zero functionality, but airport/date UX requires JS for practical use.

### Results

Server renders skeleton `<article>` cards (no price text "PKR 0"); fares loaded client-side via `results-page` inline script from `/flights/results/data`. `aria-live="polite"` on results list.

---

## 5. Progressive enhancement summary

| Flow | `<form>` | action | method | CSRF | HTML path | JS interception |
|------|----------|--------|--------|------|-----------|-----------------|
| Login | Yes | `/login` | POST | Yes | Yes | Optional AJAX (`data-jp-login-form`) |
| Register | Yes | `/register` | POST | Yes | Yes | Field validation endpoint exists |
| Forgot | Yes | `/forgot-password` | POST | Yes | Yes | None observed |
| Lookup | Yes | `lookup-booking.submit` | POST | Yes | Yes | Turnstile required |
| Agent apply | Yes | `agent.register.store` | POST | Yes | Yes | `public-form-validation.js` |
| Search | Yes | `flights.results` | GET | No | Yes | `search.js` prepareSubmit; airport/date JS |

**Hydration:** No React, Vue, or Next.js hydration in active JetPakistan public theme. Results use client-side fetch + DOM update (not hydration).

**"PKR 0" in active path:** No literal `PKR 0` in `resources/` or `public/` for views. Results use skeleton placeholders. `JetpkHomepageFareDisplay` explicitly avoids zero amounts.

---

## 6. Images / performance (static)

**Hero:** When CMS manifest exists, `sections/hero.blade.php` uses `<picture>`, `preload`, `fetchpriority="high"`, WebP/AVIF via `JetpkHeroLcpPresenter`. Fallback alt from presenter defaults to `"JetPakistan flights from Pakistan"` (not `alt=""`).

**Logo (`brand-logo.blade.php`):** `<img>` with `width="220" height="40" loading="eager" decoding="async"`. Rendered in header, drawer, footer (and auth card) — same URL possible multiple times in DOM.

**Destination cards (`dest-card.blade.php`):** CSS background via `--jp-dest-image` inline style; `aria-label` on card.

**Route cards:** No images; div/a card markup.

**Airline logos on results:** Loaded dynamically in JS; static audit cannot count 400+ or lazy-load behavior.

---

## 7. CSS / JavaScript stack

| Item | Status |
|------|--------|
| CSS custom properties / tokens | Yes — `public/themes/frontend/jetpakistan/css/tokens.css` |
| `focus-visible` rules | Yes — `theme.css` lines 29–55+ |
| `prefers-reduced-motion` | Yes — `theme.css` ~772, 818 |
| `font-display: swap` | Yes — Google Fonts URL in layout line 26 |
| Deferred scripts | Yes — `theme.js defer`; search stack `defer` |
| Module scripts | No `type="module"` on theme scripts |
| Third-party widgets | Turnstile on lookup/support; WhatsApp plain links on support (not embedded FB/IG SDK in layout) |

**Framework:** Laravel Blade + Alpine not in layout stack; Vite builds admin/dashboard assets; public theme uses standalone JS files under `public/themes/frontend/jetpakistan/js/`.

---

## 8. Footer / social

- Copyright: dynamic `date('Y')` in `partials/footer.blade.php` line 41.
- Social links: `aria-label` + `rel="noopener"` on external URLs (lines 44–47).
- tel/mailto: support and about pages use `tel:` and `mailto:` links.

---

## 9. robots.txt and sitemap

**`public/robots.txt` exact content:**

```
User-agent: *
Disallow:
```

Allows all paths (empty `Disallow`). No route override for robots in `routes/`.

**Sitemap:** No `public/sitemap.xml`. No sitemap route in `routes/`. `ReservedPublicPath` lists `sitemap.xml` as reserved. External fetch reported 404 / 0 bytes for `/sitemap.xml` — consistent with missing generator.

---

## 10. Security headers (repository)

**`app/Http/Middleware/SecurityHeaders.php`** (registered in `bootstrap/app.php`):

| Header | In app code |
|--------|-------------|
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | camera/mic/geo/payment restricted |
| `Content-Security-Policy` | **Not set** |
| `Strict-Transport-Security` | **Not set** |

`public/.htaccess` — rewrite only; no security headers.

Production-only header configuration (cPanel/Nginx) is **not verifiable** from repository alone.

---

## 11. Claim matrix

Full row-level matrix with classifications: see `docs/audits/JETPK-EXTERNAL-FRONTEND-AUDIT-VERIFICATION.tsv`.

---

## 12. Confirmed actionable defects (code-backed only)

1. **Skip link absent** on all public layout pages.
2. **JSON-LD structured data absent** on public pages.
3. **hreflang absent** for Urdu/English.
4. **Content-Security-Policy not set** in application middleware.
5. **HSTS not set** in application middleware (production may still set it).
6. **sitemap.xml missing** (no static file or route).
7. **Search form `novalidate`** disables native HTML5 validation.
8. **Support form `novalidate`** same.
9. **Date fields** use button + hidden input pattern, not `type="date"` (a11y/PE gap).
10. **Passenger stepper buttons** ~26–30px (`jp-search.css` 368–370), below 44px touch target guidance.
11. **Password confirmation** lacks `aria-describedby` / live match error region.
12. **Route/destination cards** lack `<article>` / list semantics.
13. **Passenger selector** lacks `<fieldset>/<legend>` (uses div + label).
14. **Non-home CMS/auth pages** lack `@push('head-meta')` canonical/OG blocks (title via `@section('title')` only).
15. **Google sign-in disabled state** is `div` not `button disabled` when provider not configured.

---

## 13. Claims to discard (false or technically incorrect vs codebase)

- Duplicate `<nav>` keyboard trap (only one `<nav>`; drawer/footer are not duplicate nav landmarks).
- Missing `<main>` landmark.
- Missing viewport / `<html lang>` / homepage `<title>`.
- Homepage missing description, canonical, OG, Twitter (present on home).
- Zero form labels on login/register/lookup/agent (labels present via `x-jp.form-group`).
- Agent apply has no `<form>` tag.
- robots.txt `Disallow: /` in repository (allows all).
- Server renders literal "PKR 0" on results (skeletons + no string in code).
- Hydration mismatch / React hydration (no hydration framework).
- No CSS custom properties / tokens.css.
- No `focus-visible` or reduced-motion rules.
- No `font-display: swap` (Google Fonts URL includes it).
- X-Frame-Options / Referrer-Policy / Permissions-Policy entirely missing (set in middleware).
- FAQ lacks `<details>/<summary>` (FAQ page uses them).
- Footer copyright mojibake in template (uses `©` and `date('Y')`).
- Phone country codes "not select" on register (uses `<select>`).
- Recommendations for Navigation.tsx, Next.js splitChunks, mandatory utility-first CSS, mandatory container queries (wrong stack).

---

## 14. Claims requiring browser QA

- Color contrast (PKR green on white).
- LCP / CLS / INP / TBT actual timings.
- Hero image byte size when manifest missing on production.
- Airline logo count and lazy-loading on live results.
- Whether multiple logo `<img>` cause duplicate network downloads (cache behavior).
- Console errors (`navigator.share`, date polyfill, scroll listeners).
- Horizontal scroll / overflow on trending routes carousel.
- tel link clickability on hero (desktop).
- Turnstile / third-party script main-thread impact.
- Live robots.txt if server overrides `public/robots.txt`.

---

## 15. Claims requiring production header verification

- `Strict-Transport-Security` (HSTS) on `jetpakistan.pk`.
- `Content-Security-Policy` if set at CDN/Apache/Nginx.
- `Cache-Control` on `/storage/` branding assets.
- Whether `X-Frame-Options: SAMEORIGIN` vs `DENY` is acceptable for policy.

---

## 16. Files that would need correction (not modified in this phase)

| Area | Files |
|------|-------|
| Skip link | `layouts/frontend.blade.php`, possibly `theme.css` |
| JSON-LD / hreflang / page meta | `layouts/frontend.blade.php` or shared meta partial; CMS SEO resolver integration |
| CSP / HSTS | `app/Http/Middleware/SecurityHeaders.php` or server config |
| Sitemap | new route/command + `public/sitemap.xml` or dynamic route |
| Search a11y | `date-field.blade.php`, `passenger-selector.blade.php`, `jp-search.css`, `flights-panel.blade.php` |
| Card semantics | `route-card.blade.php`, `dest-card.blade.php`, `routes.blade.php`, `destinations.blade.php` |
| Register a11y | `auth/register.blade.php` |
| Google disabled UI | `components/jp/google-sign-in.blade.php` |
| PWA meta | `layouts/frontend.blade.php` |

---

## 17. Classification totals

| Classification | Count |
|----------------|-------|
| TRUE | 17 |
| FALSE | 28 |
| PARTLY_TRUE | 27 |
| UNVERIFIED_SERVER_CONFIG | 3 |
| UNVERIFIED_BROWSER_RUNTIME | 5 |
| IRRELEVANT_REQUIREMENT | 4 |
| TECHNICALLY_INCORRECT | 3 |
| **Total claims assessed** | **87** |

---

## 18. Final git status

After this audit, only new audit artifacts under `docs/audits/` should appear as additions; no application code modified.
