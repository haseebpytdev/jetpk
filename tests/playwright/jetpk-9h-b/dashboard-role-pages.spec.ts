import { test, expect } from '@playwright/test';
import {
  adminPages,
  agentPages,
  assertDashboardPage,
  customerPages,
  staffPages,
} from './helpers/dashboard-qa';

const rolePages: Record<string, typeof adminPages> = {
  admin: adminPages,
  staff: staffPages,
  agent: agentPages,
  'agent-staff': agentPages,
  customer: customerPages,
};

for (const [role, pages] of Object.entries(rolePages)) {
  test.describe(`role-${role} dashboard pages`, () => {
    test.beforeEach(({ }, testInfo) => {
      test.skip(!testInfo.project.name.startsWith(`${role}-`), `role-${role} project only`);
    });

    for (const spec of pages) {
      test(`GET ${spec.path}`, async ({ page }, testInfo) => {
        await assertDashboardPage(page, spec, testInfo);
      });
    }
  });
}

test.describe('staff forbidden admin modules', () => {
  test.beforeEach(({ }, testInfo) => {
    test.skip(!testInfo.project.name.startsWith('staff-'), 'staff role project only');
  });

  test('branding settings blocked', async ({ page }) => {
    const response = await page.goto('/admin/settings/branding');
    const status = response?.status() ?? 0;
    expect([403, 302, 404]).toContain(status);
  });
});

test.describe('customer forbidden admin links', () => {
  test.beforeEach(({ }, testInfo) => {
    test.skip(!testInfo.project.name.startsWith('customer-'), 'customer role project only');
  });

  test('admin dashboard blocked', async ({ page }) => {
    const response = await page.goto('/admin');
    const status = response?.status() ?? 0;
    expect([403, 302, 404]).toContain(status);
  });
});

test.describe('public branding surfaces', () => {
  test.beforeEach(({ }, testInfo) => {
    test.skip(!testInfo.project.name.startsWith('public-'), 'public project only');
  });

  test('home uses canonical branding shell', async ({ page }, testInfo) => {
    await page.goto('/');
    await page.locator('header').first().waitFor({ state: 'visible' });
    const text = await page.locator('body').innerText();
    expect(text.includes('Parwaaz')).toBe(false);
    await page.screenshot({
      path: `tests/playwright/artifacts/jetpk-9h-b/screenshots/${testInfo.project.name}-public-home.png`,
      fullPage: true,
    });
  });

  test('login page branding', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.locator('.jp-auth-page').waitFor({ state: 'visible' });
    await page.screenshot({
      path: `tests/playwright/artifacts/jetpk-9h-b/screenshots/${testInfo.project.name}-public-login.png`,
      fullPage: true,
    });
  });
});

test.describe('error shells', () => {
  test.beforeEach(({ }, testInfo) => {
    test.skip(!testInfo.project.name.startsWith('public-'), 'public project only');
  });

  for (const spec of [
    { path: '/__jetpk-missing-404', status: 404 },
    { path: '/login', status: 200 },
  ]) {
    test(`error-safe navigation ${spec.path}`, async ({ page }) => {
      const response = await page.goto(spec.path === '/__jetpk-missing-404' ? '/this-route-does-not-exist-jetpk-9hb' : spec.path);
      expect(response?.status()).toBe(spec.status);
      const headers = await page.locator('header').count();
      expect(headers).toBeLessThanOrEqual(2);
    });
  }
});
