import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const outDir = path.join(process.cwd(), 'UI_test', 'screenshots', 'home', 'date-range');

function trackConsoleErrors(page: import('@playwright/test').Page): string[] {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(err.message));
  page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  return errors;
}

async function shot(page: import('@playwright/test').Page, name: string) {
  fs.mkdirSync(outDir, { recursive: true });
  await page.screenshot({ path: path.join(outDir, name), fullPage: false });
}

async function heroWidget(page: import('@playwright/test').Page) {
  const widget = page.locator('[data-hero-search], [data-jp-search]').first();
  await expect(widget).toBeVisible();
  return widget;
}

async function selectTripType(page: import('@playwright/test').Page, trip: 'round_trip' | 'one_way') {
  const widget = await heroWidget(page);
  const tab = widget.locator(`button[data-jp-trip="${trip}"]`);
  await expect(tab).toBeVisible();
  await tab.click();
  await expect(widget.locator('[data-jp-trip-type]')).toHaveValue(trip);
}

async function openReturnRangeModal(page: import('@playwright/test').Page) {
  await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await selectTripType(page, 'round_trip');
  const widget = await heroWidget(page);
  const trigger = widget.locator('[data-jp-date-role="return_range"] [data-jp-date-trigger]');
  await expect(trigger).toBeVisible();
  await trigger.click();
  const overlay = page.locator('[data-jp-date-overlay]');
  await expect(overlay).toBeVisible();
  await expect(overlay.locator('.jp-date-calendar.ota-return-range-picker--open')).toBeVisible();
}

async function enabledDays(page: import('@playwright/test').Page) {
  return page.locator('[data-jp-date-overlay] [data-jp-cal-day]:not([disabled])');
}

test.describe('Desktop JetPK date modal compact height screenshots', () => {
  const viewports = [
    { width: 1366, height: 768, name: '1366x768' },
    { width: 1366, height: 720, name: '1366x720' },
    { width: 1440, height: 700, name: '1440x700' },
    { width: 1536, height: 720, name: '1536x720' },
    { width: 1920, height: 900, name: '1920x900' },
    { width: 1024, height: 768, name: '1024x768' },
  ] as const;

  test('modal fits compact desktop heights', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 1366, height: 720 });
    await openReturnRangeModal(page);
    await shot(page, 'desktop-date-modal-1366x720.png');
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-jp-date-overlay]')).toBeHidden();
  });

  test('complete return and one-way at 1366x720', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 720 });

    await openReturnRangeModal(page);
    const days = await enabledDays(page);
    await days.nth(5).click();
    await days.nth(12).click();
    await expect(page.locator('[data-jp-date-overlay]')).toBeHidden();
    await shot(page, 'desktop-date-modal-return-complete-1366x720.png');

    await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await selectTripType(page, 'one_way');
    const widget = await heroWidget(page);
    const departTrigger = widget.locator('[data-jp-date-role="depart"] [data-jp-date-trigger]');
    await expect(departTrigger).toBeVisible();
    await departTrigger.click();
    await expect(page.locator('[data-jp-date-overlay] .jp-date-calendar.ota-return-range-picker--open')).toBeVisible();
    await shot(page, 'desktop-date-modal-oneway-1366x720.png');
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-jp-date-overlay]')).toBeHidden();
  });

  test('return cannot precede outbound in range mode', async ({ page }) => {
    const errors = trackConsoleErrors(page);
    await page.setViewportSize({ width: 1366, height: 720 });
    await openReturnRangeModal(page);
    await expect(page.locator('[data-jp-date-overlay]')).toHaveCount(1);
    const days = await enabledDays(page);
    const outbound = days.nth(8);
    const earlier = days.nth(3);
    const earlierIso = await earlier.getAttribute('data-jp-cal-day');
    await outbound.click();
    await earlier.click();
    const widget = await heroWidget(page);
    const departHidden = widget.locator('[data-jp-range-depart]');
    const returnHidden = widget.locator('[data-jp-range-return]');
    await expect(departHidden).toHaveValue(String(earlierIso));
    await expect(returnHidden).toHaveValue('');
    await expect(page.locator('[data-jp-date-overlay]')).toBeVisible();
    expect(errors, errors.join('\n')).toHaveLength(0);
  });

  test('JetPK date picker functional contract', async ({ page }) => {
    const errors = trackConsoleErrors(page);
    await page.setViewportSize({ width: 1366, height: 720 });

    await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await selectTripType(page, 'round_trip');
    const widget = await heroWidget(page);
    const rangeTrigger = widget.locator('[data-jp-date-role="return_range"] [data-jp-date-trigger]');
    await rangeTrigger.click();
    const overlay = page.locator('[data-jp-date-overlay]');
    await expect(overlay).toBeVisible();
    await expect(overlay).toHaveCount(1);

    const days = await enabledDays(page);
    const departIso = await days.nth(6).getAttribute('data-jp-cal-day');
    const returnIso = await days.nth(11).getAttribute('data-jp-cal-day');
    await days.nth(6).click();
    await days.nth(11).click();
    await expect(overlay).toBeHidden();
    await expect(widget.locator('[data-jp-range-depart]')).toHaveValue(String(departIso));
    await expect(widget.locator('[data-jp-range-return]')).toHaveValue(String(returnIso));

    await selectTripType(page, 'one_way');
    const departTrigger = widget.locator('[data-jp-date-role="depart"] [data-jp-date-trigger]');
    await expect(departTrigger).toBeVisible();
    await departTrigger.click();
    await expect(overlay).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(overlay).toBeHidden();
    expect(errors, errors.join('\n')).toHaveLength(0);
  });
});
