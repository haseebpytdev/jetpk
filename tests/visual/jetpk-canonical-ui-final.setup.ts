import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { loginWithJetpkOtp } from './helpers/jetpk-login-with-otp';
import { FRESH_ADMIN_AUTH } from './admin-page-settings-auth.setup';

export default async function globalSetup(): Promise<void> {
  const evidenceDir = path.join('test-results', 'jetpk-canonical-ui-final');
  fs.mkdirSync(evidenceDir, { recursive: true });
  fs.mkdirSync(path.dirname(FRESH_ADMIN_AUTH), { recursive: true });

  process.env.OTP_DEMO_FIXED_ENABLED = process.env.OTP_DEMO_FIXED_ENABLED ?? 'true';
  process.env.OTP_DEMO_FIXED_CODE = process.env.OTP_DEMO_FIXED_CODE ?? '123456';
  process.env.OTP_DEMO_ALLOW_DEVCP = process.env.OTP_DEMO_ALLOW_DEVCP ?? 'true';

  execSync('php artisan jetpk:playwright-fixtures --create', { stdio: 'inherit' });
  execSync('php artisan jetpk:local-audit-fixture --seed --profile=jetpk', { stdio: 'inherit' });

  const baseURL = process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  await loginWithJetpkOtp(page, process.env.PLAYWRIGHT_ADMIN_EMAIL ?? 'admin@ota.demo', process.env.PLAYWRIGHT_ADMIN_PASSWORD ?? 'password');
  await context.storageState({ path: FRESH_ADMIN_AUTH });
  await browser.close();
}
