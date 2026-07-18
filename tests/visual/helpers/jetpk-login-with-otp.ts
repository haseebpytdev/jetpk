import type { Page } from '@playwright/test';

/**
 * JetPK login with optional demo OTP — matches jetpk-9h-b global-setup contract.
 */
export async function loginWithJetpkOtp(
  page: Page,
  email: string,
  password: string,
): Promise<void> {
  const otpCode = process.env.OTP_DEMO_FIXED_CODE ?? '123456';

  page.setDefaultNavigationTimeout(90_000);
  page.setDefaultTimeout(45_000);

  await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 90_000 });
  await page.locator('form.jp-auth-form input[name="login"], input[name="login"]').first().fill(email);
  await page.locator('form.jp-auth-form input[name="password"], input[name="password"]').first().fill(password);
  await page.locator('form.jp-auth-form button[type="submit"], form button[type="submit"]').first().click();

  const landedOnOtp = await page
    .waitForURL(/\/login\/otp/, { timeout: 15_000 })
    .then(() => true)
    .catch(() => false);

  if (landedOnOtp) {
    await page.locator('input[name="otp"]').fill(otpCode);
    await page.locator('form.jp-auth-form button[type="submit"]').click();
  }

  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      await Promise.race([
        page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30_000 }),
        page.locator('#jp-dash-sidebar, .jp-portal__top').first().waitFor({ state: 'visible', timeout: 30_000 }),
      ]);
      break;
    } catch {
      if (attempt === 2 && page.url().includes('/login')) {
        throw new Error(`Login failed for ${email} — still on login`);
      }
      if (page.url().includes('/login/otp')) {
        await page.locator('input[name="otp"]').fill(otpCode);
        await page.locator('form.jp-auth-form button[type="submit"]').click();
      }
      await page.waitForTimeout(2_000);
    }
  }

  await page.waitForLoadState('domcontentloaded');
}
