/**
 * Proposed safe test — MA-5 agent portal under the app skin
 * JETPK-MOBILE-APP-THEME · baseline 6fbfae4
 *
 * SAFETY: local/deterministic, read-only navigation, existing agent fixture.
 * NO live supplier calls, NO bookings/PNR, NO payments, NO deposit approval, NO email.
 * Exclude from *-live.config.ts. Requires OTA_MOBILE_APP_THEME=jetpakistan-app.
 */
import { test, expect, Page } from '@playwright/test';

async function loginAsAgent(page: Page): Promise<void> {
  test.skip(true, 'Wire loginAsAgent() + set OTA_MOBILE_APP_THEME=jetpakistan-app, then remove.');
}

const PHONES = [
  { width: 360, height: 800 },
  { width: 390, height: 844 },
  { width: 430, height: 932 },
];
const FINANCE = ['/agent/wallet', '/agent/ledger', '/agent/deposits', '/agent/commissions'];

test.describe('MA-5 — agent portal in the app skin', () => {
  for (const vp of PHONES) {
    test(`finance pages @ ${vp.width}px: no overflow`, async ({ page }) => {
      await loginAsAgent(page);
      await page.setViewportSize(vp);
      for (const path of FINANCE) {
        await page.goto(path);
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth
        );
        expect(overflow, `${path} @ ${vp.width}px`).toBeLessThanOrEqual(1);
      }
    });
  }

  // The rule that matters: financial values must never be ellipsised or clipped.
  test('amounts are never truncated', async ({ page }) => {
    await loginAsAgent(page);
    await page.setViewportSize({ width: 360, height: 800 });
    for (const path of FINANCE) {
      await page.goto(path);
      const bad = await page.evaluate(() =>
        Array.from(document.querySelectorAll('.ota-mobile-agent__amount, .ota-mobile-agent__amount--total'))
          .filter((el) => {
            const cs = getComputedStyle(el);
            const ellipsised = cs.textOverflow === 'ellipsis' && cs.whiteSpace === 'nowrap';
            const clipped = el.scrollWidth > el.clientWidth + 1;
            return ellipsised || clipped;
          })
          .map((el) => (el.textContent || '').trim())
      );
      expect(bad, `clipped/ellipsised amounts on ${path}`).toEqual([]);
    }
  });

  test('filter FORMS keep block layout (not squashed into a chip strip)', async ({ page }) => {
    await loginAsAgent(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/agent/ledger');
    const form = page.locator('.ota-mobile-agent__filters--form');
    if (await form.count()) {
      const dir = await form.evaluate((el) => getComputedStyle(el).flexDirection);
      expect(dir).toBe('column');
    }
  });

  test('agent buttons meet the 44px target', async ({ page }) => {
    await loginAsAgent(page);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/agent/bookings');
    const btns = page.locator('.ota-mobile-agent__btn');
    const n = await btns.count();
    for (let i = 0; i < Math.min(n, 6); i++) {
      const box = await btns.nth(i).boundingBox();
      if (box) expect(box.height).toBeGreaterThanOrEqual(43.5);
    }
  });
});
