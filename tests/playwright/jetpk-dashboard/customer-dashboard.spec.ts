import { test, expect } from '@playwright/test';

const authDir = 'storage/app/playwright/jetpk-9h-b/auth';

test.describe('customer dashboard home (legacy OTA shell)', () => {
  test.use({ storageState: `${authDir}/customer.json` });

  test('renders KPI cards when legacy OTA shell active', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/customer', { waitUntil: 'domcontentloaded' });

    const dashboard = page.getByTestId('customer-dashboard');
    if ((await dashboard.count()) === 0) {
      test.skip(true, 'JetPK theme dashboard active — legacy testids not present');
    }

    await expect(dashboard).toBeVisible();
    await expect(page.getByTestId('customer-dashboard-kpis')).toBeVisible();
    await expect(page.getByTestId('customer-dashboard-quick-actions').locator('a')).not.toHaveCount(0);
  });
});

test.describe('customer bookings index (legacy OTA shell)', () => {
  test.use({ storageState: `${authDir}/customer.json` });

  test('filter tabs preserve active filter', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/customer/bookings?filter=pending_payment', { waitUntil: 'domcontentloaded' });

    const filters = page.getByTestId('customer-bookings-filters');
    const jpPortal = page.locator('.jp-portal__top');
    if ((await filters.count()) === 0 && (await jpPortal.count()) > 0) {
      test.skip(true, 'JetPK theme bookings index active');
    }

    await expect(filters).toBeVisible();
    await expect(filters.locator('a.is-active, .is-active')).toHaveText(/Pending payment/i);
  });
});
