/**
 * Proposed safe test — Agent Staff permission scoping + denied UX (Phase 6)
 * JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4
 *
 * SAFETY: local/deterministic only. Requires a LIMITED agent_staff fixture (e.g. granted
 * BookingsView only) and an agency-admin fixture. NO live supplier calls, NO real bookings/PNR,
 * NO payments, NO email. Exclude from any *-live.config.ts run.
 *
 * Verifies the Phase 0 Agent Staff contract:
 *   1. denied deep-links produce a portal-aware 403 (not a dead end);
 *   2. gated nav links are hidden for a limited user (desktop + mobile);
 *   3. permitted pages still work (no over-blocking);
 *   4. agency admins still see admin-only surfaces (no over-hiding).
 */
import { test, expect, Page } from '@playwright/test';

async function loginAsLimitedAgentStaff(page: Page): Promise<void> {
  // Wire to a seeded agent_staff user granted BookingsView ONLY.
  test.skip(true, 'Wire loginAsLimitedAgentStaff() (agent_staff, BookingsView only), then remove.');
}

async function loginAsAgencyAdmin(page: Page): Promise<void> {
  test.skip(true, 'Wire loginAsAgencyAdmin() (account_type: agent), then remove.');
}

const DESKTOP = { width: 1440, height: 900 };
const PHONE = { width: 390, height: 844 };

test.describe('agent_staff — denied deep-links', () => {
  for (const path of ['/agent/wallet', '/agent/commissions', '/agent/staff']) {
    test(`${path} is denied with a portal-aware 403`, async ({ page }) => {
      await loginAsLimitedAgentStaff(page);
      await page.setViewportSize(DESKTOP);
      const res = await page.goto(path);
      expect(res?.status()).toBe(403);

      // Portal-aware return action + Permission Matrix hint (Phase 6).
      await expect(page.getByTestId('denied-return-agent-portal')).toBeVisible();
      await expect(page.getByTestId('agent-staff-denied-hint')).toContainText(/Permission Matrix/i);
    });
  }

  test('the denied page returns the user to the agent portal', async ({ page }) => {
    await loginAsLimitedAgentStaff(page);
    await page.goto('/agent/wallet');
    await page.getByTestId('denied-return-agent-portal').click();
    await expect(page).toHaveURL(/\/agent\/?$/);
  });
});

test.describe('agent_staff — navigation is permission-scoped', () => {
  test('gated links are hidden on desktop', async ({ page }) => {
    await loginAsLimitedAgentStaff(page);
    await page.setViewportSize(DESKTOP);
    await page.goto('/agent');

    const nav = page.getByTestId('agent-portal-subnav');
    await expect(nav).toBeVisible();
    await expect(nav.getByRole('link', { name: /Flight Bookings/i })).toBeVisible(); // granted
    await expect(nav.getByRole('link', { name: /Wallet/i })).toHaveCount(0);         // not granted
    await expect(nav.getByRole('link', { name: /Commissions/i })).toHaveCount(0);    // admin-only
  });

  test('gated links are hidden on mobile (parity)', async ({ page }) => {
    await loginAsLimitedAgentStaff(page);
    await page.setViewportSize(PHONE);
    await page.goto('/agent');
    await expect(page.getByRole('link', { name: /Wallet/i })).toHaveCount(0);
  });

  test('permitted pages still render (no over-blocking)', async ({ page }) => {
    await loginAsLimitedAgentStaff(page);
    await page.setViewportSize(DESKTOP);
    const res = await page.goto('/agent/bookings');
    expect(res?.status()).toBe(200);
  });
});

test.describe('agency admin — not over-hidden', () => {
  test('agency admin still sees Commissions', async ({ page }) => {
    await loginAsAgencyAdmin(page);
    await page.setViewportSize(DESKTOP);
    const res = await page.goto('/agent/commissions');
    expect(res?.status()).toBe(200);
  });
});
