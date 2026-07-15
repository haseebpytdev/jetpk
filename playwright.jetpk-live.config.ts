<<<<<<< HEAD
/**
 * JetPK live production visual flow isolation audit (JETPK-LIVE-PLAYWRIGHT-8D).
 * Runs from local/Cursor only — never on Hostinger shared server.
=======
﻿/**
 * JetPK live production visual flow isolation audit (JETPK-LIVE-PLAYWRIGHT-8D).
 * Runs from local/Cursor only â€” never on Hostinger shared server.
>>>>>>> jetpk/main
 */
import { defineConfig, devices } from '@playwright/test';

const LIVE_BASE_URL =
<<<<<<< HEAD
  process.env.JETPK_LIVE_BASE_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'https://ota.haseebasif.com';
=======
  process.env.JETPK_LIVE_BASE_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'https://jetpakistan.pk';
>>>>>>> jetpk/main

const host = (() => {
  try {
    return new URL(LIVE_BASE_URL).hostname;
  } catch {
    return '';
  }
})();

const isLocalHost = ['127.0.0.1', 'localhost', '0.0.0.0', '::1'].includes(host);
if (isLocalHost) {
  throw new Error(
<<<<<<< HEAD
    `JetPK live audit must target production, not localhost ("${LIVE_BASE_URL}"). Set JETPK_LIVE_BASE_URL=https://ota.haseebasif.com`,
=======
    `JetPK live audit must target production, not localhost ("${LIVE_BASE_URL}"). Set JETPK_LIVE_BASE_URL=https://jetpakistan.pk`,
>>>>>>> jetpk/main
  );
}

if (!process.env.OTA_AUDIT_ALLOW_REMOTE) {
  throw new Error(
    `Remote live audit requires OTA_AUDIT_ALLOW_REMOTE=1 (base URL: ${LIVE_BASE_URL}).`,
  );
}

export default defineConfig({
  testDir: './tests/playwright/jetpk',
  testMatch: /live-visual-flow-audit\.spec\.ts/,
  timeout: 300_000,
  expect: { timeout: 30_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'storage/app/audits/jetpk-playwright-live/playwright-html-report' }],
  ],
  outputDir: 'storage/app/audits/jetpk-playwright-live/traces',
  globalTeardown: './tests/playwright/jetpk/global-teardown.ts',
  use: {
    baseURL: LIVE_BASE_URL.replace(/\/$/, ''),
    headless: true,
    screenshot: 'off',
    video: 'off',
    trace: 'retain-on-failure',
    actionTimeout: 45_000,
    navigationTimeout: 90_000,
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        channel: 'chrome',
      },
    },
  ],
});
<<<<<<< HEAD
=======

>>>>>>> jetpk/main
