import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.JETPK_BASE_URL || 'http://127.0.0.1:8000';
const authDir = 'storage/app/playwright/jetpk-9h-b/auth';

export default defineConfig({
  testDir: 'tests/playwright/jetpk-dashboard',
  outputDir: 'tests/playwright/artifacts/jetpk-dashboard/results',
  globalSetup: './tests/playwright/jetpk-9h-b/global-setup.ts',
  timeout: 120_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [
    ['list'],
    ['json', { outputFile: 'storage/app/audits/jetpk-dashboard/playwright-report.json' }],
  ],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ...devices['Desktop Chrome'],
    storageState: `${authDir}/customer.json`,
  },
  projects: [
    {
      name: 'dashboard-shell-customer',
      testMatch: /dashboard-shell\.spec\.ts/,
      grep: /customer/,
      use: { storageState: `${authDir}/customer.json` },
    },
    {
      name: 'dashboard-shell-agent',
      testMatch: /dashboard-shell\.spec\.ts/,
      grep: /agent/,
      use: { storageState: `${authDir}/agent.json` },
    },
    {
      name: 'themed-customer-dashboard',
      testMatch: /themed-customer-dashboard\.spec\.ts/,
      use: { storageState: `${authDir}/customer.json` },
    },
    {
      name: 'customer-dashboard',
      testMatch: /customer-dashboard\.spec\.ts/,
      use: { storageState: `${authDir}/customer.json` },
    },
  ],
});
