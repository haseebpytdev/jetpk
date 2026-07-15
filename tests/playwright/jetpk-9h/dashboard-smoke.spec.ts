import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const auditDir = path.join('storage', 'app', 'audits', 'jetpk-9h');
const screenshotDir = path.join('tests', 'playwright', 'artifacts', 'jetpk-9h', 'screenshots');

test.beforeAll(() => {
  fs.mkdirSync(auditDir, { recursive: true });
  fs.mkdirSync(screenshotDir, { recursive: true });
});

test.describe('JetPK dashboard smoke (unauthenticated safe routes)', () => {
  test('home and login render without console errors', async ({ page }, testInfo) => {
    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    await page.goto('/');
    await expect(page).toHaveTitle(/JetPakistan|Jet/i);
    await page.screenshot({ path: path.join(screenshotDir, `${testInfo.project.name}-home.png`), fullPage: true });

    await page.goto('/login');
    await expect(page.locator('body')).toBeVisible();
    await page.screenshot({ path: path.join(screenshotDir, `${testInfo.project.name}-login.png`), fullPage: true });

    fs.writeFileSync(
      path.join(auditDir, `console-${testInfo.project.name}.json`),
      JSON.stringify({ project: testInfo.project.name, consoleErrors }, null, 2),
    );

    expect(consoleErrors.filter((e) => !e.includes('favicon'))).toEqual([]);
  });

  test('404 error shell is single chrome', async ({ page }, testInfo) => {
    await page.goto('/this-route-should-not-exist-jetpk-9h');
    const headers = await page.locator('header').count();
    const footers = await page.locator('footer').count();
    const panels = await page.locator('.jp-error-panel').count();
    await page.screenshot({ path: path.join(screenshotDir, `${testInfo.project.name}-404.png`), fullPage: true });

    expect(headers).toBeLessThanOrEqual(1);
    expect(footers).toBeLessThanOrEqual(1);
    expect(panels).toBeLessThanOrEqual(1);
  });
});
