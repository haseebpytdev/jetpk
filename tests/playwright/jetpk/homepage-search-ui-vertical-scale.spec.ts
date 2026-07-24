import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const HOME_PATH = process.env.JETPK_HOME_PATH ?? '/';
const artifactDir = path.join('tests', 'playwright', 'artifacts', 'homepage-search-ui-vertical-scale');
const scales = [
  { label: '80', value: 0.8 },
  { label: '90', value: 0.9 },
  { label: '100', value: 1.0 },
  { label: '115', value: 1.15 },
] as const;

type Metrics = {
  outerWidth: number;
  outerHeight: number;
  topPadding: number;
  bottomPadding: number;
  fieldHeight: number;
  searchButtonHeight: number;
  tabHeight: number;
  swapSize: number;
  swapIconSize: number;
  rowGap: number;
  firstFieldWidth: number;
  labelSize: number;
  valueSize: number;
  radius: number;
  cssScale: string;
};

async function readMetrics(page: Page): Promise<Metrics> {
  return page.evaluate(() => {
    const root = document.querySelector('.jp-home') as HTMLElement | null;
    const shell = document.querySelector('#jp-flight-search.search') as HTMLElement | null;
    const field = document.querySelector('#jp-flight-search .jp-airport-field') as HTMLElement | null;
    const tab = document.querySelector('#segTrip button.on') as HTMLElement | null;
    const btn = document.querySelector('#jp-flight-search .btn-search') as HTMLElement | null;
    const swap = document.querySelector('#jp-flight-search .swap') as HTMLElement | null;
    const fieldsRow = document.querySelector('#jp-flight-search .fields') as HTMLElement | null;
    const label = document.querySelector('#jp-flight-search .jp-airport-field label') as HTMLElement | null;
    const input = document.querySelector('#jp-flight-search .jp-airport-display') as HTMLElement | null;
    const swapIcon = swap?.querySelector('svg') as SVGElement | null;

    const shellCs = shell ? getComputedStyle(shell) : null;
    const fieldCs = field ? getComputedStyle(field) : null;
    const labelCs = label ? getComputedStyle(label) : null;
    const valueCs = input ? getComputedStyle(input) : null;
    const rowCs = fieldsRow ? getComputedStyle(fieldsRow) : null;
    const swapIconCs = swapIcon ? getComputedStyle(swapIcon) : null;

    const shellBox = shell?.getBoundingClientRect();
    const fieldBox = field?.getBoundingClientRect();
    const tabBox = tab?.getBoundingClientRect();
    const btnBox = btn?.getBoundingClientRect();
    const swapBox = swap?.getBoundingClientRect();

    return {
      outerWidth: shellBox?.width ?? 0,
      outerHeight: shellBox?.height ?? 0,
      topPadding: shellCs ? parseFloat(shellCs.paddingTop || '0') : 0,
      bottomPadding: shellCs ? parseFloat(shellCs.paddingBottom || '0') : 0,
      fieldHeight: fieldBox?.height ?? 0,
      searchButtonHeight: btnBox?.height ?? 0,
      tabHeight: tabBox?.height ?? 0,
      swapSize: swapBox?.width ?? 0,
      swapIconSize: swapIconCs ? parseFloat(swapIconCs.width || '0') : 0,
      rowGap: rowCs ? parseFloat(rowCs.gap || rowCs.columnGap || '0') : 0,
      firstFieldWidth: fieldBox?.width ?? 0,
      labelSize: labelCs ? parseFloat(labelCs.fontSize || '0') : 0,
      valueSize: valueCs ? parseFloat(valueCs.fontSize || '0') : 0,
      radius: fieldCs ? parseFloat(fieldCs.borderTopLeftRadius || '0') : 0,
      cssScale: root ? getComputedStyle(root).getPropertyValue('--jp-search-ui-scale').trim() : '',
    };
  });
}

async function applyScale(page: Page, scale: number): Promise<void> {
  await page.evaluate((nextScale) => {
    const root = document.querySelector('.jp-home') as HTMLElement | null;
    if (root) {
      root.style.setProperty('--jp-search-ui-scale', String(nextScale));
    }
  }, scale);
  await page.waitForTimeout(120);
}

test.describe('homepage search UI vertical scale contract', () => {
  test.beforeAll(() => {
    fs.mkdirSync(path.join(artifactDir, 'desktop'), { recursive: true });
    fs.mkdirSync(path.join(artifactDir, 'mobile'), { recursive: true });
  });

  test('outer width stays invariant while vertical dimensions scale', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${HOME_PATH}?_vscale=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90_000 });
    await page.waitForSelector('#jp-flight-search .jp-airport-field', { timeout: 60_000 });

    const table: Record<string, Metrics> = {};

    for (const step of scales) {
      await applyScale(page, step.value);
      table[step.label] = await readMetrics(page);
      await page.screenshot({
        path: path.join(artifactDir, 'desktop', `search-vertical-scale-${step.label}.png`),
        fullPage: false,
      });
    }

    fs.writeFileSync(path.join(artifactDir, 'desktop-metrics.json'), JSON.stringify(table, null, 2));

    const widths = scales.map((s) => table[s.label].outerWidth);
    const fieldWidths = scales.map((s) => table[s.label].firstFieldWidth);
    const refWidth = widths[0];

    for (const width of widths) {
      expect(Math.abs(width - refWidth)).toBeLessThanOrEqual(1);
    }
    for (const width of fieldWidths) {
      expect(Math.abs(width - fieldWidths[0])).toBeLessThanOrEqual(1);
    }

    const m80 = table['80'];
    const m90 = table['90'];
    const m100 = table['100'];
    const m115 = table['115'];

    expect(m80.outerHeight).toBeLessThan(m90.outerHeight);
    expect(m90.outerHeight).toBeLessThan(m100.outerHeight);
    expect(m100.outerHeight).toBeLessThan(m115.outerHeight);

    expect(m80.fieldHeight).toBeLessThan(m90.fieldHeight);
    expect(m90.fieldHeight).toBeLessThan(m100.fieldHeight);
    expect(m100.fieldHeight).toBeLessThan(m115.fieldHeight);

    expect(m80.searchButtonHeight).toBeLessThan(m90.searchButtonHeight);
    expect(m90.searchButtonHeight).toBeLessThan(m100.searchButtonHeight);
    expect(m100.searchButtonHeight).toBeLessThan(m115.searchButtonHeight);

    expect(m80.swapIconSize).toBeLessThan(m115.swapIconSize);

    expect(m90.fieldHeight - m80.fieldHeight).toBeGreaterThanOrEqual(2);
    expect(m100.fieldHeight - m90.fieldHeight).toBeGreaterThanOrEqual(2);

    expect(m80.cssScale).toBe('0.8');
    expect(m100.cssScale).toBe('1');
    expect(m115.cssScale).toBe('1.15');
  });

  test('mobile keeps touch-safe heights while vertical scaling', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${HOME_PATH}?_vscale_mobile=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90_000 });
    await page.waitForSelector('#jp-flight-search .jp-airport-field', { timeout: 60_000 });

    const widths: number[] = [];

    for (const step of [scales[0], scales[2], scales[3]]) {
      await applyScale(page, step.value);
      const metrics = await readMetrics(page);
      widths.push(metrics.outerWidth);
      expect(metrics.fieldHeight).toBeGreaterThanOrEqual(44);
      expect(metrics.searchButtonHeight).toBeGreaterThanOrEqual(44);
      await page.screenshot({
        path: path.join(artifactDir, 'mobile', `search-vertical-scale-${step.label}.png`),
        fullPage: false,
      });
    }

    for (const width of widths) {
      expect(Math.abs(width - widths[0])).toBeLessThanOrEqual(1);
    }
  });
});
