# Authority-06 R3 — destination shell dependency audit

**Production SHA:** `8c50fc61967e51c5b576375b55ae7701d122c121`  
**Scope:** What client work can block route commit / destination visible / interactive after soft-nav.

| Component | Runs on nav | BLOCKS_ROUTE_COMMIT | BLOCKS_DESTINATION_VISIBLE | BLOCKS_DESTINATION_INTERACTIVE | Notes |
|-----------|-------------|---------------------|----------------------------|--------------------------------|-------|
| **PublicShell** | Every public route (client layout) | **NO** (does not gate router) | **NO** | **NO** | Post-hydration `fetchSessionBootstrap` + `PublicConfigService.getConfig()` update React state; competes for main thread / connection but does not block Next router transition directly. |
| **GuestAuthRedirect** | `/login`, `/register` only | **NO** | **NO** | **NO** | `useEffect` session check → `router.replace` only if authenticated; shares deduped `browserBootstrapInflight` with PublicShell. Does not block anonymous login form render. |
| **AuthCsrfBootstrap** | Auth routes via layout | **NO** | **NO** | **PARTIAL** | `ensureLaravelCsrfToken()` on mount; `useAuthSubmissionReady` disables submit until ready — **blocks interactive submit**, not route commit or password field visibility. |
| **PublicConfigService** (browser) | PublicShell + some islands | **NO** | **NO** | **NO** | `cache: no-store` fetch; branding/AI flag updates after paint. |
| **session-service bootstrap** | PublicShell + GuestAuthRedirect | **NO** | **NO** | **NO** | Deduped inflight promise; anonymous `{authenticated:false}` fast path still requires network unless intercepted. |
| **ThemeSwitch / theme state** | Header | **NO** | **NO** | **NO** | Local preference; no nav gate. |
| **Company branding state** | PublicShell setState | **NO** | **NO** | **NO** | Logo height/url may reflow header after config returns. |
| **PublicFloatingActionDock / FAB** | Mobile | **NO** | **NO** | **NO** | Independent of destination route commit. |
| **PublicRoutePrefetch** | PublicShell child | **NO** | **NO** | **NO** | Idle queue @2500ms may overlap nav window (network contention only). |
| **LoginPageClient** | `/login` | **NO** | **NO** | **PARTIAL** | Commerce gates / loading shell may delay form; password field in `LoginForm` once client hydrates. |
| **Support page RSC** | `/support` | **NO** (server static) | **NO** | **PARTIAL** | `SupportContactIsland` client hydration for form fields; heading/FAQ server HTML visible first (`#support-form-heading` is static). |

## Deterministic destination markers (cert harness)

| Route | Root visible | Interactive |
|-------|--------------|-------------|
| home → login | `[data-testid="login-form"]` | `input[type="password"]:not([disabled])` |
| home → support | `#support-page-heading` | `#support-form-heading` (static); form island hydrates later |

## Inference rule

Presence of session/config **requests** alone does not prove blocking. R3 A/B intercept cohorts + trace intervals are required to prove **contention**.
