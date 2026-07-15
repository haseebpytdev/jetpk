import { defineConfig, devices } from '@playwright/test';

const BASE_URL = (process.env.JETPK_LIVE_BASE_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8765').replace(
  /\/$/,
  '',
);

export default defineConfig({
  testDir: './tests/playwright/jetpk',
  testMatch: /login-validation-recovery\.spec\.ts/,
  timeout: 180_000,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: BASE_URL,
    headless: true,
    ignoreHTTPSErrors: true,
    navigationTimeout: 120_000,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
