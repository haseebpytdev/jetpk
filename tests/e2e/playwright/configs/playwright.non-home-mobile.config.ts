import { defineConfig, devices } from '@playwright/test';

import path from 'node:path';
const LOCAL_OTA_URL =
  process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: path.join(process.cwd(), 'tests/visual'),
  testMatch: /non-home-mobile-scope\.spec\.ts/,
  timeout: 120_000,
  workers: 1,
  reporter: [['list']],
  outputDir: path.join(process.cwd(), 'UI_test/traces/non-home-mobile'),
  use: {
    baseURL: LOCAL_OTA_URL,
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
