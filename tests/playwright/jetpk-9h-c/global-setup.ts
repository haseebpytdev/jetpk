import { chromium, type FullConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const baseURL = process.env.JETPK_BASE_URL || 'http://127.0.0.1:8000';
const authDir = path.join('storage', 'app', 'playwright', 'jetpk-9h-c', 'auth');
const otpCode = process.env.OTP_DEMO_FIXED_CODE || '123456';

async function loginAdmin(): Promise<void> {
  const authPath = path.join(authDir, 'admin.json');
  if (fs.existsSync(authPath) && process.env.JETPK_PW_REFRESH_AUTH !== '1') {
    const ageMs = Date.now() - fs.statSync(authPath).mtimeMs;
    if (ageMs < 2 * 60 * 60 * 1000) {
      return;
    }
  }

  const email = process.env.JETPK_PW_ADMIN_EMAIL || 'admin@ota.demo';
  const password = process.env.JETPK_PW_PASSWORD || 'password';
  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  page.setDefaultNavigationTimeout(90_000);

  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.locator('form.jp-auth-form input[name="login"]').fill(email);
  await page.locator('form.jp-auth-form input[name="password"]').fill(password);
  await page.locator('form.jp-auth-form button[type="submit"]').click();

  const landedOnOtp = await page.waitForURL(/\/login\/otp/, { timeout: 15_000 }).then(() => true).catch(() => false);
  if (landedOnOtp) {
    await page.locator('input[name="otp"]').fill(otpCode);
    await page.locator('form button[type="submit"]').first().click({ noWaitAfter: true });
  }

  await Promise.race([
    page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 90_000 }),
    page.locator('#jp-dash-sidebar, .jp-portal__top').first().waitFor({ state: 'visible', timeout: 90_000 }),
  ]).catch(() => {
    if (page.url().includes('/login')) {
      throw new Error(`Login failed for ${email} — still on login`);
    }
  });

  fs.mkdirSync(authDir, { recursive: true });
  await context.storageState({ path: authPath });
  await browser.close();
}

export default async function globalSetup(_config: FullConfig): Promise<void> {
  fs.mkdirSync(authDir, { recursive: true });
  fs.mkdirSync(path.join('storage', 'app', 'audits', 'jetpk-9h-c'), { recursive: true });
  fs.mkdirSync(path.join('tests', 'playwright', 'artifacts', 'jetpk-9h-c', 'screenshots'), { recursive: true });

  const reuseAuth = path.join('storage', 'app', 'playwright', 'jetpk-9h-b', 'auth', 'admin.json');
  const targetAuth = path.join(authDir, 'admin.json');
  if (fs.existsSync(reuseAuth) && process.env.JETPK_PW_REFRESH_AUTH !== '1') {
    fs.copyFileSync(reuseAuth, targetAuth);
    return;
  }

  await loginAdmin();
}
