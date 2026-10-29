import { defineConfig, devices } from "@playwright/test";

const smokePort = process.env.PLAYWRIGHT_PORT ?? "3019";
const mockPort = process.env.PR139_MOCK_API_PORT ?? "18080";
const baseURL = `http://127.0.0.1:${smokePort}`;
const isCi = !!process.env.CI;

export default defineConfig({
  testDir: "./tests",
  testMatch: "**/pr139-responsive-media-evidence.spec.ts",
  fullyParallel: false,
  forbidOnly: isCi,
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
      command: `node scripts/pr139-responsive-evidence-mock-api.mjs`,
      url: `http://127.0.0.1:${mockPort}/api/public/content/homepage`,
      reuseExistingServer: false,
      timeout: 60_000,
      env: {
        PR139_MOCK_API_PORT: mockPort,
      },
    },
    {
      command: "node scripts/pr139-evidence-server.mjs",
      url: baseURL,
      reuseExistingServer: false,
      timeout: 300_000,
      env: {
        PLAYWRIGHT_PORT: smokePort,
        PR139_MOCK_API_PORT: mockPort,
        NODE_ENV: "production",
        LARAVEL_URL: `http://127.0.0.1:${mockPort}`,
        NEXT_PUBLIC_LARAVEL_URL: `http://127.0.0.1:${mockPort}`,
        NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
        OTA_ALLOW_SESSION_FIXTURE: "true",
        OTA_ALLOW_CONTENT_FIXTURE: "false",
        NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "false",
      },
    },
  ],
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
