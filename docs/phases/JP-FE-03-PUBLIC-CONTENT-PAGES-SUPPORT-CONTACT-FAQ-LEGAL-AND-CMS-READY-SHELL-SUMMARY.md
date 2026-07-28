# JP-FE-03 — Public Content Pages, Support, Contact, FAQ, Legal, and CMS-Ready Shell

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-03-PUBLIC-CONTENT-PAGES-SUPPORT-CONTACT-FAQ-LEGAL-AND-CMS-READY-SHELL |
| Branch | `phase/jetpk-fe-03-public-content` |
| Feature commit | `719b30c` |
| Docs commit | `ec7ceda` |
| Merge commit | `5162186` |
| Final status | COMPLETE (local merge; no deployment) |

## Objective

Deliver the JetPakistan Next.js public content layer with Laravel-backed CMS integration, honest form submission, verified contact data, and branded 404/error handling.

## Laravel audit summary

- **Managed pages:** `ClientPageRenderer` + `ClientPageContentResolver` for `about`, `support`, `faq`, `terms`, `privacy`
- **CMS pages:** `CmsPage` model at `/pages/{slug}`
- **Custom pages:** `ClientPage` + `ClientManagedPageController@customShow`
- **Support tickets:** `POST /support` via `StorePublicSupportTicketRequest` (public, throttled)
- **Contact:** Uses same endpoint with `form_type=contact` (Laravel `/contact` redirects to `/about-us`; Next.js owns `/contact`)
- **Contact details:** `ClientGlobalContactResolver` (bootstrap + CMS global)
- **404/500 Blade:** `themes/frontend/jetpakistan/errors/*` (unchanged; Next handles its own routes)

## Integration decisions

| Page | Integration |
|------|-------------|
| About | Laravel JSON `pages/about` + fixture fallback |
| Support | CMS content + Laravel support form + topic fixtures |
| FAQ | CMS FAQ + fixture fallback; client search/filter |
| Contact | Verified contact + `POST /support` (`form_type=contact`) |
| Terms/Privacy | CMS legal sections + bootstrap fixture fallback |
| CMS `/pages/[slug]` | Laravel `CmsPage` JSON API |
| Forms | JSON response added to `SupportController@store` when `expectsJson()` |

## Verified contact source

`ClientPageBootstrapTemplate::globalContent()['contact']` via `ClientGlobalContactResolver`:

- Phone `0311 1222427` / `+923111222427`
- Email `ota@jetpakistan.pk`
- WhatsApp `923111222427`
- Office Century Tower, Kalma Chowk, Gulberg III, Lahore
- Hours `24/7`

## Routes completed (Next.js)

`/about-us`, `/support`, `/faq`, `/contact`, `/terms`, `/privacy`, `/pages/[slug]`, branded `not-found`, `error.tsx`

## Tests executed

| Suite | Result |
|-------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS (10 routes) |
| Playwright `public-content.spec.ts` | 14/14 PASS |
| Playwright `public-shell.spec.ts` + `homepage.spec.ts` | PASS (regression) |
| `php artisan test tests/Feature/Jetpk/PublicContentApiTest.php` | 8/8 PASS |

## Known limitations

- Newsletter footer form remains non-functional (preventDefault only; pre-existing)
- CMS HTML rendering uses `dangerouslySetInnerHTML` only for Laravel-sanitized CMS body
- Laravel must be reachable at build/runtime for live CMS; 3s fetch timeout falls back to fixtures
- Turnstile required when enabled in Laravel env (disabled in tests)

## No deployment confirmation

No production server, DNS, or SFTP changes were made.

## Next phase

JP-FE-04-AUTHENTICATION-OTP-REGISTRATION-SESSION-BOOTSTRAP-AND-ROLE-ROUTING
