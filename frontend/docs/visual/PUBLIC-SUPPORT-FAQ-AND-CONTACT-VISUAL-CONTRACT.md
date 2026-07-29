# Public Support, FAQ, and Contact Visual Contract (JP-UI-03)

## Support page

- Hero `h1` from CMS support page hero
- Topic search filters authoritative fixture/CMS topics client-side
- Category cards from CMS `department_cards` or support fixture in preview only
- Contact details from Laravel contact resolver (no hardcoded phone/email)
- Support form with Turnstile preserved (`ContactForm`)
- Authenticated customer/agent support remains in dashboards

## FAQ

- Shared `PublicFaq` disclosure component
- Used by `/faq` via `FaqPageClient`
- Keyboard accessible buttons with `aria-expanded`

## Contact

- `/contact` retains `ContactForm` + `SiteContactService`
- No fake success states
