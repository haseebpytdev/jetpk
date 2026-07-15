/**
 * Proposed safe test — MA-1 mobile theme foundation (zero visual change)
 * JETPK-MOBILE-APP-THEME · baseline 6fbfae4
 *
 * SAFETY: local/deterministic, read-only navigation, existing customer/agent fixtures.
 * NO live supplier calls, NO bookings/PNR, NO payments, NO email. Exclude from *-live.config.ts.
 *
 * MA-1 must change NOTHING visually. These assertions are deliberately about *absence* of change:
 * the mobile shell still renders, on the shared markup, at every phone viewport.
 * (Prefer pairing with the repo's existing screenshot baselines for a true before/after diff.)
 */
import { test, expect, Page } from '@playwright/test';

async function loginAsCustomer(page: Page): Promise<void> {
  test.skip(true, 'Wire loginAsCustomer() to the repo customer fixture, then remove this skip.');
}

const PHONES = [
  { width: 360, height: 800 },
  { width: 390, height: 844 },
  { width: 430, height: 932 },
];

test.describe('MA-1 — mobile shell unchanged under the new resolver area', () => {
  for (const vp of PHONES) {
    test(`customer dashboard @ ${vp.width}px still renders the shared mobile shell`, async ({ page }) => {
      await loginAsCustomer(page);
      await page.setViewportSize(vp);
      await page.goto('/customer');

      // The existing shared shell must still be what renders (default-mobile shim -> layouts/mobile-app).
      await expect(page.getByTestId('ota-mobile-app-shell')).toBeVisible();

      // No app-theme stylesheet should be loaded while the toggle is default-mobile.
      const appCss = await page.evaluate(() =>
        Array.from(document.querySelectorAll('link[rel=stylesheet]'))
          .map((l) => (l as HTMLLinkElement).href)
          .filter((h) => /themes\/mobile\/jetpakistan-app/.test(h))
      );
      expect(appCss, 'MA-1 must not load an app theme').toEqual([]);

      // Shared mobile stylesheet still present.
      const sharedCss = await page.evaluate(() =>
        Array.from(document.querySelectorAll('link[rel=stylesheet]'))
          .map((l) => (l as HTMLLinkElement).href)
          .some((h) => /ota-mobile-app\.css/.test(h))
      );
      expect(sharedCss, 'shared mobile shell CSS must still load').toBeTruthy();

      // No horizontal overflow (regression guard).
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
      );
      expect(overflow).toBeLessThanOrEqual(1);
    });
  }

  test('desktop preference still escapes the mobile shell', async ({ page, context }) => {
    await loginAsCustomer(page);
    await context.addCookies([
      { name: 'ota_view_mode', value: 'desktop', url: 'http://localhost' },
    ]);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/customer');
    await expect(page.getByTestId('ota-mobile-app-shell')).toHaveCount(0);
  });
});
