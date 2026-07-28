# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: public-critical-responsive.spec.ts >> guest public critical responsive >> flights-results @ mobile360
- Location: tests\visual\public-critical-responsive.spec.ts:26:7

# Error details

```
Error: flights-results navigation failed within 25000ms (page.goto: Timeout 12177ms exceeded.
Call log:
  - navigating to "http://127.0.0.1:8000/flights/results?from=LHE&to=DXB&depart=2026-08-16&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0", waiting until "commit"
)
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - generic [ref=e3]:
      - link "JetPakistan" [ref=e4] [cursor=pointer]:
        - /url: /
        - img "JetPakistan" [ref=e5]
      - generic [ref=e6]:
        - button "Register" [ref=e8] [cursor=pointer]:
          - text: Register
          - img [ref=e9]
        - button "Switch day or night theme" [ref=e11] [cursor=pointer]:
          - img [ref=e13]
        - button "Open menu" [ref=e16] [cursor=pointer]:
          - img [ref=e17]
  - main [ref=e19]:
    - generic [ref=e20]:
      - generic "Available flights" [ref=e21]:
        - generic [ref=e24]:
          - paragraph [ref=e25]:
            - generic [ref=e26]: 
            - text: Flight results
          - heading "Available flights" [level=1] [ref=e27]
          - paragraph [ref=e28]:
            - generic [ref=e29]: LHE → DXB · Sunday, Aug 16, 2026
            - generic [ref=e30]: Fares in PKR
      - generic [ref=e31]:
        - search [ref=e33]:
          - generic [ref=e34]:
            - tablist "Search product" [ref=e35]:
              - tab "Flights" [selected] [ref=e37] [cursor=pointer]:
                - img [ref=e38]
                - text: Flights
            - tablist "Trip type" [ref=e40]:
              - tab "Return" [ref=e42] [cursor=pointer]
              - tab "One-way" [selected] [ref=e43] [cursor=pointer]
              - tab "Multi-city" [ref=e44] [cursor=pointer]
          - generic [ref=e47]:
            - generic [ref=e48]:
              - generic [ref=e49]:
                - generic [ref=e50]: From
                - generic [ref=e51]:
                  - img [ref=e52]
                  - combobox "From" [ref=e55] [cursor=pointer]
              - button "Swap origin and destination" [ref=e57] [cursor=pointer]:
                - img [ref=e58]
              - generic [ref=e60]:
                - generic [ref=e61]: To
                - generic [ref=e62]:
                  - img [ref=e63]
                  - combobox "To" [ref=e66] [cursor=pointer]
              - generic [ref=e67]:
                - generic [ref=e68]: Departure
                - generic [ref=e69]:
                  - img [ref=e70]
                  - button "Departure" [ref=e73] [cursor=pointer]:
                    - generic [ref=e74]: 16 Aug
              - generic [ref=e76]:
                - generic [ref=e77]: Travellers
                - generic [ref=e78]:
                  - img [ref=e79]
                  - button "Travellers" [ref=e81] [cursor=pointer]:
                    - generic [ref=e82]: 1 adult · Economy
            - generic [ref=e83]:
              - generic [ref=e84]:
                - generic [ref=e85] [cursor=pointer]:
                  - img [ref=e87]
                  - generic [ref=e89]: Direct flights only
                - generic [ref=e90] [cursor=pointer]:
                  - img [ref=e92]
                  - generic [ref=e94]: Include nearby airports
                - generic [ref=e95] [cursor=pointer]:
                  - img [ref=e97]
                  - generic [ref=e99]: Flexible dates ±1 day
              - generic [ref=e101]:
                - text: Search
                - button "Search" [ref=e102] [cursor=pointer]:
                  - img [ref=e103]
                  - generic [ref=e106]: Search
        - generic [ref=e107]:
          - complementary [ref=e108]:
            - generic [ref=e109]:
              - button "Open sort and filters" [ref=e110] [cursor=pointer]: Sort & filters
              - button "Filter results 0" [ref=e111] [cursor=pointer]:
                - text: Filter results
                - generic [ref=e112]: "0"
          - generic [ref=e113]:
            - paragraph [ref=e114]: Showing fares...
            - generic [ref=e115]:
              - article [ref=e116]
              - article [ref=e122]
              - article [ref=e128]
              - article [ref=e134]
            - button "Load more" [disabled] [ref=e141] [cursor=pointer]
            - paragraph [ref=e142]:
              - link "Back to flight search" [ref=e143] [cursor=pointer]:
                - /url: /#jp-flight-search
              - text: ·
              - link "Home" [ref=e144] [cursor=pointer]:
                - /url: /
  - contentinfo [ref=e145]:
    - generic [ref=e146]:
      - generic [ref=e147]:
        - generic [ref=e148]:
          - link "JetPakistan" [ref=e149] [cursor=pointer]:
            - /url: /
            - img "JetPakistan" [ref=e150]
          - paragraph [ref=e151]: We help you plan and book flights with dedicated support.
          - generic [ref=e152]:
            - generic [ref=e153]:
              - img [ref=e154]
              - text: IATA
            - generic [ref=e156]:
              - img [ref=e157]
              - text: PCAA
            - generic [ref=e159]:
              - img [ref=e160]
              - text: PCI-DSS
        - generic [ref=e163]:
          - heading "Company" [level=4] [ref=e164]
          - link "About us" [ref=e165] [cursor=pointer]:
            - /url: /about-us
          - link "Contact" [ref=e166] [cursor=pointer]:
            - /url: /support
        - generic [ref=e167]:
          - heading "Policies" [level=4] [ref=e168]
          - link "Terms" [ref=e169] [cursor=pointer]:
            - /url: /terms
          - link "Privacy" [ref=e170] [cursor=pointer]:
            - /url: /privacy
        - generic [ref=e171]:
          - heading "Support" [level=4] [ref=e172]
          - link "Help centre" [ref=e173] [cursor=pointer]:
            - /url: /faq
          - link "Manage booking" [ref=e174] [cursor=pointer]:
            - /url: /lookup-booking
        - generic [ref=e175]:
          - heading "B2B & agents" [level=4] [ref=e176]
          - link "Become an agent" [ref=e177] [cursor=pointer]:
            - /url: /agent/register
      - generic [ref=e178]:
        - paragraph [ref=e179]: © 2026 JetPakistan. All rights reserved.
        - link "Contact support" [ref=e181] [cursor=pointer]:
          - /url: /support
          - img [ref=e182]
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
     |         ^ Error: flights-results navigation failed within 25000ms (page.goto: Timeout 12177ms exceeded.
  95 |     `${pageKey} navigation failed within ${budget}ms (${formatNavError(lastError)})`,
  96 |   );
  97 | }
  98 | 
```