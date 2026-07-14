import { chromium, type FullConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

const authDir = path.join('storage', 'app', 'playwright', 'jetpk-9h-c2', 'auth');
const reuseAuth = path.join('storage', 'app', 'playwright', 'jetpk-9h-c', 'auth', 'admin.json');

export default async function globalSetup(_config: FullConfig): Promise<void> {
  fs.mkdirSync(authDir, { recursive: true });
  fs.mkdirSync(path.join('storage', 'app', 'audits', 'jetpk-9h-c2'), { recursive: true });
  fs.mkdirSync(path.join('tests', 'playwright', 'artifacts', 'jetpk-9h-c2', 'screenshots'), { recursive: true });

  execSync('php artisan jetpk:playwright-fixtures --enable-bg-removal-fixture', {
    cwd: process.cwd(),
    stdio: 'inherit',
    env: { ...process.env, BACKGROUND_REMOVAL_FORCE_FIXTURE: 'true' },
  });

  if (fs.existsSync(reuseAuth)) {
    fs.copyFileSync(reuseAuth, path.join(authDir, 'admin.json'));
    return;
  }

  const baseURL = process.env.JETPK_BASE_URL || 'http://127.0.0.1:8000';
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const email = process.env.JETPK_PW_ADMIN_EMAIL || 'admin@ota.demo';
  const password = process.env.JETPK_PW_PASSWORD || 'password';
  const otp = process.env.OTP_DEMO_FIXED_CODE || '123456';

  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('form.jp-auth-form input[name="login"]').fill(email);
  await page.locator('form.jp-auth-form input[name="password"]').fill(password);
  await page.locator('form.jp-auth-form button[type="submit"]').click();

  const onOtp = await page.waitForURL(/\/login\/otp/, { timeout: 12_000 }).then(() => true).catch(() => false);
  if (onOtp) {
    await page.locator('input[name="otp"]').fill(otp);
    await page.locator('form button[type="submit"]').first().click({ noWaitAfter: true });
  }

  await page.waitForURL((url) => url.pathname.startsWith('/admin'), { timeout: 90_000 }).catch(() => {});
  await page.context().storageState({ path: path.join(authDir, 'admin.json') });
  await browser.close();
}
