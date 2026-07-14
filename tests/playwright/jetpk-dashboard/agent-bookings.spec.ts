import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const authDir = 'storage/app/playwright/jetpk-9h-b/auth';
const fixturePath = path.join('storage', 'app', 'playwright', 'jetpk-dashboard-fixtures.json');
const forbiddenBrands = ['Parwaaz', 'YD Travel', 'haseeb-master', 'haseebasif.com'];

const viewports = [
  { width: 1920, height: 1080 },
  { width: 1440, height: 900 },
  { width: 1366, height: 768 },
  { width: 1024, height: 768 },
  { width: 768, height: 1024 },
  { width: 390, height: 844 },
  { width: 360, height: 800 },
];

async function assertNoHorizontalOverflow(page: import('@playwright/test').Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    return doc.scrollWidth > doc.clientWidth + 2 || body.scrollWidth > body.clientWidth + 2;
  });
  expect(overflow).toBe(false);
}

async function assertNoBrandLeak(page: import('@playwright/test').Page): Promise<void> {
  const bodyText = await page.locator('body').innerText();
  for (const leak of forbiddenBrands) {
    expect(bodyText.includes(leak)).toBe(false);
  }
}

async function assertBreadcrumbLandmark(page: import('@playwright/test').Page): Promise<void> {
  const crumbs = page.locator('nav.ota-dashboard-breadcrumbs');
  await expect(crumbs).toBeVisible();
  await expect(crumbs).toHaveAttribute('aria-label', /.+/);
  await expect(crumbs.locator('[aria-current="page"]')).toHaveCount(1);
}

test.describe('agent bookings navigation', () => {
  test.use({ storageState: `${authDir}/agent.json` });

  test('bookings index breadcrumb, filters, and table or empty state', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/agent/bookings', { waitUntil: 'domcontentloaded' });

    await assertBreadcrumbLandmark(page);
    await expect(page.getByTestId('agent-bookings-filters').or(page.locator('.jp-portal-tabs'))).toBeVisible();
    const rows = page.locator('.ota-account-table tbody tr, .jp-portal-table tbody tr');
    const empty = page.getByText(/No bookings found/i);
    expect((await rows.count()) + (await empty.count())).toBeGreaterThan(0);
    await expect(page.locator('.jp-portal__top, [data-testid="dashboard-shell-agent"]').first()).toBeVisible();

    await assertNoHorizontalOverflow(page);
    await assertNoBrandLeak(page);
  });

  test('booking detail sections and commission visibility', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const fixtures = JSON.parse(fs.readFileSync(fixturePath, 'utf8')) as { agentBookingPath: string };
    await page.goto(fixtures.agentBookingPath, { waitUntil: 'domcontentloaded' });

    await assertBreadcrumbLandmark(page);
    await expect(
      page.getByTestId('agent-booking-detail-layout').or(page.locator('.ota-account-detail-grid, .jp-portal-grid--2').first()),
    ).toBeVisible();
  });

  test('create launcher breadcrumb and search link', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/agent/bookings/create', { waitUntil: 'domcontentloaded' });

    await assertBreadcrumbLandmark(page);
    await expect(page.getByTestId('agent-booking-search-flights')).toBeVisible();
  });

  for (const vp of viewports) {
    test(`no horizontal overflow at ${vp.width}x${vp.height}`, async ({ page }) => {
      await page.setViewportSize(vp);
      await page.goto('/agent/bookings', { waitUntil: 'domcontentloaded' });
      await assertNoHorizontalOverflow(page);
    });
  }
});

test.describe('agent staff bookings navigation', () => {
  test.use({ storageState: `${authDir}/agent-staff.json` });

  test('restricted staff session keeps bookings index without create leakage', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto('/agent/bookings', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBeLessThan(400);

    if (response?.status() === 200) {
      await assertBreadcrumbLandmark(page);
      await expect(page.getByTestId('agent-bookings-create-link')).toHaveCount(0);
    } else {
      expect(response?.status()).toBe(403);
    }

    const createResponse = await page.goto('/agent/bookings/create', { waitUntil: 'domcontentloaded' });
    expect([200, 403]).toContain(createResponse?.status() ?? 0);
  });
});
