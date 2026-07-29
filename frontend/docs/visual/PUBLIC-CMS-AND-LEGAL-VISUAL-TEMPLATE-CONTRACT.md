# Public CMS and Legal Visual Template Contract (JP-UI-03)

## Templates

| Route | Renderer |
|-------|----------|
| `/pages/[slug]` | `CmsPageRenderer` |
| `/legal/[slug]`, `/[slug]` | `CustomClientPageRenderer` |
| `/terms`, `/privacy` | `LegalDocumentLayout` |
| `/faq` | FAQ page + `PublicFaq` |

## Rules

- Published content only
- Safe HTML via `ContentRichText` / sanitizer contract
- Shared typography and spacing from JP-UI-02 tokens
- One `h1` per page
