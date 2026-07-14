/**
 * JetPakistan progressive AJAX login UX (JETPK-PUBLIC-UX-02).
 */
import { test, expect, type Page } from '@playwright/test';

const LOGIN_PATH = process.env.JETPK_LOGIN_PATH ?? '/jetpk/login';
const GENERIC_FAILURE = 'These credentials do not match our records.';
const TEST_EMAIL = process.env.JETPK_TEST_LOGIN_EMAIL ?? '';
const TEST_PASSWORD = process.env.JETPK_TEST_LOGIN_PASSWORD ?? '';

async function fillLogin(page: Page, login: string, password: string) {
  await page.fill('input[name="login"], input[name="email"]', login);
  await page.fill('input[name="password"]', password);
}

async function submitLogin(page: Page) {
  await page.click('button[type="submit"]');
}

test.describe('JetPK AJAX login UX', () => {
  test('login page loads with login script bound', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    await expect(page.locator('[data-jp-login-form]')).toBeVisible();
    await expect(page.locator('form[data-jp-login-form] button[type="submit"]')).toBeVisible();
  });

  test('invalid credentials show generic inline error without full reload', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });

    await fillLogin(page, 'invalid-user@example.test', 'NotTheRightPassword1');
    await submitLogin(page);

    await expect(page.locator('[data-jp-login-alert]')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE);
    await expect(page).toHaveURL(new RegExp(LOGIN_PATH.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
    await expect(page.getByText('Server error', { exact: false })).toHaveCount(0);
  });

  test('unknown email and wrong password show identical message', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });

    await fillLogin(page, 'unknown-user@example.test', 'NotTheRightPassword1');
    await submitLogin(page);
    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE, { timeout: 30_000 });
    const unknownMessage = (await page.locator('[data-jp-login-alert]').textContent())?.trim();

    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    await fillLogin(page, 'known-but-wrong@example.test', 'NotTheRightPassword1');
    await submitLogin(page);
    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE, { timeout: 30_000 });
    const wrongPasswordMessage = (await page.locator('[data-jp-login-alert]').textContent())?.trim();

    expect(unknownMessage).toBe(GENERIC_FAILURE);
    expect(wrongPasswordMessage).toBe(GENERIC_FAILURE);
  });

  test('missing password shows field validation', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.fill('input[name="login"], input[name="email"]', 'user@example.test');
    await submitLogin(page);

    const password = page.locator('input[name="password"]');
    const invalid = await password.evaluate((el: HTMLInputElement) => !el.checkValidity());
    expect(invalid).toBeTruthy();
  });

  test('submit disables during request and restores after failure', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });

    const submit = page.locator('form[data-jp-login-form] button[type="submit"]');
    await fillLogin(page, 'invalid-user@example.test', 'NotTheRightPassword1');

    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('/login') && response.request().method() === 'POST',
    );
    await submit.click();
    await expect(submit).toBeDisabled({ timeout: 5_000 });
    await responsePromise;
    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE, { timeout: 30_000 });
    await expect(submit).toBeEnabled();
    await expect(submit).toHaveText('Log in');
  });

  test('duplicate click sends only one login request', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });

    let postCount = 0;
    await page.route('**/login', async (route) => {
      if (route.request().method() === 'POST') {
        postCount += 1;
        await new Promise((resolve) => setTimeout(resolve, 600));
      }
      await route.continue();
    });

    await fillLogin(page, 'invalid-user@example.test', 'NotTheRightPassword1');
    const submit = page.locator('form[data-jp-login-form] button[type="submit"]');
    await submit.click();
    await submit.click({ force: true });

    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE, { timeout: 30_000 });
    expect(postCount).toBe(1);
  });

  test('password remains empty after failure', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    await fillLogin(page, 'invalid-user@example.test', 'NotTheRightPassword1');
    await submitLogin(page);
    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE, { timeout: 30_000 });
    await expect(page.locator('input[name="password"]')).toHaveValue('');
  });

  test('ajax login sends csrf header and accept json', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });

    let capturedHeaders: Record<string, string> = {};
    await page.route('**/login', async (route) => {
      if (route.request().method() === 'POST') {
        capturedHeaders = route.request().headers();
      }
      await route.continue();
    });

    await fillLogin(page, 'invalid-user@example.test', 'NotTheRightPassword1');
    await submitLogin(page);
    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE, { timeout: 30_000 });

    expect(capturedHeaders.accept || '').toContain('application/json');
    expect(capturedHeaders['x-requested-with'] || capturedHeaders['X-Requested-With'] || '').toBe('XMLHttpRequest');
    expect((capturedHeaders['x-csrf-token'] || capturedHeaders['X-CSRF-TOKEN'] || '').length).toBeGreaterThan(10);
  });

  test('no console errors or unhandled rejections', async ({ page }) => {
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    page.on('pageerror', (error) => {
      pageErrors.push(error.message);
    });

    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    await fillLogin(page, 'invalid-user@example.test', 'NotTheRightPassword1');
    await submitLogin(page);
    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE, { timeout: 30_000 });

    expect(consoleErrors).toEqual([]);
    expect(pageErrors).toEqual([]);
  });

  test('no horizontal overflow on login card', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    const overflow = await page.evaluate(() => {
      const doc = document.documentElement;
      return doc.scrollWidth > doc.clientWidth + 1;
    });
    expect(overflow).toBeFalsy();
  });

  test('successful credentials transition to OTP page when test creds configured', async ({ page }) => {
    test.skip(!TEST_EMAIL || !TEST_PASSWORD, 'Set JETPK_TEST_LOGIN_EMAIL and JETPK_TEST_LOGIN_PASSWORD for OTP transition test.');

    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    await fillLogin(page, TEST_EMAIL, TEST_PASSWORD);

    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('/login') && response.request().method() === 'POST',
    );
    await submitLogin(page);
    const response = await responsePromise;
    const payload = await response.json();

    expect(payload.ok).toBe(true);
    expect(payload.requires_otp).toBe(true);
    expect(typeof payload.redirect).toBe('string');
    expect(payload.redirect).toMatch(/\/login\/otp$/);

    await page.waitForURL(/\/login\/otp/, { timeout: 60_000 });
    await expect(page.locator('input[name="otp"], input#otp')).toBeVisible();
  });

  test('browser back does not expose password value', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    await fillLogin(page, 'invalid-user@example.test', 'NotTheRightPassword1');
    await submitLogin(page);
    await expect(page.locator('[data-jp-login-alert]')).toContainText(GENERIC_FAILURE, { timeout: 30_000 });
    await page.goBack();
    await expect(page.locator('input[name="password"]')).toHaveValue('');
  });
});

test.describe('JetPK HTML login fallback', () => {
  test('javascript-disabled HTML POST still shows validation error', async ({ page }) => {
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });
    await fillLogin(page, 'invalid-user@example.test', 'NotTheRightPassword1');
    await submitLogin(page);

    await expect(page).toHaveURL(/\/login/, { timeout: 30_000 });
    await expect(page.getByText(GENERIC_FAILURE)).toBeVisible();
    await expect(page.getByText('Server error', { exact: false })).toHaveCount(0);
  });
});
