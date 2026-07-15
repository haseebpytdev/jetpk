# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: dashboard-smoke.spec.ts >> JetPK dashboard smoke (unauthenticated safe routes) >> 404 error shell is single chrome
- Location: tests\playwright\jetpk-9h\dashboard-smoke.spec.ts:36:3

# Error details

```
Error: page.goto: net::ERR_CONNECTION_REFUSED at http://127.0.0.1:8000/this-route-should-not-exist-jetpk-9h
Call log:
  - navigating to "http://127.0.0.1:8000/this-route-should-not-exist-jetpk-9h", waiting until "load"

```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import fs from 'node:fs';
  3  | import path from 'node:path';
  4  | 
  5  | const auditDir = path.join('storage', 'app', 'audits', 'jetpk-9h');
  6  | const screenshotDir = path.join('tests', 'playwright', 'artifacts', 'jetpk-9h', 'screenshots');
  7  | 
  8  | test.beforeAll(() => {
  9  |   fs.mkdirSync(auditDir, { recursive: true });
  10 |   fs.mkdirSync(screenshotDir, { recursive: true });
  11 | });
  12 | 
  13 | test.describe('JetPK dashboard smoke (unauthenticated safe routes)', () => {
  14 |   test('home and login render without console errors', async ({ page }, testInfo) => {
  15 |     const consoleErrors: string[] = [];
  16 |     page.on('console', (msg) => {
  17 |       if (msg.type() === 'error') consoleErrors.push(msg.text());
  18 |     });
  19 | 
  20 |     await page.goto('/');
  21 |     await expect(page).toHaveTitle(/JetPakistan|Jet/i);
  22 |     await page.screenshot({ path: path.join(screenshotDir, `${testInfo.project.name}-home.png`), fullPage: true });
  23 | 
  24 |     await page.goto('/login');
  25 |     await expect(page.locator('body')).toBeVisible();
  26 |     await page.screenshot({ path: path.join(screenshotDir, `${testInfo.project.name}-login.png`), fullPage: true });
  27 | 
  28 |     fs.writeFileSync(
  29 |       path.join(auditDir, `console-${testInfo.project.name}.json`),
  30 |       JSON.stringify({ project: testInfo.project.name, consoleErrors }, null, 2),
  31 |     );
  32 | 
  33 |     expect(consoleErrors.filter((e) => !e.includes('favicon'))).toEqual([]);
  34 |   });
  35 | 
  36 |   test('404 error shell is single chrome', async ({ page }, testInfo) => {
> 37 |     await page.goto('/this-route-should-not-exist-jetpk-9h');
     |                ^ Error: page.goto: net::ERR_CONNECTION_REFUSED at http://127.0.0.1:8000/this-route-should-not-exist-jetpk-9h
  38 |     const headers = await page.locator('header').count();
  39 |     const footers = await page.locator('footer').count();
  40 |     const panels = await page.locator('.jp-error-panel').count();
  41 |     await page.screenshot({ path: path.join(screenshotDir, `${testInfo.project.name}-404.png`), fullPage: true });
  42 | 
  43 |     expect(headers).toBeLessThanOrEqual(1);
  44 |     expect(footers).toBeLessThanOrEqual(1);
  45 |     expect(panels).toBeLessThanOrEqual(1);
  46 |   });
  47 | });
  48 | 
```