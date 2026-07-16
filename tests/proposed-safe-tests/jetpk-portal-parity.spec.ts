/**
 * JetPK portal parity — customer, agent, agent staff shells + profile/logout.
 * Requires local server with OTA_CLIENT_SLUG=jetpk and seeded demo users.
 * Run: OTA_CLIENT_SLUG=jetpk LOCAL_OTA_URL=http://127.0.0.1:8000
 *      npx playwright test tests/proposed-safe-tests/jetpk-portal-parity.spec.ts
 */
import { test, expect, Page } from '@playwright/test';

const CUSTOMER_EMAIL = process.env.JETPK_PORTAL_CUSTOMER_EMAIL ?? 'customer@ota.demo';
const CUSTOMER_PASSWORD = process.env.JETPK_PORTAL_CUSTOMER_PASSWORD ?? 'password';
const AGENT_EMAIL = process.env.JETPK_PORTAL_AGENT_EMAIL ?? 'agent@ota.demo';
const AGENT_PASSWORD = process.env.JETPK_PORTAL_AGENT_PASSWORD ?? 'password';

const VIEWPORTS = [
  { name: '1440x900', width: 1440, height: 900 },
  { name: '1024x768', width: 1024, height: 768 },
  { name: '768x1024', width: 768, height: 1024 },
  { name: '390x844', width: 390, height: 844 },
];

async function login(page: Page, email: string, password: string) {
  await page.goto('/login');
  await page.fill('input[name=login], input[name=email]', email);
  await page.fill('input[name=password]', password);
  await page.locator('button[type=submit].ota-mobile-auth__btn--primary, form button[type=submit]:has-text("Log in")').first().click();
  await page.waitForLoadState('domcontentloaded');
}

async function assertNoLegacySeam(page: Page) {
  const html = await page.content();
  expect(html).not.toMatch(/Parwaaz|YoursDomain|YD Travel|haseeb-master/i);
  expect(html).not.toContain('css/ota-public.css');
}

async function assertNoOverflow(page: Page) {
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  );
  expect(overflow).toBeLessThanOrEqual(4);
}

test.describe('JetPK portal parity', () => {
  test('customer dashboard has sidebar profile and logout @ 1440', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page, CUSTOMER_EMAIL, CUSTOMER_PASSWORD);
    await page.goto('/customer');
    await expect(page.locator('[data-testid="customer-account-subnav"]')).toBeVisible();
    await expect(page.locator('[data-testid="jp-portal-sidebar-profile"]')).toBeVisible();
    await expect(page.locator('[data-testid="jp-portal-sidebar-logout"]')).toBeVisible();
    await expect(page.locator('[data-testid="jp-portal-top-profile"]')).toBeVisible();
    await assertNoLegacySeam(page);
    await assertNoOverflow(page);
  });

  test('agent dashboard has sidebar profile and logout @ 1440', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page, AGENT_EMAIL, AGENT_PASSWORD);
    await page.goto('/agent');
    await expect(page.locator('[data-testid="agent-portal-subnav"]')).toBeVisible();
    await expect(page.locator('[data-testid="jp-portal-sidebar-profile"]')).toBeVisible();
    await expect(page.locator('[data-testid="jp-portal-sidebar-logout"]')).toBeVisible();
    await assertNoLegacySeam(page);
  });

  for (const vp of VIEWPORTS) {
    test(`customer profile branded @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      if (vp.width <= 768) {
        await page.setExtraHTTPHeaders({ 'Sec-CH-Viewport-Width': String(vp.width) });
      }
      await login(page, CUSTOMER_EMAIL, CUSTOMER_PASSWORD);
      await page.goto('/profile');
      await expect(
        page.locator('[data-testid="jp-portal-profile-settings"], [data-testid="ota-mobile-customer-profile"]'),
      ).toBeVisible();
      await assertNoOverflow(page);
    });
  }

  test('customer sidebar logout form is POST with CSRF', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page, CUSTOMER_EMAIL, CUSTOMER_PASSWORD);
    await page.goto('/customer');
    const logout = page.locator('[data-testid="jp-portal-sidebar-logout"]');
    await expect(logout).toBeVisible();
    const form = logout.locator('xpath=ancestor::form[1]');
    await expect(form).toHaveAttribute('method', 'post');
    await expect(form.locator('input[name="_token"]')).toHaveCount(1);
  });
});
