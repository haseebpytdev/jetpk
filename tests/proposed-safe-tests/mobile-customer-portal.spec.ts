/**
 * Proposed safe test — MA-4 customer portal under the app skin
 * JETPK-MOBILE-APP-THEME · baseline 6fbfae4
 *
 * SAFETY: local/deterministic, read-only navigation, existing customer fixture.
 * NO live supplier calls, NO bookings/PNR, NO payments, NO email. Exclude from *-live.config.ts.
 * Requires OTA_MOBILE_APP_THEME=jetpakistan-app.
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

test.describe('MA-4 — customer portal in the app skin', () => {
  for (const vp of PHONES) {
    test(`@ ${vp.width}px: dashboard + bookings render without overflow`, async ({ page }) => {
      await loginAsCustomer(page);
      await page.setViewportSize(vp);
      for (const path of ['/customer', '/customer/bookings']) {
        await page.goto(path);
        await expect(page.getByTestId('ota-mobile-app-shell')).toHaveClass(/jp-app/);
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth
        );
        expect(overflow, `${path} @ ${vp.width}px`).toBeLessThanOrEqual(1);
      }
    });
  }

  test('dashboard stats are 2-up on a small phone', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/customer');
    const stats = page.locator('.ota-mobile-customer-dashboard-stats');
    if (await stats.count()) {
      const cols = await stats.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
      expect(cols).toBe(2);
    }
  });

  test('booking filters scroll horizontally rather than wrap', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/customer/bookings');
    const filters = page.locator('.ota-mobile-customer__filters');
    if (await filters.count()) {
      const ox = await filters.evaluate((el) => getComputedStyle(el).overflowX);
      expect(ox).toBe('auto');
    }
  });

  test('portal buttons meet the 44px target', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/customer/bookings');
    const btns = page.locator('.ota-mobile-customer__btn');
    const n = await btns.count();
    for (let i = 0; i < Math.min(n, 6); i++) {
      const box = await btns.nth(i).boundingBox();
      if (box) expect(box.height).toBeGreaterThanOrEqual(43.5);
    }
  });
});
