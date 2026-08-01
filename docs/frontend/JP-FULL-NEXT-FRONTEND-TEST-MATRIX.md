# JP-FULL-NEXT-FRONTEND-TEST-MATRIX

Phase: **JP-FULL-NEXT-FRONTEND-01C**  
Branch: `phase/jetpk-full-next-frontend-ui-integration`  
Executed: 2026-08-01

## Build gates

| Command | Result |
|---|---|
| `npm run typecheck` | **PASS** |
| `npm run lint` | **PASS** (0 warnings) |
| `npm run build` | **PASS** (67 `page.tsx` routes) |

## Route smoke (`tests/jp-full-next-frontend-routes.spec.ts`)

Config: `playwright.jp-full-next-frontend.config.ts` (port 3012)

| Metric | Value |
|---|---:|
| Executed | 4 |
| Passed | 4 |
| Failed | 0 |
| Skipped | 0 |

Routes covered: `/verify-email` (notice + verified), `/flights/fare-selection` (missing context + mocked success load).

## Integration-critical Playwright (01C)

| Suite | Executed | Passed | Failed | Skipped |
|---|---:|---:|---:|---:|
| `jp-full-next-frontend/*` (excl. visual capture) | 119 | 119 | 0 | 0 |
| `public-content.spec.ts` | 14 | 14 | 0 | 0 |
| `jp-ui-05a-customer-ownership.spec.ts` | 4 | 4 | 0 | 0 |
| `jp-ui-05a-agent-rbac.spec.ts` | 5 | 5 | 0 | 0 |
| **01C functional subtotal** | **142** | **142** | **0** | **0** |

### New in 01C

| Spec | Tests | Focus |
|---|---:|---|
| `responsive-safety.spec.ts` | 27 | 9 representative pages × 3 viewports, no horizontal overflow |
| `dark-theme-safety.spec.ts` | 7 | Readable dark theme on public + portal pages |

### Existing integration suites (01B)

| Spec | Tests |
|---|---:|
| `route-matrix.spec.ts` | 38 |
| `fare-selection.spec.ts` | 6 |
| `verify-email.spec.ts` | 8 |
| `cms-bridge.spec.ts` | 5 |
| `navigation-indexing.spec.ts` | 5 |
| `leakage.spec.ts` | 8 |
| `accessibility.spec.ts` | 5 |

## Commands

```bash
cd frontend
npm run typecheck && npm run lint && npm run build
npx playwright test tests/jp-full-next-frontend-routes.spec.ts -c playwright.jp-full-next-frontend.config.ts
npx playwright test -c playwright.jp-full-next-frontend.config.ts --grep-invert "capture "
npx playwright test tests/public-content.spec.ts tests/jp-ui-05a-customer-ownership.spec.ts tests/jp-ui-05a-agent-rbac.spec.ts -c playwright.config.ts
```

## Not run

| Suite | Reason |
|---|---|
| Broad Laravel PHPUnit | No Laravel runtime files modified |
| Visual capture (72) | Baseline accepted; not re-run in 01C |
