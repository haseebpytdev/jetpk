import { defineConfig, devices } from '@playwright/test';

import path from 'node:path';
const baseURL = process.env.JETPK_BASE_URL || 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: path.join(process.cwd(), 'tests/playwright/jetpk-homepage-lcp'),
  outputDir: path.join(process.cwd(), 'tests/playwright/artifacts/jetpk-homepage-lcp/results'),
  timeout: 120_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/playwright/artifacts/jetpk-homepage-lcp/html-report', open: 'never' }],
    ['json', { outputFile: 'storage/app/audits/jetpk-homepage-lcp/playwright-report.json' }],
  ],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    ...devices['Desktop Chrome'],
  },
  projects: [
    {
      name: 'chromium',
      use: { viewport: { width: 1440, height: 900 } },
    },
  ],
});
