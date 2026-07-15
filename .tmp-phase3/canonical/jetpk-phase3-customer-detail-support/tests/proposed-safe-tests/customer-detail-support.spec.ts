/**
 * Proposed safe test — customer booking detail + support (Phase 3)
 * JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4
 *
 * SAFETY: local/deterministic only. Uses the repo's EXISTING customer fixture + seeded
 * booking/ticket (do not hardcode production creds). NO live supplier search, NO real
 * booking/PNR, NO payment/email. Exclude from any *-live.config.ts run.
 *
 * Verifies the Phase 3 breadcrumb gap-fill and that the already-canonical detail/support
 * structure still renders. Wire the fixtures (loginAsCustomer + a seeded booking id / ticket
 * id) before running; the describe blocks are skipped until then.
 */
import { test, expect, Page } from '@playwright/test';

async function loginAsCustomer(page: Page): Promise<{ bookingRef: string; ticketId: number } | null> {
  // Replace with the repo's customer session fixture + a seeded booking/ticket.
  test.skip(true, 'Wire loginAsCustomer() + seeded booking/ticket, then remove this skip.');
  return null;
}

const SMALL = { width: 360, height: 800 };
const DESKTOP = { width: 1440, height: 900 };

test.describe('customer booking detail', () => {
  test('renders breadcrumbs + the detail layout', async ({ page }) => {
    const seed = await loginAsCustomer(page);
    await page.setViewportSize(DESKTOP);
    await page.goto(`/customer/bookings/${seed!.bookingRef}`);

    const crumbs = page.locator('.ota-dashboard-breadcrumbs');
    await expect(crumbs).toBeVisible();
    await expect(crumbs.locator('[aria-current="page"]')).toHaveCount(1);
    await expect(page.getByTestId('customer-booking-detail-layout')).toBeVisible();
  });

  test('no horizontal overflow at 360 / desktop', async ({ page }) => {
    const seed = await loginAsCustomer(page);
    for (const vp of [SMALL, DESKTOP]) {
      await page.setViewportSize(vp);
      await page.goto(`/customer/bookings/${seed!.bookingRef}`);
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
      );
      expect(overflow, `viewport ${vp.width}px`).toBeLessThanOrEqual(1);
    }
  });
});

test.describe('customer support', () => {
  test('ticket detail shows breadcrumbs, timeline, and a gated reply form', async ({ page }) => {
    const seed = await loginAsCustomer(page);
    await page.setViewportSize(DESKTOP);
    await page.goto(`/customer/support/tickets/${seed!.ticketId}`);

    await expect(page.locator('.ota-dashboard-breadcrumbs')).toBeVisible();
    // Reply form is present only when policy allows (open, owned ticket).
    const reply = page.getByTestId('customer-support-reply-form');
    const closedNote = page.getByText(/This ticket is finalised/i);
    expect((await reply.count()) + (await closedNote.count())).toBeGreaterThan(0);
  });

  test('tickets index shows rows or empty state (never blank)', async ({ page }) => {
    await loginAsCustomer(page);
    await page.setViewportSize(DESKTOP);
    await page.goto('/customer/support/tickets');
    const rows = page.locator('[data-testid="customer-support-tickets-table"] tbody tr');
    const empty = page.getByTestId('customer-support-tickets-empty');
    expect((await rows.count()) + (await empty.count())).toBeGreaterThan(0);
  });
});
