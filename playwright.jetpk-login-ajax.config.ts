import { defineConfig, devices } from '@playwright/test';

const BASE_URL = (process.env.JETPK_LIVE_BASE_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8765').replace(
  /\/$/,
  '',
);

export default defineConfig({
  testDir: './tests/playwright/jetpk',
  testMatch: /login-ajax-ux\.spec\.ts/,
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
  projects: [
    { name: 'mobile-390', use: { ...devices['Pixel 5'], viewport: { width: 390, height: 844 } } },
    { name: 'mobile-360', use: { ...devices['Pixel 5'], viewport: { width: 360, height: 800 } } },
    { name: 'desktop-1366', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 768 } } },
    { name: 'desktop-1920', use: { ...devices['Desktop Chrome'], viewport: { width: 1920, height: 1080 } } },
    {
      name: 'html-fallback',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1366, height: 768 },
        javaScriptEnabled: false,
      },
    },
  ],
});
