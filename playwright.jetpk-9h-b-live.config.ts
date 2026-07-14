import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.JETPK_LIVE_BASE_URL || 'https://jetpakistan.pk';

if (!process.env.OTA_AUDIT_ALLOW_REMOTE) {
  throw new Error('Live JetPK 9H-B Playwright requires OTA_AUDIT_ALLOW_REMOTE=1');
}

const authDir = 'storage/app/playwright/jetpk-9h-b/live-auth';
const viewports = [
  { name: 'desktop-1440', viewport: { width: 1440, height: 900 } },
  { name: 'desktop-1280', viewport: { width: 1280, height: 800 } },
  { name: 'tablet-1024', viewport: { width: 1024, height: 768 } },
  { name: 'tablet-portrait', viewport: { width: 768, height: 1024 } },
  { name: 'mobile', viewport: { width: 390, height: 844 } },
];

const roles = ['admin', 'staff', 'agent', 'agent-staff', 'customer'] as const;

export default defineConfig({
  testDir: 'tests/playwright/jetpk-9h-b',
  outputDir: 'tests/playwright/artifacts/jetpk-9h-b/live-results',
  globalSetup: './tests/playwright/jetpk-9h-b/global-setup-live.ts',
  timeout: 180_000,
  fullyParallel: false,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/playwright/artifacts/jetpk-9h-b/live-html-report', open: 'never' }],
    ['json', { outputFile: 'storage/app/audits/jetpk-9h-b/playwright-live-report.json' }],
  ],
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ...devices['Desktop Chrome'],
  },
  projects: viewports.flatMap((vp) =>
    roles.map((role) => ({
      name: `live-${role}-${vp.name}`,
      testMatch: /dashboard-role-pages\.spec\.ts/,
      grep: new RegExp(`role-${role} dashboard pages|public branding|branding consumption`),
      use: {
        viewport: vp.viewport,
        storageState: `${authDir}/${role}.json`,
      },
    })),
  ),
});
