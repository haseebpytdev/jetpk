import { test, expect } from '@playwright/test';
import { gotoPublicPage } from './helpers/public-fast-navigation';

const MOBILE_WIDTHS = [
  { name: 'mobile360', width: 360, height: 800 },
  { name: 'mobile390', width: 390, height: 844 },
  { name: 'mobile414', width: 414, height: 896 },
  { name: 'mobile430', width: 430, height: 932 },
];

const TABLET_WIDTHS = [
  { name: 'tablet768', width: 768, height: 1024 },
  { name: 'tablet991', width: 991, height: 1024 },
];

const DESKTOP_WIDTH = { name: 'desktop1024', width: 1024, height: 800 };

function futureDepart(daysAhead = 21): string {
  const d = new Date();
  d.setDate(d.getDate() + daysAhead);
  return d.toISOString().slice(0, 10);
}

const resultsQuery = `from=LHE&to=DXB&depart=${futureDepart()}&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0`;

async function assertNoHorizontalOverflow(page: import('@playwright/test').Page) {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return doc.scrollWidth > doc.clientWidth + 1;
  });
  expect(overflow, 'document horizontal overflow').toBe(false);
}

test.describe('mobile flight OTA UI', () => {
  for (const vp of MOBILE_WIDTHS) {
    test(`home search shell @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await gotoPublicPage(page, '/', 'home');
      await expect(page.locator('.ota-main-nav, .ota-slim-topbar').first()).toBeVisible();
      await expect(page.locator('.ota-mobile-home-trust-bar')).toBeVisible();
      await expect(page.locator('.ota-hero-search-field--from .ota-mobile-airport-viz__sub')).toBeVisible();
      await expect(page.locator('.ota-hero-search-field--from .ota-mobile-airport-shell')).toBeVisible();
      await expect(page.locator('footer, .ota-site-footer').first()).toBeVisible();
      await expect(page.locator('#routes')).toBeHidden();
      await expect(page.locator('#why')).toBeHidden();
      await expect(page.locator('#fares')).toBeHidden();
      await expect(page.locator('.ota-mobile-home-metrics')).toBeVisible();
      await expect(page.locator('.ota-home-desktop-content .ota-metrics-band')).toBeHidden();
      const submit = page.locator('.ota-mobile-search-submit').first();
      await expect(submit).toBeVisible();
      const searchCardBox = await page.locator('.ota-hero-search-card').first().boundingBox();
      const minViewportFieldWidth = vp.width * 0.85;
      const minCardFieldWidth = searchCardBox ? searchCardBox.width * 0.88 : minViewportFieldWidth;
      const fromBox = await page.locator('.ota-hero-search-field--from').first().boundingBox();
      const toBox = await page.locator('.ota-hero-search-field--to').first().boundingBox();
      const departBox = await page
        .locator('.ota-hero-search-field--depart, [data-return-date-part="depart"]')
        .first()
        .boundingBox();
      const paxBox = await page.locator('.ota-hero-search-field--pax').first().boundingBox();
      const submitBox = await submit.boundingBox();
      expect(fromBox && toBox && departBox && paxBox && submitBox && searchCardBox).toBeTruthy();
      for (const box of [fromBox, toBox, departBox, paxBox, submitBox]) {
        if (box) {
          expect(box.width).toBeGreaterThanOrEqual(minViewportFieldWidth);
          expect(box.width).toBeGreaterThanOrEqual(minCardFieldWidth);
        }
      }
      if (paxBox && submitBox) {
        expect(submitBox.y).toBeGreaterThan(paxBox.y);
      }
      if (searchCardBox) {
        expect(searchCardBox.width).toBeGreaterThan(vp.width * 0.82);
      }
      await assertNoHorizontalOverflow(page);
      await page.screenshot({
        path: `test-results/mobile-home-search-fixed-${vp.width}.png`,
        fullPage: true,
      });
    });

    test(`standalone search @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await gotoPublicPage(page, '/flights/search', 'flights-search');
      await expect(page.locator('[data-flight-search-form]').first()).toBeVisible();
      await assertNoHorizontalOverflow(page);
    });

    test(`results mobile chrome @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await gotoPublicPage(page, `/flights/results?${resultsQuery}`, 'flights-results');
      await expect(page.locator('.ota-mobile-bottom-bar')).toBeVisible();
      await expect(page.locator('.ota-mobile-results-list')).toBeVisible();
      await assertNoHorizontalOverflow(page);
    });
  }

  for (const vp of TABLET_WIDTHS) {
    test(`home desktop search visible @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await gotoPublicPage(page, '/', 'home');
      await expect(page.locator('.ota-hero-search-submit').first()).toBeVisible();
      await expect(page.locator('.ota-mobile-flight-card')).toHaveCount(0);
      await assertNoHorizontalOverflow(page);
    });

    test(`results sticky bar @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await gotoPublicPage(page, `/flights/results?${resultsQuery}`, 'flights-results');
      await expect(page.locator('.ota-mobile-bottom-bar')).toBeVisible();
      await assertNoHorizontalOverflow(page);
    });
  }

  test('desktop 1024 keeps desktop result layout', async ({ page }) => {
    await page.setViewportSize(DESKTOP_WIDTH);
    await gotoPublicPage(page, `/flights/results?${resultsQuery}`, 'flights-results');
    await expect(page.locator('.ota-mobile-flight-card')).toHaveCount(0);
    await expect(page.locator('.ota-mobile-flight-details')).toHaveCount(0);
    await assertNoHorizontalOverflow(page);
  });

  test('desktop 1024 keeps full homepage sections', async ({ page }) => {
    await page.setViewportSize(DESKTOP_WIDTH);
    await gotoPublicPage(page, '/', 'home');
    await expect(page.locator('.ota-hero-search-submit').first()).toBeVisible();
    const routes = page.locator('#routes');
    const why = page.locator('#why');
    const fares = page.locator('#fares');
    if ((await routes.count()) > 0) {
      await expect(routes).toBeVisible();
    }
    if ((await why.count()) > 0) {
      await expect(why).toBeVisible();
    }
    if ((await fares.count()) > 0) {
      await expect(fares).toBeVisible();
    }
    await expect(page.locator('.ota-mobile-home-trust-bar')).toBeHidden();
    await expect(page.locator('.ota-mobile-home-metrics')).toBeHidden();
    await assertNoHorizontalOverflow(page);
  });
});
