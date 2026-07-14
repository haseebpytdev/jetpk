import { test, expect } from '@playwright/test';

const authDir = 'storage/app/playwright/jetpk-9h-b/auth';
const forbiddenBrands = ['Parwaaz', 'YD Travel', 'haseeb-master', 'haseebasif.com'];

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

test.describe('JetPakistan themed customer dashboard', () => {
  test.use({ storageState: `${authDir}/customer.json` });

  test('desktop: jp-portal shell, KPIs, sections, quick actions', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto('/customer', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);
    expect(page.url()).not.toMatch(/\/login/);

    await expect(page.locator('.jp-portal__top').first()).toBeVisible();
    await expect(page.getByTestId('jp-customer-dashboard')).toBeVisible();
    await expect(page.getByTestId('jp-customer-dashboard-kpis').locator('.jp-portal-stat')).toHaveCount(4);
    await expect(page.getByTestId('jp-customer-dashboard-quick-actions').locator('a')).not.toHaveCount(0);

    const quickLinks = page.getByTestId('jp-customer-dashboard-quick-actions').locator('a');
    const hrefs = await quickLinks.evaluateAll((anchors) =>
      anchors.map((a) => (a as HTMLAnchorElement).getAttribute('href')).filter(Boolean),
    );
    expect(hrefs.length).toBeGreaterThan(0);
    for (const href of hrefs) {
      expect(href).not.toMatch(/^https?:\/\//);
      expect(href).not.toBe('#');
    }

    const pending = page.getByTestId('jp-customer-dashboard-pending-alert');
    const upcoming = page.getByTestId('jp-customer-dashboard-upcoming');
    const recent = page.getByTestId('jp-customer-recent-bookings');
    const emptyTitle = page.getByText('No bookings yet');

    const hasPending = (await pending.count()) > 0;
    const hasUpcoming = (await upcoming.count()) > 0;
    const hasRecent = (await recent.count()) > 0;
    const hasEmpty = (await emptyTitle.count()) > 0;

    expect(hasPending || hasUpcoming || hasRecent || hasEmpty).toBeTruthy();

    await assertNoHorizontalOverflow(page);
    await assertNoBrandLeak(page);
  });

  test('mobile: customer dashboard shell without horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/customer', { waitUntil: 'domcontentloaded' });

    const mobileDashboard = page.getByTestId('ota-mobile-customer-dashboard');
    const jpPortal = page.locator('.jp-portal__top');
    if ((await mobileDashboard.count()) > 0) {
      await expect(mobileDashboard).toBeVisible();
      await expect(page.getByTestId('ota-mobile-customer-dashboard-stats')).toBeVisible();
    } else {
      await expect(jpPortal.first()).toBeVisible();
      await expect(page.getByTestId('jp-customer-dashboard')).toBeVisible();
      await expect(page.getByTestId('jp-customer-dashboard-kpis')).toBeVisible();
    }

    await assertNoHorizontalOverflow(page);
    await assertNoBrandLeak(page);
  });

  test('bookings index: filters and list or empty state', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto('/customer/bookings?filter=pending_payment', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);
    expect(page.url()).not.toMatch(/\/login/);

    await expect(page.locator('.jp-portal__top').first()).toBeVisible();
    await expect(page.getByTestId('customer-bookings-filters')).toBeVisible();
    await expect(page.getByTestId('customer-bookings-filters').locator('a.is-active')).toHaveText(/Pending payment/i);

    const list = page.getByTestId('jp-customer-bookings-list');
    const empty = page.getByText('No bookings found');
    expect((await list.count()) > 0 || (await empty.count()) > 0).toBeTruthy();

    await assertNoHorizontalOverflow(page);
    await assertNoBrandLeak(page);
  });
});
