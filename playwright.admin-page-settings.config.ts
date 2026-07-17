/**
 * Admin Page Settings — CMS functional verification (local Chromium).
 */
import { defineConfig, devices } from '@playwright/test';
import { FRESH_ADMIN_AUTH } from './tests/visual/admin-page-settings-auth.setup';

const LOCAL_OTA_URL =
  process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
  globalSetup: './tests/visual/admin-page-settings-auth.setup.ts',
  testDir: './tests/visual',
  testMatch: /admin-page-settings-functional\.spec\.ts/,
  timeout: 120_000,
  workers: 1,
  reporter: [['list']],
  outputDir: 'UI_test/traces/admin-page-settings',
  use: {
    baseURL: LOCAL_OTA_URL,
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    storageState: FRESH_ADMIN_AUTH,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
