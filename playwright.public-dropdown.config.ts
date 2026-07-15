import { defineConfig, devices } from '@playwright/test';

const LOCAL_OTA_URL =
  process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

const host = (() => {
  try {
    return new URL(LOCAL_OTA_URL).hostname;
  } catch {
    return '';
  }
})();

const isLocalHost = ['127.0.0.1', 'localhost', '0.0.0.0', '::1'].includes(host);
if (!isLocalHost && !process.env.OTA_AUDIT_ALLOW_REMOTE) {
  throw new Error(
    `Refused non-local base URL "${LOCAL_OTA_URL}". Set LOCAL_OTA_URL=http://127.0.0.1:8000 for local runs.`,
  );
}

export default defineConfig({
  testDir: './tests/visual',
  testMatch: /public-search-dropdown\.spec\.ts/,
  timeout: 45_000,
  expect: { timeout: 8_000 },
  fullyParallel: true,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  outputDir: 'UI_test/traces/critical',
  use: {
    baseURL: LOCAL_OTA_URL,
    headless: true,
    screenshot: 'only-on-failure',
    video: 'off',
    trace: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 25_000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
