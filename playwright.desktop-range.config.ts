import { defineConfig, devices } from '@playwright/test';

const baseURL =
  process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: './tests/visual',
  testMatch: /desktop-return-range-picker\.spec\.ts/,
  timeout: 120_000,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL,
    headless: true,
    screenshot: 'off',
    video: 'off',
    trace: 'off',
  },
  projects: [{ name: 'desktop-chrome', use: { ...devices['Desktop Chrome'] } }],
});
