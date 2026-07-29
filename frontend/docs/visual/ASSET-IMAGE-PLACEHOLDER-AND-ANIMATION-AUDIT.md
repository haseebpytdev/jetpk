# Asset, Image, Placeholder, and Animation Audit

Phase: **JP-UI-01**

## Image region inventory

| Route / page | Component | Current source | Owner | Hardcoded? | Aspect / slot | Alt policy | Loading | Missing fallback | Dark variant | Mockup gap | Phase |
|--------------|-----------|----------------|-------|------------|---------------|------------|---------|------------------|--------------|------------|-------|
| Homepage hero | `HomepageHero` / `HeroVisual` | Inline SVG | Design asset | Yes (inline) | ~5:3 | Descriptive | None | SVG always present | No | Photo hero expected | JP-UI-06 |
| Destinations | `DestinationsSection` | `/images/home/destination-*.svg` | Fixture | Yes | Card ~4:3 | Per fixture | None | Broken image risk | No | Photo carousel | JP-UI-03/06 |
| Featured offers | `FeaturedOffersSection` | `/images/home/offer-*.svg` | Fixture | Yes | Wide card | Often empty alt | None | SVG | No | Photo promos | JP-UI-03/06 |
| About | CMS sections | CMS / static | CMS | Mixed | Section-based | CMS | Skeleton partial | CMSSectionRenderer | No | Animation area | JP-UI-03 |
| Support | Support hero | Static/CMS | CMS | Mixed | Hero band | CMS | Partial | Generic | No | Agent illustration | JP-UI-06 |
| Auth login/register | Auth layout | Minimal / none | Design | Gap | Split 50/50 | Decorative | None | Plain surface | No | Illustration slot | JP-UI-05/06 |
| Results | Airline logos | Laravel `airline_logo_url` | **D** | No | Square ~32px | Airline name | Lazy | Initials fallback | No | OK | JP-UI-04 |
| Lookup | Lookup hero | Component-level | Design | Partial | Hero | Descriptive | None | Gradient | No | Photo hero | JP-UI-05 |
| Confirmation | `BookingStatusHero` | Tone-based, no photo | **D** | No | Banner | Status text | N/A | N/A | No | Success illustration | JP-UI-06 |
| Footer | Logo inverse | `JetPakistanLogo` | **A** | Component | Auto | Brand | N/A | Text fallback | No | OK | JP-UI-02 |

## Future image policy (approved)

1. **CMS/media exists** → render optimized authoritative image.
2. **Loading** → dimensionally accurate skeleton (no layout shift).
3. **Optional missing** → approved generic JetPakistan fallback illustration.
4. **Required missing** → hide section or show honest unavailable state.
5. **Load error** → accessible fallback, preserve slot geometry.
6. **Never** use mockup PNGs as runtime assets.

## Placeholder findings

- Homepage fixture SVGs are **production placeholders** labeled “Sample from PKR …” — must migrate to CMS (**F** classification).
- Several `imageAlt: ""` on offers → accessibility gap (**Medium**).

---

## Animation inventory

| Animation | Location | Purpose | Trigger | State dep | Duration | Reduced motion | Current | Mockup intent | Action | Phase |
|-----------|----------|---------|---------|-----------|----------|----------------|---------|---------------|--------|-------|
| `AnimatedFlightPath` | Homepage hero | Brand motion | Mount | None | CSS loop | `prefers-reduced-motion` respected on About | SVG dash animation | Dotted path between sections | Extend to section divider | JP-UI-06 |
| Hero gradient blobs | HomepageHero | Ambience | Mount | None | Static | N/A | CSS blur | Photo parallax | Replace with photo layer | JP-UI-06 |
| Card hover lift | Various cards | Affordance | Hover | None | ~200ms | OK | Partial | Subtle shadow | Standardize token | JP-UI-02 |
| Carousel scroll | Destinations | Browse | User / buttons | CMS data | Smooth scroll | OK | Basic | Horizontal carousel | CMS-driven | JP-UI-03 |
| Accordion expand | FAQ, filters | Reveal | Click | None | CSS | OK | OK | Same | — | — |
| Skeleton pulse | Results loading | Loading | Fetch | **D** | Tailwind pulse | OK | `ResultSkeleton` | Same | — | — |
| Progress step transition | BookingProgress | Orientation | Route change | **D** | None | OK | Instant | Step connector animate | JP-UI-04 |
| Success celebration | Confirmation | Feedback | Ticketed state | **D** Laravel | One-shot | Required | `show_celebration` flag | Confetti intent | Only when authoritative | JP-UI-06 |
| Theme transition | — | Theme switch | Toggle | User pref | ~200ms | Required | **Missing** | Smooth cross-fade | Implement | JP-UI-02 |
| Turnstile widget | Lookup/contact | Security | Config | Laravel | External | N/A | Cloudflare | N/A | Keep | JP-OPS |
| Drawer/modal | Results details, mobile filters | Focus | User | **D** | CSS | OK | Implemented | Same | Polish motion | JP-UI-04 |

## Animation rules (enforced in later phases)

- No fake operational progress animation.
- Loading only during real requests.
- Payment/booking success motion only after Laravel confirmation.
- No blocking motion, layout shift, or hover-only essential behavior.
- Respect `prefers-reduced-motion`.

## Asset readiness summary

| Category | Ready | Partial | Missing |
|----------|------:|--------:|--------:|
| Homepage marketing images | 0 | 4 SVG slots | Photo hero, offer photos |
| Auth illustrations | 0 | 0 | 2 split-screen slots |
| Support/About decorative | 1 | 2 | Agent character |
| Booking flow | 2 | 1 | Success illustration |
| Icons / UI chrome | High | — | — |
