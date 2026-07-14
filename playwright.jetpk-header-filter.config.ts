import { defineConfig, devices } from '@playwright/test';

const BASE_URL = (process.env.JETPK_LIVE_BASE_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'https://jetpakistan.pk').replace(
  /\/$/,
  '',
);

export default defineConfig({
  testDir: './tests/playwright/jetpk',
  testMatch: /(header-filter-highlight-parity|filter-panel-visual|results-body-alignment)\.spec\.ts/,
  timeout: 300_000,
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
