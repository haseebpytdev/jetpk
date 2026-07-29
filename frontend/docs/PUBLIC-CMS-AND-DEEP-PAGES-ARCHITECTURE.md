# Public CMS and Deep Pages Architecture (JP-FE-13)

## Ownership

| Layer | Responsibility |
|-------|----------------|
| Laravel | CMS records, publication, SEO source data, contact/support mutations, Turnstile validation, sitemap route inventory |
| Next.js | Public presentation, CMS rendering, forms UI, metadata consumption, structured data presentation |

## Laravel JSON contracts

| Endpoint | Purpose |
|----------|---------|
| `GET /api/public/content/config` | Public-safe brand, contact, paths, social links |
| `GET /api/public/content/pages/{pageKey}` | Managed pages (`about`, `support`, `faq`, `terms`, `privacy`, `global`) |
| `GET /api/public/content/cms/{slug}` | Active `CmsPage` records |
| `GET /api/public/content/custom/{slug}` | Published custom `ClientPage` slugs |
| `GET /api/public/content/sitemap-routes` | Authoritative public route inventory |
| `GET /sitemap.xml` | Laravel XML sitemap (Blade-era parity) |
| `POST /support` | Contact + public support submissions |

## Next.js routes

| Route | Service | Notes |
|-------|---------|-------|
| `/about-us` | `PublicPageService` | CMS managed page |
| `/contact` | `SiteContactService`, `ContactForm` | Next-owned; Laravel redirects legacy `/contact` |
| `/support` | `SupportContentService`, `ContactForm` | Turnstile when enabled |
| `/faq`, `/terms`, `/privacy` | Managed page services | Legal uses `LegalPageService` |
| `/pages/[slug]` | `CmsPageService` | Legacy/admin CMS pages |
| `/[slug]` | `CustomPageService` | Custom client pages (reserved slug guard) |
| `/legal/[slug]` | `CustomPageService` | Maps `refund` → `refund-policy`, etc. |
| `/sitemap` | HTML index from Laravel routes | Human-readable |
| `/sitemap.xml` | Next `sitemap.ts` | Consumes Laravel route contract |

## No-fixback policy

- Production builds do **not** substitute fixture legal/copy when CMS is empty (`allowContentFixtures()`).
- Development may use fixtures when `NODE_ENV=development` or `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=true`.
- Public layout uses `dynamic = "force-dynamic"` so CMS fetches occur at request time.

## Safe rendering

- CMS HTML sanitized server-side (`ClientSafeHtmlSanitizer`).
- Custom pages render structured sections as plain text paragraphs.
- `dangerouslySetInnerHTML` only for sanitized CMS HTML bodies.

## Feature module

`frontend/features/public-content/` — services, components, SEO helpers, Turnstile-integrated `ContactForm`.
