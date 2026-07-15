/**
 * Proposed safe test — agent dashboard + bookings (Phase 4)
 * JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4
 *
 * SAFETY: local/deterministic only. Uses the repo's EXISTING agent fixture + seeded booking
 * (do not hardcode production creds). NO live supplier search, NO real booking/PNR, NO
 * payment/email. Exclude from any *-live.config.ts run. Wire loginAsAgent() + a seeded
 * booking ref before running; describes are skipped until then.
 */
import { test, expect, Page } from '@playwright/test';

async function loginAsAgent(page: Page): Promise<{ bookingRef: string } | null> {
  test.skip(true, 'Wire loginAsAgent() + a seeded agency booking, then remove this skip.');
  return null;
}

const SMALL = { width: 360, height: 800 };
const DESKTOP = { width: 1440, height: 900 };

test.describe('agent bookings index', () => {
  test('renders breadcrumbs + filter toolbar and rows-or-empty', async ({ page }) => {
    await loginAsAgent(page);
    await page.setViewportSize(DESKTOP);
    await page.goto('/agent/bookings');

    await expect(page.locator('.ota-dashboard-breadcrumbs')).toBeVisible();
    await expect(page.getByTestId('agent-bookings-filters')).toBeVisible();
    const rows = page.locator('.ota-account-table tbody tr');
    expect(await rows.count()).toBeGreaterThan(0); // includes the empty-state row
  });

  test('no horizontal overflow at 360 / desktop', async ({ page }) => {
    await loginAsAgent(page);
    for (const vp of [SMALL, DESKTOP]) {
      await page.setViewportSize(vp);
      await page.goto('/agent/bookings');
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
      );
      expect(overflow, `viewport ${vp.width}px`).toBeLessThanOrEqual(1);
    }
  });
});

test.describe('agent booking detail', () => {
  test('renders breadcrumbs + the detail-* layout', async ({ page }) => {
    const seed = await loginAsAgent(page);
    await page.setViewportSize(DESKTOP);
    await page.goto(`/agent/bookings/${seed!.bookingRef}`);
    await expect(page.locator('.ota-dashboard-breadcrumbs')).toBeVisible();
    await expect(page.locator('.ota-account-detail-grid')).toBeVisible();
  });
});
