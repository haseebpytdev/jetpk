import { test, expect } from '@playwright/test';

const authDir = 'storage/app/playwright/jetpk-9h-b/auth';
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

test.describe('customer detail + support navigation', () => {
  test.use({ storageState: `${authDir}/customer.json` });

  test('booking detail: breadcrumbs, sections, themed shell', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/customer', { waitUntil: 'domcontentloaded' });

    const detailNav = page
      .locator('a[href*="/customer/bookings/"]')
      .filter({ hasNotText: /payment proof|#payment/i })
      .first();

    if ((await detailNav.count()) === 0) {
      await page.goto('/customer/bookings', { waitUntil: 'domcontentloaded' });
    }

    const bookingEntry = page
      .locator('a[href*="/customer/bookings/"], a.jp-portal-trip[href*="/bookings/"]')
      .filter({ hasNotText: /create/i })
      .first();
    await expect(bookingEntry).toBeVisible();
    await bookingEntry.click();

    await assertBreadcrumbLandmark(page);
    await expect(page.getByTestId('customer-booking-detail-layout')).toBeVisible();
    await expect(page.locator('.jp-portal__top, [data-testid="dashboard-shell-customer"]').first()).toBeVisible();

    await assertNoHorizontalOverflow(page);
    await assertNoBrandLeak(page);
  });

  test('support index/create/show surfaces', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });

    await page.goto('/customer/support/tickets', { waitUntil: 'domcontentloaded' });
    await assertBreadcrumbLandmark(page);
    const table = page.getByTestId('customer-support-tickets-table');
    const empty = page.getByTestId('customer-support-tickets-empty');
    expect((await table.count()) + (await empty.count())).toBeGreaterThan(0);

    await page.goto('/customer/support/tickets/create', { waitUntil: 'domcontentloaded' });
    await assertBreadcrumbLandmark(page);
    await expect(page.getByTestId('customer-support-ticket-form')).toBeVisible();

    const viewLink = page.locator('a[href*="/customer/support/tickets/"]').filter({ hasNotText: 'create' }).first();
    if ((await viewLink.count()) > 0) {
      await viewLink.click();
      await assertBreadcrumbLandmark(page);
      const reply = page.getByTestId('customer-support-reply-form');
      const closedNote = page.getByText(/This ticket is finalised/i);
      expect((await reply.count()) + (await closedNote.count())).toBeGreaterThan(0);
    }
  });

  test('profile settings breadcrumb and universal settings', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/profile', { waitUntil: 'domcontentloaded' });

    await assertBreadcrumbLandmark(page);
    await expect(page.locator('input[name="name"], #name').first()).toBeVisible();
    await assertNoHorizontalOverflow(page);
  });

  for (const vp of viewports) {
    test(`no horizontal overflow at ${vp.width}x${vp.height}`, async ({ page }) => {
      await page.setViewportSize(vp);
      await page.goto('/customer/support/tickets', { waitUntil: 'domcontentloaded' });
      await assertNoHorizontalOverflow(page);
    });
  }
});
