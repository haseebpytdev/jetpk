import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const screenshotDir = path.join('tests', 'playwright', 'artifacts', 'jetpk-homepage-lcp', 'screenshots');
const auditDir = path.join('storage', 'app', 'audits', 'jetpk-homepage-lcp');
const homePath = process.env.JETPK_HOME_PATH || '/jetpk/home';

const viewports = [
  { name: 'desktop-1920', width: 1920, height: 1080 },
  { name: 'desktop-1440', width: 1440, height: 900 },
  { name: 'desktop-1366', width: 1366, height: 768 },
  { name: 'tablet-1024', width: 1024, height: 768 },
  { name: 'tablet-portrait', width: 768, height: 1024 },
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'mobile-360', width: 360, height: 800 },
] as const;

async function assertNoHorizontalOverflow(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2);
  expect(overflow, 'horizontal overflow').toBe(false);
}

test.describe('homepage hero LCP', () => {
  for (const viewport of viewports) {
    test(`responsive hero contract @ ${viewport.name}`, async ({ page }, testInfo) => {
      const consoleErrors: string[] = [];
      const failedRequests: string[] = [];

      page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
      });
      page.on('response', (res) => {
        const url = res.url();
        if (res.status() >= 400 && /\/(storage\/|client-assets\/|themes\/frontend\/jetpakistan\/)/.test(url)) {
          failedRequests.push(`${res.status()} ${url}`);
        }
      });

      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await page.goto(homePath, { waitUntil: 'domcontentloaded', timeout: 60_000 });

      const hero = page.locator('section.hero');
      await expect(hero).toBeVisible({ timeout: 15_000 });

      const loader = page.locator('#jpLoader');
      await expect(loader).toHaveClass(/done/);

      const heroImg = page.locator('section.hero .hero-img');
      if (await hero.evaluate((el) => el.classList.contains('hero--has-image'))) {
        await expect(heroImg).toBeVisible();
        await expect(heroImg).toHaveAttribute('loading', 'eager');
        await expect(heroImg).toHaveAttribute('fetchpriority', 'high');
        await expect(heroImg).toHaveAttribute('width', /.+/);
        await expect(heroImg).toHaveAttribute('height', /.+/);

        const box = await heroImg.boundingBox();
        expect(box?.width ?? 0).toBeGreaterThan(100);
        expect(box?.height ?? 0).toBeGreaterThan(80);
      }

      await expect(page.locator('section.hero h1')).toBeVisible();
      await expect(page.locator('section.hero .search, section.hero .jp-master-search').first()).toBeVisible();

      const searchInput = page.locator('section.hero input.jp-airport-display, section.hero input[data-jp-airport-input]').first();
      if (await searchInput.count()) {
        await expect(searchInput).toBeVisible();
        await searchInput.click({ trial: true });
      }

      await assertNoHorizontalOverflow(page);
      fs.mkdirSync(screenshotDir, { recursive: true });
      await page.screenshot({
        path: path.join(screenshotDir, `${viewport.name}.png`),
        fullPage: false,
      });

      const actionableErrors = consoleErrors.filter(
        (msg) => !msg.includes('ERR_NAME_NOT_RESOLVED') && !msg.includes('fonts.googleapis.com'),
      );

      expect(actionableErrors, 'console errors').toEqual([]);
      expect(failedRequests, 'failed asset requests').toEqual([]);

      fs.mkdirSync(auditDir, { recursive: true });
      fs.writeFileSync(
        path.join(auditDir, `${viewport.name}.json`),
        JSON.stringify({ viewport, homePath, consoleErrors, failedRequests }, null, 2),
      );
    });
  }

  test('hero remains visible with javascript disabled', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto(homePath, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await expect(page.locator('section.hero h1')).toBeVisible();
    await expect(page.locator('section.hero .search, section.hero .jp-master-search').first()).toBeVisible();
    await context.close();
  });
});
