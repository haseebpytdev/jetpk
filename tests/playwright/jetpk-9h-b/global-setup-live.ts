import { chromium, type FullConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const baseURL = process.env.JETPK_LIVE_BASE_URL || 'https://jetpakistan.pk';
const authDir = path.join('storage', 'app', 'playwright', 'jetpk-9h-b', 'live-auth');
const otpCode = process.env.JETPK_LIVE_OTP_CODE || process.env.OTP_DEMO_FIXED_CODE || '';

const roles = [
  { id: 'admin', email: process.env.JETPK_LIVE_ADMIN_EMAIL, password: process.env.JETPK_LIVE_ADMIN_PASSWORD },
  { id: 'staff', email: process.env.JETPK_LIVE_STAFF_EMAIL, password: process.env.JETPK_LIVE_STAFF_PASSWORD },
  { id: 'agent', email: process.env.JETPK_LIVE_AGENT_EMAIL, password: process.env.JETPK_LIVE_AGENT_PASSWORD },
  { id: 'agent-staff', email: process.env.JETPK_LIVE_AGENT_STAFF_EMAIL, password: process.env.JETPK_LIVE_AGENT_STAFF_PASSWORD },
  { id: 'customer', email: process.env.JETPK_LIVE_CUSTOMER_EMAIL, password: process.env.JETPK_LIVE_CUSTOMER_PASSWORD },
];

async function loginWithOtp(role: (typeof roles)[number]): Promise<void> {
  if (!role.email || !role.password) {
    throw new Error(`Missing live credentials for role ${role.id}`);
  }

  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
  const page = await context.newPage();

  await page.goto('/login');
  await page.locator('input[name="login"]').fill(role.email);
  await page.locator('input[name="password"]').fill(role.password);
  await page.locator('button[type="submit"], input[type="submit"]').first().click();

  if (await page.url().includes('/login/otp')) {
    if (!otpCode) {
      throw new Error(`Live login for ${role.id} requires OTP but JETPK_LIVE_OTP_CODE is unset`);
    }
    await page.locator('input[name="otp"]').fill(otpCode);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
  }

  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 90_000 });

  fs.mkdirSync(authDir, { recursive: true });
  await context.storageState({ path: path.join(authDir, `${role.id}.json`) });
  await browser.close();
}

export default async function globalSetup(_config: FullConfig): Promise<void> {
  fs.mkdirSync(authDir, { recursive: true });
  for (const role of roles) {
    await loginWithOtp(role);
  }
}
