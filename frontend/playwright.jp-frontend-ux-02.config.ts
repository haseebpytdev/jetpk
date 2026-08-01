import { defineConfig, devices } from "@playwright/test";

const smokePort = process.env.PLAYWRIGHT_PORT ?? "3013";
const baseURL = `http://127.0.0.1:${smokePort}`;
const isCi = !!process.env.CI;

export default defineConfig({
  testDir: "./tests/jp-frontend-ux-02",
  testIgnore: [/evidence-capture\.spec\.ts$/],
  fullyParallel: false,
  forbidOnly: isCi,
  retries: 0,
  workers: 1,
  reporter: [["list"]],
  timeout: 90_000,
  expect: { timeout: 20_000 },
  use: {
    baseURL,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    ...devices["Desktop Chrome"],
  },
  webServer: {
    command: "node scripts/playwright-server.mjs",
    url: baseURL,
    reuseExistingServer: !isCi,
    timeout: 300_000,
    env: {
      PLAYWRIGHT_PORT: smokePort,
      NODE_ENV: "production",
      NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
      OTA_ALLOW_SESSION_FIXTURE: "true",
      NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "true",
    },
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
