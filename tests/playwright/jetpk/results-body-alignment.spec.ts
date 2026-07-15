/**
 * JETPK-FLIGHT-RESULTS-BODY-HEADER-WIDTH-ALIGNMENT
 * Assert results body containers share header .wrap left/right boundaries.
 */
import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { futureDepartDate } from './helpers/constants';

const OUT = 'storage/app/audits/jetpk-results-body-alignment';

const HEADER_CONTAINER = '.jp-site-header .wrap.jp-header-container, .header .wrap.jp-header-container';
const RESULTS_BODY = '.jp-flights-results__container.ota-results-pro-body';
const RESULTS_HEADING = '.jp-flights-results__container:not(.ota-results-pro-body)';
const RESULTS_ROOT = '[data-results-root]';
const RESULTS_LIST = '[data-results-list]';

const DESKTOP_VIEWPORTS = [
  { name: 'desktop1920', width: 1920, height: 1080 },
  { name: 'desktop1440', width: 1440, height: 900 },
  { name: 'desktop1366', width: 1366, height: 768 },
  { name: 'desktop1280', width: 1280, height: 720 },
  { name: 'desktop1024', width: 1024, height: 768 },
];

const TABLET_VIEWPORT = { name: 'tablet768', width: 768, height: 1024 };

function resultsPath(): string {
  const depart = futureDepartDate();
  return `/flights/results?trip_type=one_way&from=ISB&to=KHI&from_display=Islamabad&to_display=Karachi&depart=${depart}&adults=1&children=0&infants=0&cabin=economy`;
}

type Box = { left: number; right: number; width: number };

async function measureBox(page: import('@playwright/test').Page, selector: string): Promise<Box | null> {
  return page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const r = el.getBoundingClientRect();
    return { left: r.left, right: r.right, width: r.width };
  }, selector);
}

async function assertAlignment(
  page: import('@playwright/test').Page,
  resultsSelector: string,
  label: string,
): Promise<{ header: Box; results: Box; deltaLeft: number; deltaRight: number }> {
  const header = await measureBox(page, HEADER_CONTAINER);
  const results = await measureBox(page, resultsSelector);

  expect(header, `${label}: header container`).not.toBeNull();
  expect(results, `${label}: results container`).not.toBeNull();

  const deltaLeft = Math.abs(header!.left - results!.left);
  const deltaRight = Math.abs(header!.right - results!.right);

  expect(deltaLeft, `${label}: left edge delta`).toBeLessThanOrEqual(2);
  expect(deltaRight, `${label}: right edge delta`).toBeLessThanOrEqual(2);

  return { header: header!, results: results!, deltaLeft, deltaRight };
}

async function assertResultsColumnFill(page: import('@playwright/test').Page, label: string): Promise<void> {
  const metrics = await page.evaluate(() => {
    const root = document.querySelector('[data-results-root]');
    const list = document.querySelector('[data-results-list]');
    const card = document.querySelector('[data-results-list] .jp-result-card, [data-results-list] .ota-result-pro-card');
    const rootCs = root ? getComputedStyle(root) : null;
    const measure = (el: Element | null) => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { left: r.left, right: r.right, width: r.width };
    };
    return {
      root: measure(root),
      list: measure(list),
      card: measure(card),
      rootMaxWidth: rootCs?.maxWidth ?? null,
      rootWidth: rootCs?.width ?? null,
    };
  });

  expect(metrics.root, `${label}: results root`).not.toBeNull();
  expect(metrics.list, `${label}: results list`).not.toBeNull();

  const deadSpace = metrics.root!.right - (metrics.list?.right ?? metrics.root!.right);
  expect(deadSpace, `${label}: right-column dead space`).toBeLessThanOrEqual(2);
  expect(Math.abs(metrics.root!.width - metrics.list!.width), `${label}: list vs column width`).toBeLessThanOrEqual(
    2,
  );
  expect(metrics.rootMaxWidth, `${label}: results root max-width`).toBe('none');
}

test.describe('JetPK results body / header width alignment', () => {
  test.beforeAll(() => {
    fs.mkdirSync(path.join(OUT, 'screenshots'), { recursive: true });
  });

  for (const vp of DESKTOP_VIEWPORTS) {
    test(`desktop alignment @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto(`${resultsPath()}&_align=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
      await page.waitForSelector(HEADER_CONTAINER, { timeout: 60_000 });
      await page.waitForSelector(RESULTS_BODY, { timeout: 60_000 });
      await page.waitForSelector(RESULTS_LIST, { timeout: 60_000 });

      const body = await assertAlignment(page, RESULTS_BODY, vp.name);
      await assertAlignment(page, RESULTS_HEADING, `${vp.name} heading`);
      await assertResultsColumnFill(page, vp.name);

      const overflow = await page.evaluate(() => {
        const doc = document.documentElement;
        return Math.max(doc.scrollWidth - doc.clientWidth, 0);
      });
      expect(overflow, 'horizontal overflow').toBeLessThanOrEqual(1);

      await page.screenshot({
        path: path.join(OUT, 'screenshots', `${vp.name}-aligned.png`),
        fullPage: true,
      });

      fs.writeFileSync(
        path.join(OUT, `${vp.name}-metrics.json`),
        JSON.stringify({ viewport: vp, body, overflow }, null, 2),
      );
    });
  }

  test('tablet alignment preserves gutters without overflow', async ({ page }) => {
    await page.setViewportSize({ width: TABLET_VIEWPORT.width, height: TABLET_VIEWPORT.height });
    await page.goto(`${resultsPath()}&_align=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.waitForSelector(HEADER_CONTAINER, { timeout: 60_000 });
    await page.waitForSelector(RESULTS_BODY, { timeout: 60_000 });

    await assertAlignment(page, RESULTS_BODY, TABLET_VIEWPORT.name);

    const overflow = await page.evaluate(() => {
      const doc = document.documentElement;
      return Math.max(doc.scrollWidth - doc.clientWidth, 0);
    });
    expect(overflow).toBeLessThanOrEqual(1);

    await page.screenshot({
      path: path.join(OUT, 'screenshots', `${TABLET_VIEWPORT.name}-aligned.png`),
      fullPage: true,
    });
  });
});
