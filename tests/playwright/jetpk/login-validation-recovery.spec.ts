/**
 * JetPakistan HTML login validation must stay on login page (no custom 500).
 */
import { test, expect } from '@playwright/test';

const LOGIN_PATH = process.env.JETPK_LOGIN_PATH ?? '/login';

test.describe('JetPK login validation recovery', () => {
  test('invalid HTML login shows inline error without server-error page', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(LOGIN_PATH, { waitUntil: 'networkidle', timeout: 120_000 });

    await page.fill('input[name="login"], input[name="email"]', 'invalid-user@example.test');
    await page.fill('input[name="password"]', 'NotTheRightPassword1');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/login/, { timeout: 30_000 });
    await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
    await expect(page.getByText('Server error', { exact: false })).toHaveCount(0);
  });
});
