/**
 * Non-home mobile scope — Strategy 1 must affect `/` only.
 * Canonical replacement for retired `mobile-flight-ota.spec.ts` (see docs/JETPK_MOBILE_FLIGHT_OTA_RETIREMENT.md).
 */
import { expect, test } from '@playwright/test';
import { gotoPublicPage } from './helpers/public-fast-navigation';
import { getOverflowMetrics } from './helpers/layout-checks';
import { loginWithJetpkOtp } from './helpers/jetpk-login-with-otp';

const MOBILE = { width: 390, height: 844 };
const TABLET = { width: 768, height: 1024 };
const DESKTOP = { width: 1024, height: 800 };
const HEAVY_NAV_MS = 45_000;

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

async function expectMobileResultsChrome(page: import('@playwright/test').Page) {
  await expect(
    page.locator('[data-testid="ota-mobile-results"], .ota-mobile-results, nav[aria-label="App navigation"]').first(),
  ).toBeVisible();
}

test.describe('non-home mobile scope @ 390', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE);
  });

  test('flights/results renders mobile chrome', async ({ page }) => {
    await gotoPublicPage(page, `/flights/results?${resultsQuery}`, 'flights-results', {
      timeoutMs: HEAVY_NAV_MS,
    });
    await expectMobileResultsChrome(page);
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
    await gotoPublicPage(page, '/flights/search', 'flights-search', { timeoutMs: HEAVY_NAV_MS });
    await expect(page.locator('[data-flight-search-form], [data-jp-search]').first()).toBeVisible();
    await expect(page.locator('.ota-mobile-home-trust-bar')).toHaveCount(0);
    await assertNoOverflow(page, 'flights/search nav');
  });

  test('home uses Strategy 1 responsive shell not legacy mobile-home substitution', async ({ page }) => {
    await gotoPublicPage(page, '/', 'home');
    await expect(page.locator('[data-jp-search], [data-flight-search-form]').first()).toBeVisible();
    await expect(page.locator('[data-testid="ota-mobile-home"]')).toHaveCount(0);
    await expect(page.locator('.ota-mobile-home-trust-bar')).toHaveCount(0);
    await expect(page.locator('#routes')).toBeHidden();
  });
});

test.describe('non-home mobile scope @ tablet768', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(TABLET);
  });

  test('flights/results keeps mobile app shell and bottom navigation', async ({ page }) => {
    await gotoPublicPage(page, `/flights/results?${resultsQuery}`, 'flights-results', {
      timeoutMs: HEAVY_NAV_MS,
    });
    await expectMobileResultsChrome(page);
    await assertNoOverflow(page, 'flights/results tablet768');
  });

  test('flights/search is not legacy mobile-home substitution', async ({ page }) => {
    await gotoPublicPage(page, '/flights/search', 'flights-search', { timeoutMs: HEAVY_NAV_MS });
    await expect(page.locator('[data-flight-search-form], [data-jp-search]').first()).toBeVisible();
    await expect(page.locator('.ota-mobile-home-trust-bar')).toHaveCount(0);
  });
});

test.describe('non-home mobile scope @ desktop1024', () => {
  test('flights/results keeps desktop result layout', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await gotoPublicPage(page, `/flights/results?${resultsQuery}`, 'flights-results', {
      timeoutMs: HEAVY_NAV_MS,
    });
    await expect(page.locator('.ota-mobile-flight-card')).toHaveCount(0);
    await expect(page.locator('.ota-mobile-flight-details')).toHaveCount(0);
    await assertNoOverflow(page, 'flights/results desktop1024');
  });

  test('home shows full JetPK sections at desktop width', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await gotoPublicPage(page, '/', 'home');
    await expect(page.locator('[data-jp-search], [data-flight-search-form]').first()).toBeVisible();
    await expect(page.locator('.ota-mobile-home-trust-bar')).toHaveCount(0);
    await expect(page.locator('.ota-mobile-home-metrics')).toHaveCount(0);
    await assertNoOverflow(page, 'home desktop1024');
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
