import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const HOME_PATH = process.env.JETPK_HOME_PATH ?? '/';
const artifactDir = path.join('tests', 'playwright', 'artifacts', 'homepage-search-ui-scale');
const scales = [
  { label: '80', value: 0.8 },
  { label: '90', value: 0.9 },
  { label: '100', value: 1.0 },
  { label: '115', value: 1.15 },
] as const;

type Metrics = {
  outerWidth: number;
  outerHeight: number;
  outerPadding: number;
  fieldHeight: number;
  tabHeight: number;
  searchButtonHeight: number;
  swapSize: number;
  rowGap: number;
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

    const shellCs = shell ? getComputedStyle(shell) : null;
    const fieldCs = field ? getComputedStyle(field) : null;
    const tabCs = tab ? getComputedStyle(tab) : null;
    const btnCs = btn ? getComputedStyle(btn) : null;
    const swapCs = swap ? getComputedStyle(swap) : null;
    const rowCs = fieldsRow ? getComputedStyle(fieldsRow) : null;
    const labelCs = label ? getComputedStyle(label) : null;
    const valueCs = input ? getComputedStyle(input) : null;

    const shellBox = shell?.getBoundingClientRect();
    const fieldBox = field?.getBoundingClientRect();
    const tabBox = tab?.getBoundingClientRect();
    const btnBox = btn?.getBoundingClientRect();
    const swapBox = swap?.getBoundingClientRect();

    const pad = shellCs
      ? ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft']
          .map((k) => parseFloat((shellCs as unknown as Record<string, string>)[k] || '0'))
          .reduce((a, b) => a + b, 0)
      : 0;

    return {
      outerWidth: shellBox?.width ?? 0,
      outerHeight: shellBox?.height ?? 0,
      outerPadding: pad,
      fieldHeight: fieldBox?.height ?? 0,
      tabHeight: tabBox?.height ?? 0,
      searchButtonHeight: btnBox?.height ?? 0,
      swapSize: swapBox?.width ?? 0,
      rowGap: rowCs ? parseFloat(rowCs.gap || rowCs.columnGap || '0') : 0,
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

test.describe('homepage search UI scale visual contract', () => {
  test.beforeAll(() => {
    fs.mkdirSync(path.join(artifactDir, 'desktop'), { recursive: true });
    fs.mkdirSync(path.join(artifactDir, 'mobile'), { recursive: true });
  });

  test('desktop computed dimensions change proportionally across slider range', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${HOME_PATH}?_scale=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90_000 });
    await page.waitForSelector('#jp-flight-search .jp-airport-field', { timeout: 60_000 });

    const table: Record<string, Metrics> = {};

    for (const step of scales) {
      await applyScale(page, step.value);
      table[step.label] = await readMetrics(page);
      await page.screenshot({
        path: path.join(artifactDir, 'desktop', `search-scale-${step.label}.png`),
        fullPage: false,
      });
    }

    fs.writeFileSync(path.join(artifactDir, 'desktop-metrics.json'), JSON.stringify(table, null, 2));

    const m80 = table['80'];
    const m90 = table['90'];
    const m100 = table['100'];
    const m115 = table['115'];

    expect(m80.fieldHeight).toBeLessThan(m90.fieldHeight);
    expect(m90.fieldHeight).toBeLessThan(m100.fieldHeight);
    expect(m100.fieldHeight).toBeLessThan(m115.fieldHeight);

    expect(m80.outerWidth).toBeLessThan(m90.outerWidth);
    expect(m90.outerWidth).toBeLessThan(m100.outerWidth);
    expect(m100.outerWidth).toBeLessThan(m115.outerWidth);

    expect(m90.fieldHeight - m80.fieldHeight).toBeGreaterThanOrEqual(2);
    expect(m100.fieldHeight - m90.fieldHeight).toBeGreaterThanOrEqual(2);
    expect(m115.outerHeight).toBeGreaterThan(m80.outerHeight);

    expect(m80.cssScale).toBe('0.8');
    expect(m100.cssScale).toBe('1');
    expect(m115.cssScale).toBe('1.15');
  });

  test('mobile keeps touch-safe heights while scaling', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${HOME_PATH}?_scale_mobile=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90_000 });
    await page.waitForSelector('#jp-flight-search .jp-airport-field', { timeout: 60_000 });

    for (const step of [scales[0], scales[2], scales[3]]) {
      await applyScale(page, step.value);
      const metrics = await readMetrics(page);
      expect(metrics.fieldHeight).toBeGreaterThanOrEqual(44);
      expect(metrics.searchButtonHeight).toBeGreaterThanOrEqual(44);
      await page.screenshot({
        path: path.join(artifactDir, 'mobile', `search-scale-${step.label}.png`),
        fullPage: false,
      });
    }
  });
});
