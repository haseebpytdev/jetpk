import { defineConfig, devices } from '@playwright/test';

const baseURL = (process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');

export default defineConfig({
  testDir: './tests/playwright/jetpk',
  testMatch: /homepage-search-ui-scale\.spec\.ts/,
  timeout: 180_000,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL,
    headless: true,
    navigationTimeout: 120_000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
