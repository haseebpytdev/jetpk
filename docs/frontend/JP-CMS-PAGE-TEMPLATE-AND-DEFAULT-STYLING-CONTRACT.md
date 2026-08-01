# JP-CMS-PAGE-TEMPLATE-AND-DEFAULT-STYLING-CONTRACT

Phase: **JP-PUBLIC-NEXT-THEME-01**
Branch: `phase/jetpk-public-next-theme-rebuild`
Baseline: `111b2925f12369dbcbef139c9b251726a5a785fd`
Authority: [docs/architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md](../architecture/JP-PUBLIC-NEXT-THEME-REBUILD-ARCHITECTURE.md) §§9–10, decisions in §16
Status: **Phase A contract, closed by JP-PUBLIC-NEXT-THEME-01A decisions — no runtime implementation. Implemented in Phase C.**

---

## 1. Objective

Any CMS page created in the future must inherit the JetPakistan public design automatically, without a developer writing page-specific styling and without a CMS author being able to inject arbitrary markup, styling or script.

Three guarantees follow from that:

1. **Automatic inheritance** — a new page with no styling instructions renders with correct JetPakistan typography, spacing, color and responsive behavior.
2. **Bounded authorship** — authors choose content and structure, never presentation primitives.
3. **Safe failure** — an unknown template, unknown block or malformed payload degrades to a valid themed page, never to Blade, Master or Parwaaz output and never to a blank or broken screen.

---

## 2. Current backend reality

This contract is written against what exists today, not against a hoped-for schema.

| Table | Model | Template column |
|---|---|---|
| `cms_pages` | `App\Models\CmsPage` | **none** |
| `client_pages` | `App\Models\ClientPage` | **none** |
| `client_page_settings` | `App\Models\ClientPageSetting` | **none** |

`cms_pages` carries `title`, `slug`, `content`, `excerpt`, `featured_image_path`, SEO fields, `canonical_url`, `robots`, `status` and footer fields. `client_pages` carries `slug`, titles, navigation flags, header/footer flags and `seo_json`. `client_page_settings` carries `page_key`, `status`, SEO, `content_json` and `settings_json`.

**No backend template field exists.**

**Approved (architecture §16 decision 10):** frontend template resolution is approved for this rebuild. **No database template column is added.** A Dev CP-selectable template field is **deferred to a separately approved backend phase**. Template selection is therefore resolved in the frontend from the page key, route family or slug (§6).

### Consumed contracts

| Endpoint | Shape |
|---|---|
| `GET /api/public/content/pages/{pageKey}` | `{ page_key, source: 'empty'\|'cms', content, seo, contact, sections_order }` |
| `GET /api/public/content/cms/{slug}` | `{ slug, title, subtitle, body_html, seo: { title, description, canonical, robots }, source: 'cms' }` |
| `GET /api/public/content/custom/{slug}` | `{ slug, title, content, seo, source: 'empty'\|'cms' }` |
| `GET /api/public/content/config` | brand, domain, contact, legal/support/lookup/groups paths, social links, default SEO |

Allowed managed page keys: `about`, `support`, `faq`, `terms`, `privacy`, `global`.
Full page-key set: `home`, `about`, `support`, `group-search`, `login`, `register`, `footer`, `global`, `terms`, `privacy`, `faq`, `booking-lookup`, `agent-registration`, plus `custom:{slug}`.

Two content shapes exist and both must be supported: `cms/{slug}` returns raw `body_html`, while `custom/{slug}` returns structured sections whose `type` defaults to `rich_text`.

---

## 3. Structured block rendering — the preferred path

Structured blocks are the primary authoring model. `CmsPageRenderer` maps each block to one approved themed component. A block never carries class names, styles or markup.

### 3.1 Block union

Defined in `frontend/lib/cms/block-types.ts`:

```ts
export type CmsBlock =
  | { type: "hero";      eyebrow?: string; heading: string; body?: string;
                         image?: CmsImageRef; actions?: CmsAction[] }
  | { type: "richText";  html: string }
  | { type: "image";     image: CmsImageRef; caption?: string;
                         width?: "container" | "wide" | "full" }
  | { type: "cardGrid";  heading?: string; body?: string; columns?: 2 | 3 | 4;
                         items: Array<{ icon?: string; heading: string;
                                        body?: string; href?: string }> }
  | { type: "stats";     heading?: string;
                         items: Array<{ value: string; label: string; icon?: string }> }
  | { type: "timeline";  heading?: string;
                         items: Array<{ marker: string; heading: string; body?: string }> }
  | { type: "faq";       heading?: string;
                         items: Array<{ question: string; answer: string }> }
  | { type: "callout";   tone?: "info" | "success" | "warning" | "danger";
                         heading?: string; body: string; action?: CmsAction }
  | { type: "gallery";   heading?: string; columns?: 2 | 3 | 4; items: CmsImageRef[] };

export type CmsImageRef = { src: string; alt: string; width?: number; height?: number };
export type CmsAction   = { label: string; href: string };
```

### 3.2 Block to component mapping

| Block | Component | Notes |
|---|---|---|
| `hero` | `CmsHero` | One `<h1>` per page; `actions` render as `PublicButton` |
| `richText` | `CmsRichText` | Sanitized, wrapped in `.jp-cms-content` (§4) |
| `image` | `CmsImage` | `alt` is mandatory; width is a token, not a pixel value |
| `cardGrid` | `CmsCardGrid` | Uses `PublicCard`; column count is a bounded enum |
| `stats` | `CmsStats` | Values are strings; no client-side computation |
| `timeline` | `CmsTimeline` | Ordered list semantics |
| `faq` | `CmsFaq` | Accessible disclosure pattern |
| `callout` | `CmsCallout` | Tone is a bounded enum, never a color value |
| `gallery` | `CmsGallery` | Every item requires `alt` |

Every block is wrapped by `CmsSection`, which owns vertical rhythm and container width. Blocks never set their own outer spacing.

### 3.2.1 URL and host validation for structured blocks (decision 11)

Structured blocks are data, but their URL-bearing fields are just as dangerous as raw HTML attributes. **`CmsAction.href`, `cardGrid` item `href` values and every `CmsImageRef.src` require the same allowlisted URL and host validation applied to sanitized HTML in §4.2.**

| Field | Rule |
|---|---|
| `CmsAction.href` | Scheme restricted to `http`, `https`, `mailto`, `tel` or a site-relative path. `javascript:`, `data:` and `vbscript:` are rejected. |
| `cardGrid` item `href` | Same as `CmsAction.href` |
| `CmsImageRef.src` | Site-relative path or an allowlisted image host. `data:` URLs are rejected. |
| External destinations | Host checked against the allowlist; `rel="noopener noreferrer"` applied |

A block whose URL fails validation is treated as malformed: the link or image is dropped and the surrounding block still renders, or the block is skipped per §3.3. A rejected URL is never rendered as text and never emitted as an attribute.

### 3.3 Renderer behavior

`CmsPageRenderer` in `frontend/features/public-content/components/`:

1. Normalizes the payload through `normalize-cms-page.ts`.
2. Iterates blocks in order and looks up each `type` in the block registry.
3. **Unknown block type:** skip it silently in production; render a visible development-only marker in non-production. Never throw, never render raw JSON, never render unsanitized fallback markup.
4. **Malformed block of a known type:** skip that block; render the remaining blocks.
5. **Empty or missing content:** render the themed empty state within the public shell. Never fall through to Blade.
6. Always renders inside `PublicShell`; the page payload can never replace the shell.

---

## 4. Sanitized rich-HTML compatibility

Existing CMS HTML — principally `body_html` from `GET /api/public/content/cms/{slug}` — must keep working.

### 4.1 Rendering boundary

Sanitized HTML renders only inside:

```html
<article class="jp-cms-content">...</article>
```

This element is the sole location in the public frontend where `dangerouslySetInnerHTML` is permitted for CMS content. The only other permitted uses anywhere are the JSON-LD emitter in `SeoJsonLd.tsx` and the theme bootstrap script in `app/layout.tsx`, neither of which accepts CMS input.

### 4.2 Sanitization

`frontend/lib/cms/sanitize-cms-html.ts` applies an **allowlist**, not a denylist. The existing `isTrustedCmsHtml` check in `CmsPageService` — which rejects `<script`, `javascript:` and `on*=` — is a useful precondition but is not sufficient on its own and does not replace sanitization.

**Allowed elements:** `p`, `h2`–`h6`, `strong`, `em`, `b`, `i`, `u`, `s`, `sup`, `sub`, `br`, `hr`, `a`, `ul`, `ol`, `li`, `blockquote`, `figure`, `figcaption`, `img`, `table`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td`, `caption`, `code`, `pre`, `span`, `div`, `dl`, `dt`, `dd`, `abbr`.

**Allowed attributes:** `href` on `a` (scheme restricted to `http`, `https`, `mailto`, `tel`, or a site-relative path); `src`, `alt`, `width`, `height`, `loading` on `img`; `colspan`, `rowspan`, `scope` on table cells; `id` on headings for anchors; `title` on `abbr`; `lang` and `dir` globally.

**Stripped unconditionally:** `<script>`, `<style>`, `<link>`, `<meta>`, `<base>`, `<object>`, `<embed>`, `<applet>`, `<form>`, `<input>`, `<button>`, `<select>`, `<textarea>`, `<iframe>`, every `on*` event-handler attribute, every `style` attribute, every `class` attribute, `srcdoc`, `formaction`, and any `javascript:`, `data:` or `vbscript:` URL.

`<h1>` in rich HTML is demoted to `<h2>` so the page keeps exactly one `<h1>` (§9).

`rel="noopener noreferrer"` is added to external links. Sanitization runs before rendering; unsanitized HTML is never passed to React.

### 4.3 What raw CMS HTML may never control

Per architecture §9, raw CMS HTML must not control the shell, scripts, arbitrary styles, event handlers, forms, unapproved iframes, the header or footer, or the global layout.

**Iframes are not allowed by default.** If approved media embedding is required later, it must be introduced as a dedicated structured block with an explicit provider allowlist, never as raw HTML.

---

## 5. Default styling — `.jp-cms-content`

`frontend/styles/cms-content.css` gives every CMS page correct typography and spacing with no author input. It is scoped entirely under `.jp-cms-content` and must not emit any global or unscoped selector.

Styled elements: headings `h2`–`h6`, paragraphs, links, ordered and unordered lists including nesting, tables with header emphasis and horizontal overflow, blockquotes, images, figures and captions, horizontal rules, inline `code` and `pre` blocks, definition lists, and approved media wrappers.

Rules:

| Concern | Rule |
|---|---|
| Typography | Font family, size ramp, line height and measure come from design tokens. No literal `px` font sizes. |
| Vertical rhythm | Owned by `.jp-cms-content` through sibling spacing. Blocks and authors never set margins. |
| Measure | Long-form text is constrained to a readable line length; tables and figures may exceed it. |
| Color | Text, muted text, link, border and surface colors are token references only. No literal hex values. |
| Links | Visible non-color affordance plus a `:focus-visible` ring. |
| Tables | Horizontally scrollable on narrow viewports without breaking the page layout. |
| Images | `max-width: 100%`, intrinsic aspect ratio preserved, lazy loading by default. |
| Code | Wraps or scrolls; never causes horizontal page overflow. |
| First and last child | Leading and trailing margins collapsed so section padding stays exact. |

Structured blocks do **not** depend on `cms-content.css` for their layout. They use `CmsSection` and the public component set. `cms-content.css` governs prose only.

---

## 6. Page template registry

`frontend/lib/cms/page-template-registry.ts`.

### 6.1 Registered templates

| Template | Purpose |
|---|---|
| `default-content` | Generic content page. **The fallback.** |
| `hero-content` | Hero followed by content sections |
| `landing` | Marketing composition with multiple section types |
| `faq` | Question and answer disclosure layout |
| `contact` | Contact channels plus form. **`/contact` uses this template** (decision 1). |
| `policy` | Long-form legal or policy document |
| `destination` | Destination detail |
| `offer` | Offer or promotion detail |
| `article-index` | Article listing |
| `article-detail` | Single article |

**Registration is not publication (decision 11).** `destination`, `offer`, `article-index` and `article-detail` may be registered so the renderer is ready for them, but registering a template must **not** create unsupported routes and must **not** introduce fixture or placeholder content. A template becomes reachable only when authoritative content and an approved route exist for it.

### 6.2 Resolution order

Frontend-side resolution is the approved model for this rebuild (decision 10). No backend template field exists and none is added.

1. **Explicit** — if a future payload ever supplies a `template` value and it is registered, use it. This path is dormant until the deferred backend phase is separately approved.
2. **Page key** — map the fixed keys: `about` → `hero-content`, `support` → `hero-content`, `faq` → `faq`, `terms` → `policy`, `privacy` → `policy`, `booking-lookup` → `hero-content`, `group-search` → `landing`, `agent-registration` → `default-content`, `home` → `landing`. The `/contact` route resolves to `contact`.
3. **Route family** — anything served under `/legal/[slug]` resolves to `policy`. The alias map currently hard-coded in the `/legal/[slug]` page (`refund` → `refund-policy`, `cookies` → `cookie-policy`, `cancellation` → `cancellation-policy`, `booking-terms`) moves into this registry in Phase C.
4. **Slug convention** — an explicit, reviewed slug-to-template map for known content, for example destination and offer slugs.
5. **Fallback** — `default-content`.

Resolution is pure, synchronous and side-effect free. It never performs a network request and never throws.

### 6.3 Unknown template behavior

An unrecognized template value resolves to `default-content`. It must never:

- render a Blade view;
- render Master OTA or Parwaaz markup;
- render an unstyled page;
- render a blank page;
- throw an unhandled error;
- expose the unknown value to the visitor.

In non-production a development-only warning is logged. In production the substitution is silent.

**No Blade, Master or Parwaaz fallback is permitted for any unknown template, unknown block, missing page or error state.** The Laravel catch-all `GET /{slug}` renders Blade, so any CMS path that Next fails to handle risks falling through to it. Next must always resolve CMS routes itself and return its own `not-found.tsx` when content genuinely does not exist.

---

## 7. Prohibited author capabilities

| Prohibited | Enforcement |
|---|---|
| `<script>` and external script references | Stripped by the sanitizer; not expressible as a block |
| Inline event handlers (`onclick`, `onerror`, any `on*`) | Attribute allowlist |
| `style` attributes and `<style>` blocks | Attribute and element allowlist |
| `class` attributes, including Tailwind utilities | `class` is stripped; blocks accept no class input |
| Raw JSX or component references | Payloads are data only |
| `<form>` and form controls | Element allowlist; forms are shell-owned components |
| Iframes and embeds | Not allowed by default; approved media requires a dedicated block |
| Header, footer or shell replacement | Content renders only inside `PublicShell` |
| Arbitrary colors, fonts, spacing or radii | Blocks expose bounded enums; CSS uses tokens only |
| Arbitrary URL schemes | `href` and `src` schemes are allowlisted |
| Additional `<h1>` elements | Demoted to `<h2>` |
| SEO override beyond the SEO contract | Metadata comes only from the `seo` payload |

---

## 8. Responsive and dark-theme behavior

### Responsive

Every CMS template and block must be correct at desktop, tablet and mobile widths using the repository breakpoint scale — not the Mock Shell's two breakpoints. Requirements:

- No horizontal page overflow at any supported width, including wide tables, long unbroken strings, code blocks and oversized images.
- `cardGrid` and `gallery` column counts degrade predictably: 4 → 2 → 1, 3 → 2 → 1, 2 → 1.
- Hero images use responsive sources and never force a fixed height that clips text.
- Touch targets meet the minimum interactive size on mobile.
- Type ramp and section spacing scale by token, not by ad-hoc media queries inside components.

### Dark theme

- Every color is a token reference. No literal hex value in any CMS component or in `cms-content.css`.
- Both themes must satisfy the contrast requirements in §9. The brand green shifts between themes, so link, button and badge contrast must be verified in both.
- Author-supplied images may be light-only. Figures and image wrappers must render acceptably on a dark surface; do not auto-invert content images.
- Code blocks, tables, blockquotes and callout tones each need a verified dark surface treatment.
- Theme is applied through the `data-theme` attribute on the document element, matching the existing repository theme bootstrap. No flash of incorrect theme on first paint.

---

## 9. Accessibility requirements

| Requirement | Rule |
|---|---|
| Heading order | Exactly one `<h1>`, supplied by the hero or page title. Rich-HTML `<h1>` is demoted. No skipped levels. |
| Landmarks | Content renders inside `<main>`; the shell provides `<header>`, `<nav>` and `<footer>`. CMS content emits no landmark of its own except `<article>`. |
| Images | `alt` is mandatory on every block image and gallery item. Decorative images use `alt=""` explicitly. |
| Links | Descriptive text; no bare "click here". External links carry `rel="noopener noreferrer"`. Links are distinguishable by more than color. |
| Focus | Visible `:focus-visible` indicator on every interactive element. **No broad global focus suppression.** No persistent blue or cyan browser or framework glow. |
| Keyboard | FAQ disclosures, galleries and any interactive block are fully keyboard operable with correct `aria-expanded` and `aria-controls`. |
| Contrast | Text meets WCAG AA in both light and dark themes; non-text UI indicators meet the non-text contrast minimum. |
| Tables | `<th>` with `scope`; a `<caption>` where the table needs a name. Scroll containers are focusable and labelled. |
| Language | `lang` and `dir` preserved from the sanitizer allowlist for mixed-language content. |
| Motion | Any block animation respects `prefers-reduced-motion`. |
| Empty and error states | Announced accessibly; never conveyed by color or icon alone. |

---

## 10. Implementation surface (Phase C)

| Path | Responsibility |
|---|---|
| `frontend/lib/cms/block-types.ts` | `CmsBlock` union and shared refs |
| `frontend/lib/cms/page-template-registry.ts` | Registry and resolution order |
| `frontend/lib/cms/normalize-cms-page.ts` | Payload normalization for all three content shapes |
| `frontend/lib/cms/sanitize-cms-html.ts` | Allowlist sanitizer |
| `frontend/features/public-content/components/CmsPageRenderer.tsx` | Block dispatch, safe fallbacks |
| `frontend/features/public-content/components/Cms*.tsx` | Approved block components |
| `frontend/styles/cms-content.css` | `.jp-cms-content` default styling |
| `frontend/styles/tokens.css` | Token source consumed by the above |

Names may follow existing repository conventions, but the responsibilities above must remain separated.

---

## 11. Acceptance criteria

This contract is satisfied when:

1. A new CMS page with no styling instruction renders correctly in the public shell, in both themes, at all supported widths.
2. Structured blocks render only through approved components.
3. Legacy rich HTML renders only inside sanitized `.jp-cms-content`.
4. An unknown template resolves to `default-content` without visitor-visible failure.
5. An unknown or malformed block is skipped without breaking the page.
6. Scripts, inline handlers, `style`, `class`, forms and iframes are demonstrably stripped.
7. No CMS route can fall through to Blade, Master OTA or Parwaaz output.
8. Every CMS page passes the accessibility checks in §9.
9. `cms-content.css` emits no global or unscoped selector.
10. No literal color, font or spacing value appears in any CMS component or in `cms-content.css`.
11. `CmsAction.href`, card links and image sources are validated against the same URL and host allowlist as sanitized HTML, and a rejected URL is never rendered.
12. Registered `destination`, `offer` and `article` templates create no unsupported route and no fixture content.
13. No database template column is added; template resolution is entirely frontend-side.
