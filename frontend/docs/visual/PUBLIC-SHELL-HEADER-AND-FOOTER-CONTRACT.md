# Public Shell, Header, and Footer Contract

Phase: **JP-UI-02**

## Shell ownership

| Route family | Layout | Shell |
|--------------|--------|-------|
| Homepage `/` | `app/page.tsx` | `PublicShell` |
| Public CMS `(public)/*` | `app/(public)/layout.tsx` | `PublicShell` |
| Flights/booking | `app/flights/layout.tsx` | `PublicShell` |
| Auth `(auth)/*` | `app/(auth)/layout.tsx` | `PublicShell` |
| Customer `/customer/*` | `app/customer/layout.tsx` | `PublicShell` + dashboard section |
| Agent `/agent/*` | `app/agent/layout.tsx` | `PublicShell` + dashboard section |
| 404 | `app/not-found.tsx` | `PublicShell` |

## Consolidation changes

- Removed per-page `PublicShell` duplication from auth, customer, and agent routes.
- Single skip link in root layout (`components/ui/SkipLink.tsx`).
- Single `main#main-content` landmark in `PublicShell`.
- Dashboard shells use `<section>` without duplicate `main` or skip link.

## Header (`SiteHeader`)

- Sticky, compact (`h-jp-nav`), tokenized surfaces
- Logo → desktop nav → ThemeSwitch → currency → account → Book Now
- Mobile: `MobileNavigation` drawer with ThemeSwitch, nav, account, CTA
- Navigation source: `lib/navigation.ts` (`primaryNavigation`)
- Book Now: `LinkButton` → `/flights`

## Footer (`SiteFooter`)

- Tokenized `bg-jp-footer` inverse brand column
- Columns from `footerColumns` in `lib/navigation.ts`
- Social links from `socialLinks` with `rel="noopener noreferrer"`
- **Newsletter removed** — no operational endpoint (JP-UI-01 finding)

## Unsupported controls

| Control | JP-UI-02 handling |
|---------|-------------------|
| Newsletter subscribe | Removed from footer |
| Hotels/Offers nav | Not in `primaryNavigation` unless configured later |
| Social login | Unchanged; Laravel-authoritative |

## Customer/Agent chrome

Public header/footer remain visible. Dashboard sidebar accessed via compact **Dashboard menu** toolbar on mobile (no duplicate JetPakistan top bar).
