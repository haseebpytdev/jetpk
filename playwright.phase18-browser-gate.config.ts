/**
 * Phase 18J bounded browser regression gate (local only, retries=0, workers=1).
 *
 * Run shards separately to avoid wall-clock termination:
 *   $env:PLAYWRIGHT_BASE_URL='http://127.0.0.1:8000'
 *   npx playwright test -c playwright.phase18-browser-gate.config.ts --project=shard1-header-filter
 *   npx playwright test -c playwright.phase18-browser-gate.config.ts --project=shard2-search-scale
 *   npx playwright test -c playwright.phase18-browser-gate.config.ts --project=shard3-flow-audit
 *   npx playwright test -c playwright.phase18-browser-gate.config.ts --project=shard5-public-flights
 */
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

if (!['127.0.0.1', 'localhost', '0.0.0.0', '::1'].includes(host)) {
  throw new Error(`Phase 18 browser gate requires local base URL, got "${LOCAL_OTA_URL}"`);
}

process.env.JETPK_CLIENT_PREFIX ??= '';

const baseUse = {
  baseURL: LOCAL_OTA_URL.replace(/\/$/, ''),
  headless: true,
  ignoreHTTPSErrors: true,
  navigationTimeout: 120_000,
  actionTimeout: 45_000,
};

export default defineConfig({
  timeout: 600_000,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  outputDir: 'storage/app/audits/phase18-browser-gate/traces',
  projects: [
    {
      name: 'shard1-header-filter',
      testDir: './tests/playwright/jetpk',
      testMatch: /(header-filter-highlight-parity|filter-panel-visual|results-body-alignment)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], ...baseUse },
    },
    {
      name: 'shard2-search-scale',
      testDir: './tests/playwright/jetpk',
      testMatch: /homepage-search-ui-vertical-scale\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], ...baseUse },
    },
    {
      name: 'shard3-flow-audit',
      testDir: './tests/playwright/jetpk',
      testMatch: /live-visual-flow-audit\.spec\.ts/,
      grep: /one-way results flow|return outbound \+ inbound flow|checkout visual flow|client no-fallback URL scan|public pages \+ viewport leak scan|search UI parity|results search UI parity/,
      use: { ...devices['Desktop Chrome'], ...baseUse },
    },
    {
      name: 'shard5-public-flights',
      testDir: './tests/visual',
      testMatch: /public-critical-responsive\.spec\.ts/,
      grep: /flights-search|flights-results/,
      use: { ...devices['Desktop Chrome'], ...baseUse },
    },
  ],
});
