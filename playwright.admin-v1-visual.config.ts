import { defineConfig, devices } from '@playwright/test';

const LOCAL_OTA_URL =
  process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? process.env.APP_URL ?? 'http://127.0.0.1:8000';

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
    `Admin visual audit refused non-local base URL "${LOCAL_OTA_URL}". Set LOCAL_OTA_URL=http://127.0.0.1:8000 or OTA_AUDIT_ALLOW_REMOTE=1 for approved staging mirrors only.`,
  );
}

export default defineConfig({
  testDir: './tests/visual',
  testMatch: /admin-v1-visual-audit\.spec\.ts/,
  timeout: 600_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  outputDir: 'UI_test/traces/admin-v1-visual',
  use: {
    baseURL: LOCAL_OTA_URL,
    headless: true,
    screenshot: 'off',
    video: 'off',
    trace: 'retain-on-failure',
    actionTimeout: 20_000,
    navigationTimeout: 60_000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
