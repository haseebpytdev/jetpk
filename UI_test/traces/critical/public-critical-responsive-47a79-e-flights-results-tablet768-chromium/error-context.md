# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: public-critical-responsive.spec.ts >> guest public critical responsive >> flights-results @ tablet768
- Location: tests\visual\public-critical-responsive.spec.ts:26:7

# Error details

```
Test timeout of 45000ms exceeded.
```

```
Error: flights-results navigation failed within 25000ms (page.goto: Timeout 12489ms exceeded.
Call log:
  - navigating to "http://127.0.0.1:8000/flights/results?from=LHE&to=DXB&depart=2026-08-16&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0", waiting until "commit"
)
```

# Test source

```ts
  1  | import type { Page, Response } from '@playwright/test';
  2  | 
  3  | /** Total budget per navigation (goto + shell); aligned with Playwright navigationTimeout in public configs. */
  4  | export const PUBLIC_NAV_TIMEOUT_MS = 25_000;
  5  | 
  6  | const SEARCH_SHELL_KEYS = new Set(['home', 'flights-search']);
  7  | 
  8  | export function publicPageShellLocator(page: Page, pageKey: string) {
  9  |   if (SEARCH_SHELL_KEYS.has(pageKey)) {
  10 |     return page
  11 |       .locator('[data-hero-search], [data-jp-search], [data-flight-search-form], main, .ota-main-nav')
  12 |       .first();
  13 |   }
  14 |   if (pageKey === 'flights-results') {
  15 |     return page
  16 |       .locator(
  17 |         '.ota-results-pro, [data-results-root], .ota-mobile-results, [data-testid="ota-mobile-results"], [data-hero-search], main',
  18 |       )
  19 |       .first();
  20 |   }
  21 |   return page.locator('main, [data-hero-search], form, .ota-main-nav').first();
  22 | }
  23 | 
  24 | function formatNavError(err: unknown): string {
  25 |   if (err instanceof Error) {
  26 |     return err.message;
  27 |   }
  28 |   return String(err);
  29 | }
  30 | 
  31 | /**
  32 |  * Fast public navigation: commit (not domcontentloaded), shell wait, one retry.
  33 |  * Layout assertions run after this; broken UI still fails checks.
  34 |  */
  35 | export async function gotoPublicPage(
  36 |   page: Page,
  37 |   path: string,
  38 |   pageKey: string,
  39 |   options?: { timeoutMs?: number },
  40 | ): Promise<Response | null> {
  41 |   const budget = options?.timeoutMs ?? PUBLIC_NAV_TIMEOUT_MS;
  42 |   const started = Date.now();
  43 |   const shell = publicPageShellLocator(page, pageKey);
  44 |   let lastError: unknown;
  45 | 
  46 |   for (let attempt = 0; attempt < 2; attempt += 1) {
  47 |     const elapsed = Date.now() - started;
  48 |     const remaining = budget - elapsed;
  49 |     if (remaining < 2_500) {
  50 |       break;
  51 |     }
  52 | 
  53 |     const gotoTimeout = Math.min(Math.floor(budget / 2), remaining);
  54 |     let response: Response | null = null;
  55 | 
  56 |     try {
  57 |       response = await page.goto(path, {
  58 |         waitUntil: 'commit',
  59 |         timeout: gotoTimeout,
  60 |       });
  61 |     } catch (err) {
  62 |       lastError = err;
  63 |       if (attempt === 0) {
  64 |         continue;
  65 |       }
  66 |       break;
  67 |     }
  68 | 
  69 |     const shellTimeout = Math.max(3_000, budget - (Date.now() - started));
  70 |     try {
  71 |       await shell.waitFor({ state: 'attached', timeout: shellTimeout });
  72 |       if (SEARCH_SHELL_KEYS.has(pageKey)) {
  73 |         await page
  74 |           .locator('[data-hero-search]')
  75 |           .first()
  76 |           .waitFor({ state: 'visible', timeout: Math.min(8_000, shellTimeout) });
  77 |       } else if (pageKey === 'flights-results') {
  78 |         await page
  79 |           .locator(
  80 |             '.ota-results-pro, [data-results-root], .ota-mobile-results, [data-testid="ota-mobile-results"], main',
  81 |           )
  82 |           .first()
  83 |           .waitFor({ state: 'visible', timeout: Math.min(12_000, shellTimeout) });
  84 |       }
  85 |       return response;
  86 |     } catch (err) {
  87 |       lastError = err;
  88 |       if (attempt === 0) {
  89 |         continue;
  90 |       }
  91 |     }
  92 |   }
  93 | 
> 94 |   throw new Error(
     |         ^ Error: flights-results navigation failed within 25000ms (page.goto: Timeout 12489ms exceeded.
  95 |     `${pageKey} navigation failed within ${budget}ms (${formatNavError(lastError)})`,
  96 |   );
  97 | }
  98 | 
```