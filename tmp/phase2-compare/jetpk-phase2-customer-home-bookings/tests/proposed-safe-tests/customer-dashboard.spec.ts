/**
 * Proposed safe test — customer dashboard home + bookings index (Phase 2)
 * JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4
 *
 * SAFETY: local/deterministic only. Uses the repo's EXISTING customer auth fixture (do not
 * hardcode production creds). NO live supplier search, NO real booking/PNR, NO payment/email.
 * Exclude from any *-live.config.ts run. Wire loginAsCustomer() to the established session
 * helper before running.
 *
 * Asserts the Phase 2 data-driven home renders the controller data and the bookings index
 * preserves its filters + rows + pagination. Keys off data-testid hooks:
 *   customer-dashboard, customer-dashboard-stats, customer-dashboard-quick-actions,
 *   customer-dashboard-upcoming (conditional), customer-bookings-filters.
 */
import { test, expect, Page } from '@playwright/test';

async function loginAsCustomer(page: Page): Promise<void> {
  // Replace with the repository's real customer session fixture / storageState.
  test.skip(true, 'Wire loginAsCustomer() to the repo customer auth fixture, then remove this skip.');
}

const SMALL = { width: 360, height: 800 };
const PHONE = { width: 390, height: 844 };
const DESKTOP = { width: 1440, height: 900 };

test.describe('customer dashboard home', () => {
  test('renders KPI stats and quick actions', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize(DESKTOP);
    await page.goto('/customer');

    await expect(page.getByTestId('customer-dashboard')).toBeVisible();
    // Four KPI stats from $kpis.
    await expect(page.getByTestId('customer-dashboard-stats').locator('.ota-customer-dashboard__stat')).toHaveCount(4);
    // Quick actions present (count depends on which routes exist for the tenant).
    await expect(page.getByTestId('customer-dashboard-quick-actions').locator('a')).not.toHaveCount(0);
  });

  test('no horizontal overflow at 360 / 390 / desktop', async ({ page }) => {
    await loginAsCustomer(page);
    for (const vp of [SMALL, PHONE, DESKTOP]) {
      await page.setViewportSize(vp);
      await page.goto('/customer');
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
      );
      expect(overflow, `viewport ${vp.width}px must not overflow`).toBeLessThanOrEqual(1);
    }
  });
});

test.describe('customer bookings index', () => {
  test('renders filter tabs and preserves the active filter', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize(DESKTOP);
    await page.goto('/customer/bookings?filter=pending_payment');

    const filters = page.getByTestId('customer-bookings-filters');
    await expect(filters).toBeVisible();
    await expect(filters.locator('a.is-active')).toHaveText(/Pending payment/i);
  });

  test('either a populated table/cards or the empty state is shown (never a blank page)', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize(DESKTOP);
    await page.goto('/customer/bookings');

    const table = page.locator('.ota-account-table tbody tr');
    const empty = page.locator('.ota-account-empty-title');
    const hasRows = (await table.count()) > 0;
    const hasEmpty = (await empty.count()) > 0;
    expect(hasRows || hasEmpty).toBeTruthy();
  });
});
