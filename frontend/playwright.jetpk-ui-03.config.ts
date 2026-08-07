import { defineConfig, devices } from "@playwright/test";

const smokePort = process.env.PLAYWRIGHT_PORT ?? "3003";
const baseURL = `http://127.0.0.1:${smokePort}`;
const isCi = !!process.env.CI;

/**
 * UI-03 homepage photography and shell-contract tests use the dev server so
 * approved static homepage media fixtures are available without mutating production
 * preview infrastructure (JETPK-UI-001).
 */
export default defineConfig({
  testDir: "./tests",
  fullyParallel: false,
  forbidOnly: isCi,
  retries: 0,
  workers: 1,
  reporter: "list",
  timeout: 60_000,
  use: {
    baseURL,
    trace: "off",
  },
  webServer: {
    command: `npx next dev -p ${smokePort}`,
    url: baseURL,
    reuseExistingServer: !isCi,
    timeout: 600_000,
    stdout: "pipe",
    stderr: "pipe",
    env: {
      PLAYWRIGHT_PORT: smokePort,
      NODE_ENV: "development",
      NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
      NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "true",
    },
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
