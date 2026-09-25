import { defineConfig, devices } from '@playwright/test';

import path from 'node:path';
const LIVE_BASE_URL = (process.env.JETPK_LIVE_BASE_URL ?? 'https://jetpakistan.pk').replace(/\/$/, '');

export default defineConfig({
  testDir: path.join(process.cwd(), 'tests/playwright/jetpk'),
  testMatch: /investigate-live-dom-styles\.spec\.ts/,
  timeout: 300_000,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: LIVE_BASE_URL,
    headless: true,
    ignoreHTTPSErrors: true,
    navigationTimeout: 120_000,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
