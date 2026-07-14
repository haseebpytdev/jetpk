import { defineConfig, devices } from '@playwright/test';

const LOCAL_OTA_URL =
  process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

/** Safety guard — refuse non-local hosts unless explicitly overridden for CI staging mirrors */
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
    `Responsive audit refused non-local base URL "${LOCAL_OTA_URL}". Set LOCAL_OTA_URL=http://127.0.0.1:8000 or OTA_AUDIT_ALLOW_REMOTE=1 for approved staging mirrors only.`,
  );
}

export default defineConfig({
  testDir: './tests/visual',
  timeout: 360_000,
  expect: { timeout: 12_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  outputDir: 'UI_test/traces',
  use: {
    baseURL: LOCAL_OTA_URL,
    headless: true,
    screenshot: 'off',
    video: 'off',
    trace: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 45_000,
  },
  globalSetup: './tests/visual/global-setup.ts',
  globalTeardown: './tests/visual/global-teardown.ts',
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: 'chromium',
      dependencies: ['setup'],
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      dependencies: ['setup'],
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      dependencies: ['setup'],
      use: { ...devices['Desktop Safari'] },
    },
  ],
});
