import { defineConfig, devices } from "@playwright/test";

const smokePort = process.env.PLAYWRIGHT_PORT ?? "3021";
const mockPort = process.env.JP_01B_MOCK_API_PORT ?? "18081";
const baseURL = `http://127.0.0.1:${smokePort}`;
const laravelUrl = process.env.LARAVEL_URL ?? `http://127.0.0.1:${mockPort}`;

export default defineConfig({
  testDir: "./tests",
  testMatch: ["**/jp-ui-perf-01b-first-paint-branding.spec.ts"],
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: "list",
  timeout: 90_000,
  use: {
    baseURL,
    trace: "off",
  },
  webServer: [
    {
      command: `node scripts/jp-01b-branding-mock-api.mjs`,
      url: `http://127.0.0.1:${mockPort}/api/public/content/config`,
      reuseExistingServer: !process.env.CI,
      timeout: 30_000,
      env: {
        JP_01B_MOCK_API_PORT: mockPort,
      },
    },
    {
      command: "node scripts/jp-01b-evidence-server.mjs",
      url: baseURL,
      reuseExistingServer: false,
      timeout: 300_000,
      env: {
        PLAYWRIGHT_PORT: smokePort,
        JP_01B_MOCK_API_PORT: mockPort,
        LARAVEL_URL: laravelUrl,
        NEXT_PUBLIC_LARAVEL_URL: laravelUrl,
        NODE_ENV: "production",
        NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
        OTA_ALLOW_SESSION_FIXTURE: "true",
        OTA_ALLOW_CONTENT_FIXTURE: "false",
        NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "false",
      },
    },
  ],
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
