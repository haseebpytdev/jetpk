import { chromium, type FullConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { loginWithJetpkOtp } from './helpers/jetpk-login-with-otp';

export const FRESH_ADMIN_AUTH = path.join('storage', 'test-results', 'admin-page-settings-fresh-auth.json');

export default async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL =
    (config.projects[0]?.use?.baseURL as string | undefined) ??
    process.env.LOCAL_OTA_URL ??
    'http://127.0.0.1:8000';

  fs.mkdirSync(path.dirname(FRESH_ADMIN_AUTH), { recursive: true });

  const email = process.env.OTA_AUDIT_ADMIN_EMAIL ?? process.env.PLAYWRIGHT_ADMIN_EMAIL ?? 'admin@ota.demo';
  const password =
    process.env.OTA_AUDIT_PASSWORD ?? process.env.PLAYWRIGHT_ADMIN_PASSWORD ?? 'password';

  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  await loginWithJetpkOtp(page, email, password);
  await context.storageState({ path: FRESH_ADMIN_AUTH });
  await browser.close();
}
