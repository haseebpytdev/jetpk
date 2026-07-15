import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const outDir = path.join(process.cwd(), 'UI_test', 'screenshots', 'home', 'date-range');

async function shot(page: import('@playwright/test').Page, name: string) {
  fs.mkdirSync(outDir, { recursive: true });
  await page.screenshot({ path: path.join(outDir, name), fullPage: false });
}

async function openReturnModal(page: import('@playwright/test').Page) {
  await page.goto('/');
  const widget = page.locator('[data-hero-search]').first();
  await widget.locator('[data-trip-radio][value="round_trip"]').check();
  await widget.locator('[data-return-range-trigger="depart"]').click();
  await expect(page.locator('[data-date-modal]').first()).toHaveClass(/ota-date-modal--open/);
}

test.describe('Desktop date modal compact height screenshots', () => {
  const viewports = [
    { width: 1366, height: 768, name: '1366x768' },
    { width: 1366, height: 720, name: '1366x720' },
    { width: 1440, height: 700, name: '1440x700' },
    { width: 1536, height: 720, name: '1536x720' },
    { width: 1920, height: 900, name: '1920x900' },
    { width: 1024, height: 768, name: '1024x768' },
  ] as const;

  test('modal fits compact desktop heights', async ({ page }) => {
    test.setTimeout(180_000);
    for (const vp of viewports) {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await openReturnModal(page);
      await shot(page, `desktop-date-modal-${vp.name}.png`);
      await page.keyboard.press('Escape');
      await expect(page.locator('[data-date-modal]').first()).toBeHidden();
    }
  });

  test('complete return and one-way at 1366x720', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 720 });

    await openReturnModal(page);
    const picker = page.locator('[data-date-modal]').first();
    const days = picker.locator('[data-return-range-day]:not([disabled])');
    await days.nth(5).click();
    await days.nth(12).click();
    await page.locator('[data-return-range-apply]').click();
    await expect(picker).toBeHidden();
    await shot(page, 'desktop-date-modal-return-complete-1366x720.png');

    await page.goto('/');
    const widget = page.locator('[data-hero-search]').first();
    await widget.locator('[data-trip-radio][value="one_way"]').check();
    await widget.locator('[data-return-range-trigger="depart"]').click();
    await expect(picker).toHaveClass(/ota-date-modal--open/);
    await shot(page, 'desktop-date-modal-oneway-1366x720.png');
  });
});
