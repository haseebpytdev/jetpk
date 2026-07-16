import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/proposed-safe-tests',
  testMatch: /mobile-(live-activation|integration-screenshots)\.spec\.ts/,
  timeout: 120_000,
  workers: 1,
  use: {
    baseURL: process.env.LOCAL_OTA_URL ?? 'http://127.0.0.1:8000',
    headless: true,
    screenshot: 'off',
  },
});
