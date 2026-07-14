import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.JETPK_BASE_URL || 'http://127.0.0.1:8000';
const authDir = 'storage/app/playwright/jetpk-9h-c2/auth';

const viewports = [
  { name: 'desktop-1440', viewport: { width: 1440, height: 900 } },
  { name: 'desktop-1280', viewport: { width: 1280, height: 800 } },
  { name: 'tablet-portrait', viewport: { width: 768, height: 1024 } },
  { name: 'mobile', viewport: { width: 390, height: 844 } },
] as const;

export default defineConfig({
  testDir: 'tests/playwright/jetpk-9h-c2',
  outputDir: 'tests/playwright/artifacts/jetpk-9h-c2/results',
  globalSetup: './tests/playwright/jetpk-9h-c2/global-setup.ts',
  globalTeardown: './tests/playwright/jetpk-9h-c2/global-teardown.ts',
  timeout: 180_000,
  expect: { timeout: 30_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/playwright/artifacts/jetpk-9h-c2/html-report', open: 'never' }],
    ['json', { outputFile: 'storage/app/audits/jetpk-9h-c2/playwright-report.json' }],
  ],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    ...devices['Desktop Chrome'],
  },
  projects: viewports.flatMap((vp) => [
    {
      name: `bg-removal-${vp.name}`,
      testMatch: /background-removal\.spec\.ts/,
      use: { viewport: vp.viewport, storageState: `${authDir}/admin.json` },
    },
    {
      name: `palette-${vp.name}`,
      testMatch: /palette-consumption\.spec\.ts/,
      use: { viewport: vp.viewport, storageState: `${authDir}/admin.json` },
    },
  ]),
});
