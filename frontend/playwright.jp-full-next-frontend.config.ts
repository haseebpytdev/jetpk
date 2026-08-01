import { defineConfig, devices } from "@playwright/test";

const smokePort = process.env.PLAYWRIGHT_PORT ?? "3012";
const baseURL = `http://127.0.0.1:${smokePort}`;
const isCi = !!process.env.CI;

export default defineConfig({
  testDir: "./tests",
  testMatch: [/jp-full-next-frontend\/.*\.spec\.ts$/, /jp-full-next-frontend-routes\.spec\.ts$/],
  fullyParallel: false,
  forbidOnly: isCi,
  retries: 0,
  workers: 1,
  reporter: [["list"], ["html", { open: "never", outputFolder: ".playwright-report/jp-full-next-frontend" }]],
  timeout: 90_000,
  expect: { timeout: 20_000 },
  use: {
    baseURL,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "off",
    ...devices["Desktop Chrome"],
  },
  webServer: {
    command: "node scripts/playwright-server.mjs",
    url: baseURL,
    reuseExistingServer: !isCi,
    timeout: 300_000,
    stdout: "pipe",
    stderr: "pipe",
    env: {
      PLAYWRIGHT_PORT: smokePort,
      NODE_ENV: "production",
      NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
      OTA_ALLOW_SESSION_FIXTURE: "true",
      NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "true",
      JP_THEME_LAB_ENABLED: "true",
    },
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
