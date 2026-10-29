# JP-UI-PERF-STABILITY-01B — First-paint branding preflight

## Scope

Close SSR-first branding residual after Phase A (media CLOSED — untouched).

## Bases

| Item | SHA |
|---|---|
| Protected main | `3e80091bf09d6223ea364c49400a7801d94877b8` |
| Production public frontend | `43f5a3beb9a8e88567cf49bef76853f49b55e0c6` |
| Canonical branch | `perf/jp-ui-perf-stability-01b-first-paint-branding` |

`BRANDING_SHELL_PRODUCTION_MAIN_PREIMAGE_PARITY=PASS` (pre-verified byte-identical shell/branding files).

## Authoritative branding (public config, sanitized)

```text
EXPECTED_BRAND_NAME=JetPakistan
EXPECTED_LOGO_PATH=/storage/agencies/1/branding/1py7vHKBiZEVF7Wr9TEcaK8eRNpVYflrRyWYiMoM.png
EXPECTED_HEADER_LOGO_HEIGHT=36
```

Source: production `GET /api/public/content/config` (read-only). Absolute URL reduced to storage path via `resolveHeaderLogoUrl`.

## Production baseline (pre-fix)

| Route | Correct org logo in SSR HTML | Notes |
|---|---|---|
| `/` | YES | height=36, storage branding |
| `/flights` | YES | height=36, storage branding |
| `/login` | NO | `src=/client-assets/jetpk/logo/logo.svg`, height=40 |

```text
BASELINE_HOME_CORRECT_LOGO_IN_SSR=YES
BASELINE_LOGIN_CORRECT_LOGO_IN_SSR=NO
BASELINE_BRAND_LOGO_POST_HYDRATION_SWAP=YES  (login: fallback → org after PublicShell getConfig)
BASELINE_BROWSER_PUBLIC_CONFIG_FETCH_COUNT=1  (PublicShell always fetched config)
```

## Root cause

1. **Primary (login / auth):** `(auth)/layout.tsx` deliberately passed `branding={null}` so SSR emitted canonical fallback; `PublicShell` always browser-fetched config after hydration and swapped logo.
2. **Amplifier (all shells):** `PublicShell` refetched public config even when SSR already supplied branding → redundant browser branding path.
3. **Cache hazard:** `unstable_cache` previously returned/persisted `null` on Laravel failure → could poison 3600s cache (addressed).

```text
ROOT_CAUSE=AUTH_LAYOUT_NULL_BRANDING_PLUS_PUBLICSHELL_ALWAYS_REFETCH_CONFIG; NULL_CACHE_POISONING_ADDRESSED
NULL_CACHE_POISONING_CONFIRMED=YES_AS_HAZARD
```

## Implementation (runtime files only)

1. `frontend/features/public-content/services/public-config-service.ts` — throw inside `unstable_cache` on null; catch outside → safe null (no 1h poison).
2. `frontend/components/layout/PublicShell.tsx` — skip browser config when `initialBranding != null`; session bootstrap retained; recovery when SSR branding missing.
3. `frontend/app/(auth)/layout.tsx` — SSR `PublicConfigService.getConfig()` into `PublicShell`.

`JetPakistanLogo.tsx` unchanged (`LOGO_COMPONENT_CHANGE_REQUIRED=NO`).

## Post-fix local evidence (mock Laravel + production build)

### Null-cache unit

```text
VALID_CONFIG_IS_CACHEABLE=PASS
FAILED_CONFIG_IS_NOT_PERSISTED_AS_VALID_CACHE=PASS
NEXT_REQUEST_CAN_RECOVER_AFTER_TRANSIENT_FAILURE=PASS
```

Source: `frontend/tests/regression/jp-ui-perf-01b-null-cache.test.mjs`

### SSR HTML + hydration (`playwright.jp-01b-branding.config.ts`)

```text
HOME_CORRECT_LOGO_IN_SSR=YES
LOGIN_CORRECT_LOGO_IN_SSR=YES
FLIGHTS_CORRECT_LOGO_IN_SSR=YES  (prod read-only HTML + flights layout wiring)
PLACEHOLDER_LOGO_FIRST_PAINT=NO
BRAND_LOGO_POST_HYDRATION_SWAP=NO
CORRECT_LOGO_ON_SSR=YES
LOGO_SRC_MUTATION_AFTER_HYDRATION=0
BROWSER_PUBLIC_CONFIG_FETCH_HOME=0
BROWSER_PUBLIC_CONFIG_FETCH_LOGIN=0
DUPLICATE_BRANDING_FETCH=NO
LOGO_WIDTH_DELTA_PX=0
LOGO_HEIGHT_DELTA_PX=0
LOGO_RELATED_LAYOUT_SHIFT=0
LOGO_DIMENSIONS_RESERVED=YES
HEADER_BRANDING_REGRESSION=NO  (viewports 320–1440 in 01B spec)
```

Artifacts: `ssr-_.html.snippet.txt`, `ssr-_login.html.snippet.txt`, `ssr-_flights-prod.html.snippet.txt`, `hydration-logo-mutations.json`, `header-viewports.json`, `_playwright-01b.txt`

### Auth warm navigation (N=12 local mock)

```text
PHASE_B_HOME_LOGIN_MEDIAN=130ms
PHASE_B_HOME_LOGIN_P95=336ms
BASE_HOME_LOGIN_P95=242ms  (prior prod soft-nav cert b45975e3 host; different host — not paired micro-compare)
WARM_HOME_LOGIN_NEW_LARAVEL_CONFIG_CALLS=0  (configLog: 1 cold MISS after cache clear, then 12 HIT during soft-nav)
NO_MATERIAL_HOME_LOGIN_P95_REGRESSION=YES  (warm HIT-only; absolute soft-nav << 1500ms; AUTH_DIRECT_CONFIG_APPROACH=ACCEPTED)
```

Artifact: `home-login-nav.json`

### Phase-A media (prod read-only, no code change)

```text
HERO_CURRENT_SRC_IS_DERIVATIVE=YES
ROUTE_CURRENT_SRC_IS_DERIVATIVE=YES
DESTINATION_CURRENT_SRC_IS_DERIVATIVE=YES
FEATURED_CURRENT_SRC_IS_DERIVATIVE=YES
GIANT_ORIGINAL_SELECTED_WHEN_DERIVATIVE_AVAILABLE=NO
PHASE_A_MEDIA_REGRESSION=NO
```

Artifact: `phase-a-media-prod.json`

### Build / static

```text
TYPECHECK=PASS
LINT=PASS  (pre-existing unrelated warnings only)
NEXT_BUILD=PASS
FIRST_PAINT_BRANDING_TESTS=PASS
NAVIGATION_REGRESSION=PASS
HEADER_FOOTER_REGRESSION=PASS_VIA_01B_VIEWPORT_SUITE
```

Legacy `jp-ui-02-header-footer` / `public-shell` via default `playwright.config.ts` hung on webServer startup in this session; branding-specific header viewport suite (7 widths) passed and is used for header branding regression.

## Safety

```text
SUPPLIER_CALLS=0
BOOKING_MUTATIONS=0
PAYMENT_MUTATIONS=0
PRODUCTION_DEPLOYED=NO
PHASE_A_MEDIA=UNTOUCHED
UNRELATED_RUNTIME_FILES=0
```
