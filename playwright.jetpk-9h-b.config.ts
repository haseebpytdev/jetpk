import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.JETPK_BASE_URL || 'http://127.0.0.1:8000';
const authDir = 'storage/app/playwright/jetpk-9h-b/auth';

const viewports = [
  { name: 'desktop-1440', viewport: { width: 1440, height: 900 } },
  { name: 'desktop-1280', viewport: { width: 1280, height: 800 } },
  { name: 'tablet-1024', viewport: { width: 1024, height: 768 } },
  { name: 'tablet-portrait', viewport: { width: 768, height: 1024 } },
  { name: 'mobile', viewport: { width: 390, height: 844 } },
];

const roles = ['admin', 'staff', 'agent', 'agent-staff', 'customer'] as const;

const roleGrep: Record<(typeof roles)[number], RegExp> = {
  admin: /role-admin dashboard pages/,
  staff: /role-staff dashboard pages|staff forbidden admin modules/,
  agent: /role-agent dashboard pages/,
  'agent-staff': /role-agent-staff dashboard pages/,
  customer: /role-customer dashboard pages|customer forbidden admin links/,
};

export default defineConfig({
  testDir: 'tests/playwright/jetpk-9h-b',
  outputDir: 'tests/playwright/artifacts/jetpk-9h-b/results',
  globalSetup: './tests/playwright/jetpk-9h-b/global-setup.ts',
  timeout: 120_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/playwright/artifacts/jetpk-9h-b/html-report', open: 'never' }],
    ['json', { outputFile: 'storage/app/audits/jetpk-9h-b/playwright-report.json' }],
  ],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    ...devices['Desktop Chrome'],
  },
  projects: [
    ...viewports.flatMap((vp) =>
      roles.map((role) => ({
        name: `${role}-${vp.name}`,
        testMatch: /dashboard-role-pages\.spec\.ts/,
        grep: roleGrep[role],
        use: {
          viewport: vp.viewport,
          storageState: `${authDir}/${role}.json`,
        },
      })),
    ),
    ...viewports.map((vp) => ({
      name: `public-${vp.name}`,
      testMatch: /dashboard-role-pages\.spec\.ts/,
      grep: /public branding|error shells/,
      use: { viewport: vp.viewport },
    })),
    {
      name: 'branding-public-desktop',
      testMatch: /branding-consumption\.spec\.ts/,
      grep: /public header|public footer|auth layout|error page|favicon|booking safe/,
      use: { viewport: { width: 1440, height: 900 } },
    },
    {
      name: 'branding-consumption-desktop',
      testMatch: /branding-consumption\.spec\.ts/,
      grep: /admin dashboard sidebar|page settings preview|email template preview|role shells/,
      use: {
        viewport: { width: 1440, height: 900 },
        storageState: `${authDir}/admin.json`,
      },
    },
    {
      name: 'branding-consumption-mobile',
      testMatch: /branding-consumption\.spec\.ts/,
      grep: /mobile drawer/,
      use: { viewport: { width: 390, height: 844 } },
    },
    {
      name: 'branding-fallback-desktop',
      testMatch: /branding-consumption\.spec\.ts/,
      grep: /fallback when logo cleared/,
      use: { viewport: { width: 1440, height: 900 } },
    },
  ],
});
