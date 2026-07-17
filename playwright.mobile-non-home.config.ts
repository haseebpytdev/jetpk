import { defineConfig, devices } from '@playwright/test';

const LOCAL_OTA_URL =
  process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: './tests/visual',
  testMatch: /mobile-flight-ota\.spec\.ts/,
  grep: /standalone search|results mobile chrome|results sticky bar|desktop 1024 keeps desktop result/,
  timeout: 90_000,
  workers: 1,
  reporter: [['list']],
  outputDir: 'UI_test/traces/mobile-non-home',
  use: {
    baseURL: LOCAL_OTA_URL,
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
