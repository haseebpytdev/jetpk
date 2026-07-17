/**
 * Non-home mobile scope — Strategy 1 must affect `/` only.
 */
import { expect, test } from '@playwright/test';
import { gotoPublicPage } from './helpers/public-fast-navigation';
import { getOverflowMetrics } from './helpers/layout-checks';
import { loginWithJetpkOtp } from './helpers/jetpk-login-with-otp';

const MOBILE = { width: 390, height: 844 };

function futureDepart(daysAhead = 21): string {
  const d = new Date();
  d.setDate(d.getDate() + daysAhead);
  return d.toISOString().slice(0, 10);
}

const resultsQuery = `from=LHE&to=DXB&depart=${futureDepart()}&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0`;

async function assertNoOverflow(page: import('@playwright/test').Page, label: string) {
  const m = await getOverflowMetrics(page);
  expect(m.hasOverflow, `${label}: ${m.bodyScrollWidth} > ${m.innerWidth}`).toBe(false);
}

test.describe('non-home mobile scope @ 390', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE);
  });

  test('flights/results renders mobile chrome', async ({ page }) => {
    await gotoPublicPage(page, `/flights/results?${resultsQuery}`, 'flights-results');
    await expect(
      page.locator('[data-testid="ota-mobile-results"], .ota-mobile-results, nav[aria-label="App navigation"]').first(),
    ).toBeVisible();
    await assertNoOverflow(page, 'flights/results');
  });

  test('booking passengers redirects without session (no 500)', async ({ page }) => {
    const res = await page.goto('/booking/passengers', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(500);
    expect(page.url()).not.toContain('500');
    await assertNoOverflow(page, 'booking/passengers');
  });

  test('booking review redirects without session (no 500)', async ({ page }) => {
    const res = await page.goto('/booking/review', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(500);
    expect(page.url()).not.toContain('500');
    await assertNoOverflow(page, 'booking/review');
  });

  test('customer portal entry redirects guest to login', async ({ page }) => {
    const res = await page.goto('/customer', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(500);
    expect(page.url()).toMatch(/\/login/);
  });

  test('agent portal entry redirects guest to login', async ({ page }) => {
    const res = await page.goto('/agent', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(500);
    expect(page.url()).toMatch(/\/login/);
  });

  test('mobile nav shell on flights search (not home substitution)', async ({ page }) => {
    await gotoPublicPage(page, '/flights/search', 'flights-search');
    await expect(page.locator('[data-flight-search-form], [data-jp-search]').first()).toBeVisible();
    await expect(page.locator('.ota-mobile-home-trust-bar')).toHaveCount(0);
    await assertNoOverflow(page, 'flights/search nav');
  });

  test('home uses Strategy 1 mobile shell not full desktop sections', async ({ page }) => {
    await gotoPublicPage(page, '/', 'home');
    await expect(page.locator('[data-jp-search], [data-flight-search-form]').first()).toBeVisible();
    await expect(page.locator('[data-testid="ota-mobile-home"]')).toHaveCount(0);
    await expect(page.locator('#routes')).toBeHidden();
  });
});

test.describe('non-home mobile authenticated entry', () => {
  test('customer dashboard loads after login', async ({ page }) => {
    await page.setViewportSize(MOBILE);
    const email = process.env.OTA_AUDIT_CUSTOMER_EMAIL ?? 'customer@ota.demo';
    const password = process.env.OTA_AUDIT_PASSWORD ?? 'password';
    await loginWithJetpkOtp(page, email, password);
    const res = await page.goto('/customer', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
  });

  test('agent dashboard loads after login', async ({ page }) => {
    await page.setViewportSize(MOBILE);
    const email = process.env.OTA_AUDIT_AGENT_EMAIL ?? 'agent@ota.demo';
    const password = process.env.OTA_AUDIT_PASSWORD ?? 'password';
    await loginWithJetpkOtp(page, email, password);
    const res = await page.goto('/agent', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
  });
});
