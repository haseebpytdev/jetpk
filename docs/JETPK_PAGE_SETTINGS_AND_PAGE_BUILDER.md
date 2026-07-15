# JetPK Page Settings & Page Builder

## Configurable page keys

| Key | Label | Sections |
|-----|-------|----------|
| `home` | Home page | hero, trust_chips, why_book, groups, agent_callout, faq, … |
| `about` | About | hero |
| `support` | Support | hero |
| `group-search` | Group search hero | hero |
| `footer` | Footer & links | description, legal, social |
| `global` | Global public | announcement, header_support, seo |
| `terms` / `privacy` / `faq` | Legal/help | content |
| `booking-lookup` | Booking lookup | hero |
| `agent-registration` | Agent landing | hero, benefits |

Schema: `App\Support\Client\ClientPageSectionSchema`

## Admin workflow

1. Admin → Page settings → select page
2. Edit sections (draft auto-saved)
3. Upload assets (`hero_background`, `hero_mobile`, `og_image`)
4. Preview via iframe or “Open preview tab”
5. Publish draft

## Validation

- `content` array required on PATCH
- Strings sanitized via `ClientPageContentResolver::sanitizeValue` (strip tags)
- Images: jpg/png/webp/svg, max 5MB

## Fallback

- Missing published content → JetPK defaults in `ClientPageContentResolver`
- Never master-client public content

## Frontend wiring

- `client_page_content($pageKey, $dotPath, $default)` in section blades
- Homepage `why-book` section reads `why_book.*` from page settings (9G)

## Audit

```bash
php artisan jetpk:page-settings-audit
```
