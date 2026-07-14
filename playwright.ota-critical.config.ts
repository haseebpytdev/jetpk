/**
 * Combined fast critical UI checks (~10–15 min): route-manifest smoke, public dropdown,
 * guest public-critical responsive, agent-critical responsive.
 */
import { defineConfig, devices } from '@playwright/test';

process.env.OTA_AUDIT_ROLES ??= 'agent,agent_staff_restricted,agent_staff_full';
process.env.OTA_AUDIT_AGENT_EMAIL ??= 'agent.jetpakistan@example.test';
process.env.OTA_AUDIT_AGENT_STAFF_RESTRICTED_EMAIL ??= 'staff.restricted@ota.demo';
process.env.OTA_AUDIT_AGENT_STAFF_FULL_EMAIL ??= 'staff.full@ota.demo';
process.env.OTA_AUDIT_PASSWORD ??= 'Password123!';

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
  testMatch:
    /(route-manifest\.smoke|public-search-dropdown|public-critical-responsive|agent-critical-responsive)\.spec\.ts/,
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
    trace: 'retain-on-failure',
    actionTimeout: 12_000,
    navigationTimeout: 25_000,
  },
  globalSetup: './tests/visual/global-setup.ts',
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
  ],
});
