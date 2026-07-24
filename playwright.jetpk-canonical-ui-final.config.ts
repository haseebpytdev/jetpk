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

if (!['127.0.0.1', 'localhost', '0.0.0.0', '::1'].includes(host) && !process.env.OTA_AUDIT_ALLOW_REMOTE) {
  throw new Error(`Refused non-local base URL "${LOCAL_OTA_URL}"`);
}

export default defineConfig({
  testDir: './tests/visual',
  testMatch: /jetpk-canonical-ui-final\.spec\.ts/,
  timeout: 180_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['json', { outputFile: 'test-results/jetpk-canonical-ui-final/report.json' }]],
  outputDir: 'test-results/jetpk-canonical-ui-final/traces',
  globalSetup: './tests/visual/jetpk-canonical-ui-final.setup.ts',
  use: {
    baseURL: LOCAL_OTA_URL,
    headless: true,
    screenshot: 'off',
    video: 'off',
    trace: 'retain-on-failure',
    actionTimeout: 20_000,
    navigationTimeout: 60_000,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
