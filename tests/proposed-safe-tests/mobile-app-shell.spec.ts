/**
 * Proposed safe test — MA-2 JetPK mobile app shell
 * JETPK-MOBILE-APP-THEME · baseline 6fbfae4
 *
 * SAFETY: local/deterministic, read-only navigation, existing customer fixture.
 * NO live supplier calls, NO bookings/PNR, NO payments, NO email. Exclude from *-live.config.ts.
 *
 * Requires OTA_MOBILE_APP_THEME=jetpakistan-app in the test env (see integration doc).
 * Guards: the toggle actually swaps the shell, contracts survive, chrome is app-style and
 * accessible, and no financial data is hidden.
 */
import { test, expect, Page } from '@playwright/test';

async function loginAsCustomer(page: Page): Promise<void> {
  test.skip(true, 'Wire loginAsCustomer() + set OTA_MOBILE_APP_THEME=jetpakistan-app, then remove.');
}

const PHONES = [
  { width: 360, height: 800 },
  { width: 390, height: 844 },
  { width: 430, height: 932 },
];

test.describe('MA-2 — app shell renders and preserves contracts', () => {
  for (const vp of PHONES) {
    test(`@ ${vp.width}px: app skin active, contracts intact, no overflow`, async ({ page }) => {
      await loginAsCustomer(page);
      await page.setViewportSize(vp);
      await page.goto('/customer');

      // Contract: same testid as the shared shell.
      const shell = page.getByTestId('ota-mobile-app-shell');
      await expect(shell).toBeVisible();

      // The skin is active (scoping class + stylesheet).
      await expect(shell).toHaveClass(/jp-app/);
      const appCss = await page.evaluate(() =>
        Array.from(document.querySelectorAll('link[rel=stylesheet]'))
          .map((l) => (l as HTMLLinkElement).href)
          .some((h) => /themes\/mobile\/jetpakistan-app\/css\/app\.css/.test(h))
      );
      expect(appCss, 'app.css must load when the toggle is on').toBeTruthy();

      // Structure preserved.
      await expect(page.locator('#ota-mobile-app-main')).toBeVisible();
      await expect(page.locator('.ota-mobile-app__bottom-nav')).toBeVisible();

      // No horizontal overflow.
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
      );
      expect(overflow).toBeLessThanOrEqual(1);
    });
  }

  test('tab bar targets are >=44px and labels do not wrap out', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/customer');

    const items = page.locator('.ota-mobile-app__bottom-nav-item');
    const n = await items.count();
    expect(n).toBeGreaterThan(0);
    for (let i = 0; i < n; i++) {
      const box = await items.nth(i).boundingBox();
      expect(box!.height, 'tab target must be >=44px').toBeGreaterThanOrEqual(43.5);
    }
  });

  test('content is not hidden behind the fixed tab bar', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/customer');
    const pad = await page.locator('#ota-mobile-app-main').evaluate(
      (el) => parseFloat(getComputedStyle(el).paddingBottom)
    );
    expect(pad, 'main must clear the tab bar').toBeGreaterThanOrEqual(58);
  });

  test('keyboard focus is visible (tokenised ring, not suppressed)', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/customer');
    await page.keyboard.press('Tab');
    const shadow = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      return el ? getComputedStyle(el).boxShadow : '';
    });
    expect(shadow, 'focus-visible must render a ring').not.toBe('none');
  });

  test('wide tables scroll rather than hide financial data', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/customer/bookings');
    const wraps = page.locator('.table-responsive, .ota-account-table-wrap, .jp-table-wrap');
    if (await wraps.count()) {
      const ok = await wraps.first().evaluate((el) => getComputedStyle(el).overflowX === 'auto');
      expect(ok).toBeTruthy();
    }
  });
});
