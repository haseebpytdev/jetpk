/**
 * JetPK mobile live activation — viewport shell + toggle controls.
 * Run: OTA_CLIENT_SLUG=jetpk OTA_MOBILE_APP_THEME=jetpakistan-app LOCAL_OTA_URL=http://127.0.0.1:8000
 *      npx playwright test -c playwright.mobile-integration.config.ts
 */
import { test, expect, Page } from '@playwright/test';

const MOBILE_UA =
  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

const VIEWPORTS = [
  { name: '390x844', width: 390, height: 844 },
  { name: '430x932', width: 430, height: 932 },
  { name: '768x1024', width: 768, height: 1024 },
  { name: '1440x900', width: 1440, height: 900 },
];

async function gotoHome(page: Page, width: number, height: number, viewportWidthHeader?: number) {
  await page.setViewportSize({ width, height });
  const headers: Record<string, string> = { 'User-Agent': MOBILE_UA };
  if (viewportWidthHeader !== undefined) {
    headers['Sec-CH-Viewport-Width'] = String(viewportWidthHeader);
  }
  await page.setExtraHTTPHeaders(headers);
  const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
  expect(response?.status() ?? 0).toBeLessThan(500);
}

test.describe('JetPK mobile live activation', () => {
  for (const vp of VIEWPORTS.filter((v) => v.width <= 768)) {
    test(`mobile shell @ ${vp.name} includes jp-app + app.css`, async ({ page }) => {
      await gotoHome(page, vp.width, vp.height, vp.width);

      await expect(page.getByTestId('ota-mobile-app-shell')).toBeVisible();
      await expect(page.locator('body.jp-app')).toBeVisible();

      const appCss = await page.evaluate(() =>
        Array.from(document.querySelectorAll('link[rel=stylesheet]'))
          .map((l) => (l as HTMLLinkElement).href)
          .some((h) => /themes\/mobile\/jetpakistan-app\/css\/app\.css\?v=\d+/.test(h)),
      );
      expect(appCss).toBeTruthy();

      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      );
      expect(overflow).toBeLessThanOrEqual(2);
    });
  }

  test('desktop @ 1440x900 does not load jetpakistan-app app.css in auto mode', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.setExtraHTTPHeaders({
      'User-Agent':
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
      'Sec-CH-Viewport-Width': '1440',
    });

    const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
    expect(response?.status() ?? 0).toBeLessThan(500);

    await expect(page.getByTestId('ota-mobile-app-shell')).toHaveCount(0);
    await expect(page.locator('.jp-site-main')).toBeVisible();

    const appCss = await page.evaluate(() =>
      Array.from(document.querySelectorAll('link[rel=stylesheet]'))
        .map((l) => (l as HTMLLinkElement).href)
        .some((h) => /themes\/mobile\/jetpakistan-app\/css\/app\.css/.test(h)),
    );
    expect(appCss).toBeFalsy();
    await expect(page.getByTestId('jp-desktop-mobile-app-toggle')).toBeVisible();
  });

  test('mobile → desktop control restores desktop shell', async ({ page }) => {
    await gotoHome(page, 390, 844, 390);
    await expect(page.getByTestId('ota-mobile-app-shell')).toBeVisible();

    await page.getByTestId('ota-mobile-app-desktop-toggle').locator('button').click();
    await page.waitForURL((url) => !url.searchParams.has('_ota_auto_shell'));

    await expect(page.getByTestId('ota-mobile-app-shell')).toHaveCount(0);
    const appCss = await page.evaluate(() =>
      Array.from(document.querySelectorAll('link[rel=stylesheet]'))
        .map((l) => (l as HTMLLinkElement).href)
        .some((h) => /themes\/mobile\/jetpakistan-app\/css\/app\.css/.test(h)),
    );
    expect(appCss).toBeFalsy();
  });

  test('desktop → mobile app control restores mobile shell', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.setExtraHTTPHeaders({
      'User-Agent':
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
      'Sec-CH-Viewport-Width': '1440',
    });
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const mobileToggle = page.getByTestId('jp-desktop-mobile-app-toggle');
    await expect(mobileToggle).toBeVisible();
    await mobileToggle.click();
    await page.waitForURL((url) => !url.searchParams.has('_ota_auto_shell'));

    await expect(page.getByTestId('ota-mobile-app-shell')).toBeVisible();
    await expect(page.locator('body.jp-app')).toBeVisible();
  });
});
