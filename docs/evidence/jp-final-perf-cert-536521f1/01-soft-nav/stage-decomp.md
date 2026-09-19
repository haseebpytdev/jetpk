# Soft-nav stage decomposition — 536521f1 / mHk-585jVMAtC6DsijCuS

**Mode:** read-only diagnostic (N≤2 per route). **Not** a recert.
**Source:** [Stage-decompose soft-nav fails](f7d75cc6-23b5-4798-bd77-323aa9de9859) (Ask mode could not write this file; parent saved).
**Method:** warm home load → 200ms settle (no idle prefetch wait) → footer Link soft click.
**Cert gate reference:** `RESULTS.md` N=20 APP_P95.

## Ranked dominant segment (FAIL APP = click → URL)

1. **Destination RSC completion under concurrent `_rsc` prefetch contention** (often multi-second `RSC_RESPONSE_END`)
2. **Cold / variable origin RSC TTFB** when Node/OLS busy
3. **Prefetch MISS** (deferred about/support; priority privacy often still in-flight at early click)
4. Post-commit client session + public-config (secondary; not APP click→URL)
5. Client CMS page fetches: **none** (CMS embedded in server RSC)

## Source dynamic opt-in

| Route | `force-dynamic` | `cookies()`/headers in page | `revalidate` | Prefetch tier (at 536521f1) |
|-------|-----------------|-----------------------------|--------------|-------------------------------|
| `/about-us` | no | no | 300 | deferred idle (+800ms→ric) |
| `/privacy` | no | no | 300 | priority |
| `/support` | no | no | 300 | deferred idle |
| `/faq`,`/terms` | no | no | 300 | priority (PASS) |
| `/login` | **yes** (`(auth)/layout`) | yes | n/a | priority but dynamic |

Also: `SiteFooter`/`SiteHeader` force `prefetch` on Links → parallel stampede with `PublicRoutePrefetch`.
HTTP Cache-Control observed: always `private, no-store` for document+RSC despite ISR exports.

## Per-route decomp (diag)

### home_about
| Sample | PREFETCH | wall URL | RSC_TTFB | RSC_RESPONSE_END | PUBLIC_CONFIG# | CMS_PAGE# | DUP |
|--------|----------|----------|----------|------------------|----------------|-----------|-----|
| 0 | HIT | 858 | (cached; no nav RSC) | — | 1 | 0 | 0 |
| 1 | MISS | **4112** | ~319 | **~3824** | 1 | 0 | 0 |

### home_privacy
| Sample | PREFETCH | wall URL | RSC_TTFB | RSC_RESPONSE_END | PUBLIC_CONFIG# | CMS_PAGE# |
|--------|----------|----------|----------|------------------|----------------|-----------|
| 0 | MISS | **3200** | **~2099** | **~4142** | 2* | 0 |
| 1 | MISS→fast | 964 | ~208 | ~715 | 1 | 0 |

### home_support
| Sample | PREFETCH | wall URL | RSC_TTFB | RSC_RESPONSE_END | PUBLIC_CONFIG# | CMS_PAGE# |
|--------|----------|----------|----------|------------------|----------------|-----------|
| 0 | MISS | 1588 | ~211 | ~474 (queued ~1405 wall) | 1 | 0 |
| 1 | MISS | 1914 | ~200 | ~907 | 2* | 0 |

## Why faq/terms PASS but privacy/about FAIL

- faq/terms: priority + often complete before click in N=20 mix → P95≤1500.
- privacy: priority but early-click / contention tail retained (P95 3396).
- about/support: deferred → systematic MISS on early clicks; about worst P95 5244.
- Not CMS client waterfall.

## Smallest safe remediations (no harness gaming; CMS authoritative)

1. Remove blanket Link `prefetch` on footer/header; single-flight `PublicRoutePrefetch` only.
2. Move `/about-us` + `/support` to priority (or pre-idle) queue.
3. Drop `/contact` from prefetch list (308→about-us).
4. Keep cert “no artificial prefetch wait”.
5. Separately: auth soft-nav / restore real HTTP/ISR cache if OLS strips; disk pressure cleanup.

## Follow-up status (parent, post-diag)

- [x] Promote about/support (+ groups) via staggered waves (`08602d4a`→`ab667f78`)
- [x] `fetchSupportCategories` / site-contact `cache()`+timeout inside cache
- [x] Auth layout: remove `force-dynamic`; login/register no server `cookies()` (`bd12ac76`)
- [x] Auth/support `loading.tsx` (`a895eaea`)
- [x] Disk free 95%→64%
- [ ] Footer/header Link stampede → single-flight ownership (next)
- [ ] Drop `/contact` from prefetch if still present
- [ ] Recert after stampede fix

Live cert after partial remediations (`a895eaea`): soft-nav still **FAIL** 6/10 (worst home_support 3192).
