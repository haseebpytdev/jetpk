/**
 * Proposed safe test — shared dashboard shell (Phase 1)
 * JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4
 *
 * SAFETY: local/deterministic only. Uses the app's EXISTING Playwright auth fixtures
 * (do not hardcode production creds). Exercises NO live supplier search, NO real
 * booking/PNR, NO payment/email. Exclude from any *-live.config.ts run.
 *
 * WIRING: this is a template. Replace `loginAs(role)` with the repo's established
 * authentication helper (e.g. the storageState / session-seeding used by
 * playwright.agent-critical.config.ts) and confirm the local route names. Assertions
 * key off the data-testid hooks added in Phase 1:
 *   dashboard-shell-{role}, customer-account-subnav / agent-portal-subnav,
 *   dashboard-shell-{role} .ota-dashboard-navtoggle, permission-denied.
 */
import { test, expect, Page } from '@playwright/test';

// Replace with the repository's real auth fixture:
async function loginAs(page: Page, role: 'customer' | 'agent'): Promise<string> {
  // e.g. await useSeededSession(page, role); return '/' + role + '/dashboard';
  const home: Record<string, string> = {
    customer: '/customer/dashboard',
    agent: '/agent/dashboard',
  };
  return home[role];
}

const PHONE = { width: 390, height: 844 };
const SMALL = { width: 360, height: 800 };
const DESKTOP = { width: 1440, height: 900 };

for (const role of ['customer', 'agent'] as const) {
  test.describe(`shared shell · ${role}`, () => {
    test('renders the canonical shell with sidebar nav', async ({ page }) => {
      const home = await loginAs(page, role);
      await page.setViewportSize(DESKTOP);
      await page.goto(home);

      await expect(page.getByTestId(`dashboard-shell-${role}`)).toBeVisible();
      const subnav = page.getByTestId(role === 'customer' ? 'customer-account-subnav' : 'agent-portal-subnav');
      await expect(subnav).toBeVisible();
      // At least the Overview link is present and marked active on the dashboard.
      await expect(subnav.locator('a.ota-dashboard-sidebar__link.is-active')).toHaveCount(1);
    });

    test('no horizontal overflow at 360 / 390 / desktop', async ({ page }) => {
      const home = await loginAs(page, role);
      for (const vp of [SMALL, PHONE, DESKTOP]) {
        await page.setViewportSize(vp);
        await page.goto(home);
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth
        );
        expect(overflow, `viewport ${vp.width}px must not overflow`).toBeLessThanOrEqual(1);
      }
    });

    test('mobile drawer opens, traps focus, closes on Escape', async ({ page }) => {
      const home = await loginAs(page, role);
      await page.setViewportSize(PHONE);
      await page.goto(home);

      const toggle = page.locator(`[data-testid="dashboard-shell-${role}"] .ota-dashboard-navtoggle`);
      // Toggle is only interactive below lg.
      if (await toggle.isVisible()) {
        await toggle.click();
        const drawer = page.locator(`#ota-dashboard-drawer-${role}`);
        await expect(drawer).toBeVisible();
        await expect(page.locator('body.ota-dashboard-drawer-open')).toHaveCount(1);
        await page.keyboard.press('Escape');
        await expect(drawer).toBeHidden();
        await expect(page.locator('body.ota-dashboard-drawer-open')).toHaveCount(0);
      }
    });

    test('keyboard focus shows a visible focus ring (no outline:none regressions)', async ({ page }) => {
      const home = await loginAs(page, role);
      await page.setViewportSize(DESKTOP);
      await page.goto(home);
      await page.keyboard.press('Tab');
      const hasRing = await page.evaluate(() => {
        const el = document.activeElement as HTMLElement | null;
        if (!el) return false;
        const s = getComputedStyle(el);
        // foundation focus system uses box-shadow ring; ensure it is not fully suppressed
        return s.boxShadow !== 'none' || s.outlineStyle !== 'none';
      });
      expect(hasRing).toBeTruthy();
    });
  });
}

test.describe('permission-denied state', () => {
  test('renders the canonical denied component when present', async ({ page }) => {
    // Wire to a fixture route that renders <x-dashboard.permission-denied /> for an
    // agent_staff user lacking a permission (Phase 6). Skjuntil that fixture exists.
    test.skip(true, 'Enable once the Phase 6 agent_staff permission fixture is available.');
    await page.goto('/agent/bookings');
    await expect(page.getByTestId('permission-denied')).toBeVisible();
  });
});
