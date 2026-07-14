import { test, expect } from '@playwright/test';
import { PUBLIC_DROPDOWN_VIEWPORTS } from './helpers/critical-viewports';
import { getOverflowMetrics, OVERFLOW_TOLERANCE_PX } from './helpers/layout-checks';
import { gotoPublicPage } from './helpers/public-fast-navigation';

const PAGES = [
  { key: 'home', path: '/' },
  { key: 'flights-search', path: '/flights/search' },
] as const;

async function assertNoPageOverflow(page: import('@playwright/test').Page): Promise<void> {
  const metrics = await getOverflowMetrics(page);
  expect(metrics.hasOverflow, `horizontal overflow ${metrics.bodyScrollWidth} > ${metrics.innerWidth}`).toBe(
    false,
  );
}

async function activePaxPicker(page: import('@playwright/test').Page) {
  const pickers = page.locator('[data-hero-search] [data-pax-picker]');
  const count = await pickers.count();
  for (let i = 0; i < count; i += 1) {
    const candidate = pickers.nth(i);
    const adults = candidate.locator('[name="adults"]');
    if ((await adults.count()) > 0 && !(await adults.isDisabled())) {
      await expect(candidate).toBeVisible({ timeout: 8_000 });
      return candidate;
    }
  }
  const fallback = pickers.first();
  await expect(fallback).toBeVisible({ timeout: 8_000 });
  return fallback;
}

for (const pageDef of PAGES) {
  for (const vp of PUBLIC_DROPDOWN_VIEWPORTS) {
    test(`${pageDef.key} travellers dropdown @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      const response = await gotoPublicPage(page, pageDef.path, pageDef.key);
      expect(response?.status() ?? 0).toBeLessThan(500);

      await assertNoPageOverflow(page);

      const widget = page.locator('[data-hero-search]').first();
      await expect(widget).toBeVisible();
      const picker = await activePaxPicker(page);
      const trigger = picker.locator('.ota-hero-search-pax__trigger');
      await expect(trigger).toBeVisible();

      const searchBtn = page.locator('.ota-hero-search-submit').first();
      await expect(searchBtn).toBeVisible();

      await trigger.click();
      await expect(picker).toHaveAttribute('open', '');
      const panel = picker.locator('.ota-hero-search-pax__panel');
      await expect(panel).toBeVisible();

      const panelBox = await panel.boundingBox();
      const viewport = page.viewportSize();
      expect(panelBox).not.toBeNull();
      expect(viewport).not.toBeNull();
      if (panelBox && viewport) {
        expect(panelBox.x).toBeGreaterThanOrEqual(-OVERFLOW_TOLERANCE_PX);
        expect(panelBox.x + panelBox.width).toBeLessThanOrEqual(viewport.width + OVERFLOW_TOLERANCE_PX);
        expect(panelBox.y).toBeGreaterThanOrEqual(-OVERFLOW_TOLERANCE_PX);
        const panelFitsOrScrolls =
          panelBox.y + panelBox.height <= viewport.height + OVERFLOW_TOLERANCE_PX ||
          (await panel.evaluate((el) => {
            const style = window.getComputedStyle(el);
            const maxH = parseFloat(style.maxHeight || '0');
            const scrollable = style.overflowY === 'auto' || style.overflowY === 'scroll';
            return scrollable || maxH > 0;
          }));
        expect(panelFitsOrScrolls).toBe(true);
      }

      await expect(picker.locator('[name="cabin"]')).toBeVisible();
      await expect(picker.locator('[name="adults"]')).toBeVisible();
      await expect(picker.locator('[name="children"]')).toBeVisible();
      await expect(picker.locator('[name="infants"]')).toBeVisible();

      const searchBox = await searchBtn.boundingBox();
      if (searchBox && panelBox) {
        const overlaps =
          panelBox.x < searchBox.x + searchBox.width &&
          panelBox.x + panelBox.width > searchBox.x &&
          panelBox.y < searchBox.y + searchBox.height &&
          panelBox.y + panelBox.height > searchBox.y;
        if (overlaps) {
          const searchVisible = await searchBtn.isVisible();
          expect(searchVisible).toBe(true);
        }
      }

      const summary = picker.locator('[data-pax-summary]');
      const beforeSummary = (await summary.textContent())?.trim() ?? '';
      await picker.locator('[name="children"]').selectOption('1');
      await picker.locator('[name="cabin"]').selectOption('business');
      await expect(summary).not.toHaveText(beforeSummary);
      await expect(summary).toContainText('Business');

      await page.keyboard.press('Escape');
      await expect(picker).not.toHaveAttribute('open', '');

      await trigger.click();
      await expect(picker).toHaveAttribute('open', '');
      await page.mouse.click(8, 8);
      await expect(picker).not.toHaveAttribute('open', '');

      await trigger.click();
      await expect(picker).toHaveAttribute('open', '');
      await trigger.click();
      await expect(picker).not.toHaveAttribute('open', '');

      const blockingPanel = page.locator('[data-pax-picker][open] .ota-hero-search-pax__panel');
      await expect(blockingPanel).toHaveCount(0);
      await expect(searchBtn).toBeVisible();
    });
  }
}
