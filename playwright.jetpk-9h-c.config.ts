import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.JETPK_BASE_URL || 'http://127.0.0.1:8000';
const authDir = 'storage/app/playwright/jetpk-9h-c/auth';

const viewports = [
  { name: 'desktop-1440', viewport: { width: 1440, height: 900 } },
  { name: 'desktop-1280', viewport: { width: 1280, height: 800 } },
  { name: 'tablet-1024', viewport: { width: 1024, height: 768 } },
  { name: 'tablet-portrait', viewport: { width: 768, height: 1024 } },
  { name: 'mobile', viewport: { width: 390, height: 844 } },
] as const;

export default defineConfig({
  testDir: 'tests/playwright/jetpk-9h-c',
  outputDir: 'tests/playwright/artifacts/jetpk-9h-c/results',
  globalSetup: './tests/playwright/jetpk-9h-c/global-setup.ts',
  timeout: 120_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/playwright/artifacts/jetpk-9h-c/html-report', open: 'never' }],
    ['json', { outputFile: 'storage/app/audits/jetpk-9h-c/playwright-report.json' }],
  ],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    ...devices['Desktop Chrome'],
  },
  projects: [
    ...viewports.map((vp) => ({
      name: `public-${vp.name}`,
      testMatch: /hero-branding\.spec\.ts/,
      grep: /public homepage/,
      use: { viewport: vp.viewport },
    })),
    ...viewports.map((vp) => ({
      name: `branding-${vp.name}`,
      testMatch: /hero-branding\.spec\.ts/,
      grep: /branding page/,
      use: {
        viewport: vp.viewport,
        storageState: `${authDir}/admin.json`,
      },
    })),
  ],
});
