# Public Content Architecture (JP-FE-03)

## Purpose

Next.js public content pages consume Laravel-managed CMS data through a typed service boundary. Fixtures provide presentation fallbacks when published CMS content is empty.

## Laravel contracts (audited)

| Route | Method | Controller | Auth | Response |
|-------|--------|------------|------|----------|
| `/about-us` | GET | `SupportController@about` | Public | Blade |
| `/support` | GET | `SupportController@support` | Public | Blade |
| `/support` | POST | `SupportController@store` | Public | Redirect or JSON |
| `/faq` | GET | `ClientManagedPageController@faq` | Public | Blade |
| `/terms` | GET | `ClientManagedPageController@terms` | Public | Blade |
| `/privacy` | GET | `ClientManagedPageController@privacy` | Public | Blade |
| `/pages/{slug}` | GET | `CmsPageController@show` | Public | Blade |
| `/contact` | GET | Redirect → `/about-us` (Laravel legacy) | — | Next owns `/contact` |

### JSON API (additive, JP-FE-03)

| Route | Purpose |
|-------|---------|
| `GET /api/public/content/csrf-token` | Session CSRF for form POST |
| `GET /api/public/content/turnstile-config` | Public Turnstile site key + response field (JP-FE-10A) |
| `GET /api/public/content/site-contact` | `ClientGlobalContactResolver` |
| `GET /api/public/content/support/categories` | `SupportTicketCategory` enum |
| `GET /api/public/content/pages/{pageKey}` | Managed page CMS payload |
| `GET /api/public/content/cms/{slug}` | `CmsPage` active pages |
| `GET /api/public/content/custom/{slug}` | Custom `ClientPage` slugs |

### Support / contact form (`POST /support`)

Fields (from `StorePublicSupportTicketRequest`):

- `form_type`: `support` \| `contact`
- `name` (required for guests)
- `email` (required)
- `subject` (required for support)
- `category` (required for support)
- `body` (required)
- `booking_reference` (optional)
- `website` (honeypot, prohibited)
- `cf-turnstile-response` (when Turnstile enabled)

Contact mode auto-sets `subject=General inquiry` and `category=other`.

## Contact data source

**Authoritative:** `ClientGlobalContactResolver` (CMS global + bootstrap merge).

**Canonical values** (bootstrap in `ClientPageBootstrapTemplate::globalContent()`):

- Phone: `0311 1222427` / `+923111222427`
- Email: `ota@jetpakistan.pk`
- WhatsApp: `923111222427`
- Website: `https://www.jetpakistan.com`
- Office: Century Tower, Kalma Chowk, Gulberg III, Lahore
- Hours: `24/7`

**Conflict note:** `config/ota-client.php` and `config/ota-brand.php` contain alternate demo phones — not used for public UI; resolver/bootstrap is authoritative.

## Fixture boundaries

| Service | Fixture file | Used when |
|---------|--------------|-----------|
| `PublicPageService` | `fixtures/about.ts` | CMS `about` empty |
| `FaqService` | `fixtures/faq.ts` | CMS `faq` empty |
| `SupportContentService` | `fixtures/support.ts` | CMS `support` empty (+ topic taxonomy) |
| `LegalPageService` | `fixtures/legal.ts` | CMS legal empty |
| `SiteContactService` | `fixtures/site-contact.ts` | API unreachable |

## Component inventory

- `PublicPageHero`, `ContentSection`, `ContentRichText`, `ContentCardGrid`
- `Breadcrumbs`, `TableOfContents`, `LegalDocumentLayout`
- `CmsPageRenderer`, `ContactDetailsCard`, `ContactForm`
- `FaqPageClient`, `SupportPageClient`, `AboutPageContent`
- `EmptyContentState`, `PublicContentErrorState`

## Next.js routes

- `/about-us`, `/support`, `/faq`, `/contact`, `/terms`, `/privacy`
- `/pages/[slug]` (CMS)
- `not-found.tsx`, `error.tsx`

## Security

- No direct DB access from Next.js
- CMS HTML only from Laravel-sanitized API (`ClientSafeHtmlSanitizer`)
- Forms POST to Laravel with CSRF + cookies via `/laravel/*` proxy
- No fake success without Laravel JSON `ok: true`
