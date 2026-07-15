/**
 * Proposed safe test — MA-3 public site & booking flow under the app skin
 * JETPK-MOBILE-APP-THEME · baseline 6fbfae4
 *
 * SAFETY: local/deterministic ONLY. Uses the repo's existing fixture/mock search flow.
 * NEVER performs a live supplier search, real booking, PNR, payment or email.
 * Requires OTA_MOBILE_APP_THEME=jetpakistan-app. Exclude from *-live.config.ts.
 */
import { test, expect, Page } from '@playwright/test';

async function mobileContext(page: Page): Promise<void> {
  test.skip(true, 'Wire the repo fixture search flow + set OTA_MOBILE_APP_THEME=jetpakistan-app.');
}

const PHONES = [
  { width: 360, height: 800 },
  { width: 390, height: 844 },
  { width: 430, height: 932 },
];

test.describe('MA-3 — public/booking flow renders in the app skin', () => {
  for (const vp of PHONES) {
    test(`home @ ${vp.width}px: app skin active, no overflow`, async ({ page }) => {
      await mobileContext(page);
      await page.setViewportSize(vp);
      await page.goto('/');

      await expect(page.getByTestId('ota-mobile-app-shell')).toHaveClass(/jp-app/);
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
      );
      expect(overflow, 'no horizontal overflow on the landing page').toBeLessThanOrEqual(1);
    });
  }

  test('card primitives share one radius (compaction layer applied)', async ({ page }) => {
    await mobileContext(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');
    const radii = await page.evaluate(() =>
      Array.from(document.querySelectorAll('.ota-mobile-home__search-card, .ota-mobile-home__recent-card'))
        .map((el) => getComputedStyle(el).borderRadius)
    );
    if (radii.length > 1) {
      expect(new Set(radii).size, 'cards must share one radius').toBe(1);
    }
  });

  test('no shadow stacking on cards', async ({ page }) => {
    await mobileContext(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');
    const shadows = await page.evaluate(() =>
      Array.from(document.querySelectorAll('.ota-mobile-home__search-card'))
        .map((el) => getComputedStyle(el).boxShadow)
    );
    for (const s of shadows) expect(s === 'none' || s === '').toBeTruthy();
  });

  test('booking CTAs meet the 44px target', async ({ page }) => {
    await mobileContext(page);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/');
    const ctas = page.locator('.ota-mobile-booking__cta, .ota-mobile-results__cta');
    const n = await ctas.count();
    for (let i = 0; i < n; i++) {
      const box = await ctas.nth(i).boundingBox();
      if (box) expect(box.height).toBeGreaterThanOrEqual(43.5);
    }
  });
});
