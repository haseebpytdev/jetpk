# Next.js Integration Roadmap — DASH-08-09

## Purpose

Prepare a portable CMS contract consumable by the dashboard preview today and a future JetPakistan public Next.js frontend.

## Current dashboard CMS contract

Typed pages, sections, assets, validation — fixture-backed in `dashboard/mocks/cms-fixtures.ts`.

## Future API boundary

```
GET /api/v1/cms/pages/{slug} → CmsPage + CmsSectionInstance[]
```

No Blade, Laravel route names, or PHP serializers in the contract.

## Future Next.js page renderer

1. Fetch typed page data
2. Iterate ordered sections
3. Resolve `frontendComponentKey` against trusted registry
4. Validate fields server-side
5. Apply design tokens and responsive assets

## Trusted component registry

Mirror of `CMS_SECTION_REGISTRY` as React components under `components/cms/`.

## Section ordering

`page.sectionIds` determines render order.

## Design tokens

Map `themeMode`, `themeTreatment`, `contentWidth`, `spacing` to Tailwind/token classes.

## Responsive assets

Select desktop/mobile/day/night variant from `CmsAsset` metadata.

## Preview parity strategy

Dashboard preview modes approximate public layout; not pixel-perfect in Prompt 01.

## Server-rendering

Components must be RSC-compatible; no `dangerouslySetInnerHTML`.

## Caching / revalidation

ISR per page slug; on-demand revalidation after CMS publish.

## Localization readiness

`CmsLocale` field on pages and FAQ items.

## Safe rich-content strategy

Structured text blocks; restricted markdown or portable JSON — no arbitrary HTML.

## Operational logic separation

Search, booking, payments, supplier calls remain outside CMS.

## Migration sequence

1. Prompt 01 foundation (complete)
2. Prompt 02 Reports UI (complete)
3. Prompt 03 CMS UI (complete — dashboard preview surfaces)
4. API layer + public Next.js app (future phase)
5. Gradual homepage section migration

## Prompt 03 CMS readiness

- Six dashboard routes implemented with shared workspace, filters, drawers, and preview shell
- `buildCmsModule()` produces typed `CmsModuleResult` from fixtures
- Section registry drives list filters, validation, and preview component selection
- Local preview editing is ephemeral (component state only; refresh restores fixtures)
- Playwright coverage: `cms-*.smoke.spec.ts` (overview, pages, sections, banners, notices, assets)

## Testing strategy

Contract tests, registry uniqueness, link safety, Playwright route shells, and CMS module smoke specs. Final phase gate (DASH-08-09 Prompt 04): foundation spec (36), Reports specs (83), CMS specs (132), critical regression (21), full suite **527** tests with `retries=0`.

### Optimized Playwright strategy

**During implementation**

- Run affected specs and directly related helpers after each change
- Run `npm run typecheck` and `npm run lint` on touched modules
- Avoid repeating the full 500+ test suite on every edit

**After a module**

- Run new or changed specs once
- Use `--repeat-each=2` only for new or high-risk flows (navigation, URL state, drawers, sorting, preview)
- Use `--repeat-each=3` only for previously flaky tests or after shared synchronization helper changes

**Before phase completion**

- Run foundation spec, domain specs (Reports, CMS), critical regression, then the full suite **once**
- Full suite is reserved for final phase gate, shared infrastructure changes, dependency upgrades, merge readiness, and deployment/release readiness

**Retries remain 0** — the strategy is optimized, not weakened. Complete testing is required at phase boundaries; intermediate runs target only affected areas.

## Non-goals for DASH-08-09

- Second Next.js public app
- Production upload/CDN
- WYSIWYG HTML editor
- Live CMS persistence
- Brand switching / multi-tenant UI

---

## DASH-11 — Laravel read-only dashboard integration

### Prompt 02 (current)

| Area | Status |
|------|--------|
| Laravel GET `/api/dashboard/*` | ✅ Session, overview, bookings, payments, customers |
| Next.js adapters | ✅ `laravel-client` + module services |
| Auth shell | ✅ Session summary in header/sidebar |
| Data-source switching | ✅ Explicit fixture / laravelReadOnly / unavailable |
| No silent fallback | ✅ Enforced in `createReadOnlyService` |
| Tests | ✅ Targeted Laravel + Next.js read-only specs |

### Prompt 01 baseline

Architecture + contracts + visual audit baseline.

| Area | Prompt 01 | Prompt 02+ |
|------|-----------|------------|
| Read-only API contracts | ✅ Documented | ✅ Laravel implementation (Prompt 02 modules) |
| Response/error envelopes | ✅ Typed | ✅ Wired to live APIs |
| Data-source modes | ✅ | ✅ Live shell for core modules |
| Visual system doc | ✅ | Maintained during integration |

**Explicitly prohibited in DASH-11:** mutation APIs, login UI changes, silent fixture fallback, credential exposure.

---

## Public frontend planning (JP-FE-01+)

1. **Blade frontend remains in maintenance mode** — only critical fixes (broken flows, 500s, security, branding leaks, severe mobile issues).
2. **No comprehensive Blade refactor.**
3. Valid audit findings become **Next.js acceptance criteria**.
4. Future public frontend begins with **JP-FE-01** — architecture, design system, contracts, route map, CMS registry.
5. **Laravel/Sabre hardened logic remains authoritative** — no business logic duplication in Next.js.
6. Every public frontend phase requires visual QA at: **1440, 1280, 1024, 768, 430, 390, 360**.
