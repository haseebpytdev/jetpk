/**
 * Local mobile theme integration screenshots (MA integration gate).
 * Run: OTA_MOBILE_APP_THEME=jetpakistan-app LOCAL_OTA_URL=http://127.0.0.1:8000 npx playwright test tests/proposed-safe-tests/mobile-integration-screenshots.spec.ts
 */
import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const OUT = path.join('storage', 'app', 'audits', 'jetpk-mobile-integration-screenshots');

const VIEWPORTS = [
  { name: '390x844', width: 390, height: 844 },
  { name: '430x932', width: 430, height: 932 },
  { name: '768x1024', width: 768, height: 1024 },
];

const PAGES = [
  { path: '/', label: 'home' },
  { path: '/login', label: 'login' },
];

test.describe('MA integration — local mobile screenshots', () => {
  test.beforeAll(() => {
    fs.mkdirSync(OUT, { recursive: true });
  });

  for (const vp of VIEWPORTS) {
    for (const pg of PAGES) {
      test(`${pg.label} @ ${vp.name}`, async ({ page }) => {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await page.setExtraHTTPHeaders({
          'User-Agent':
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        });
        const response = await page.goto(pg.path, { waitUntil: 'domcontentloaded' });
        expect(response?.status() ?? 0).toBeLessThan(500);

        const shell = page.getByTestId('ota-mobile-app-shell');
        if (await shell.count()) {
          await expect(shell).toBeVisible();
          const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
          );
          expect(overflow).toBeLessThanOrEqual(2);
        }

        await page.screenshot({
          path: path.join(OUT, `${pg.label}-${vp.name}.png`),
          fullPage: true,
        });
      });
    }
  }
});
