import { defineConfig, devices } from "@playwright/test";

const smokePort = process.env.PLAYWRIGHT_PORT ?? "3003";
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${smokePort}`;

const isCi = !!process.env.CI;

export default defineConfig({
  testDir: "./tests",
  testMatch: "**/*.spec.ts",
  testIgnore: ["**/regression/**"],
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: "list",
  timeout: 90_000,
  use: {
    baseURL,
    trace: "off",
  },
  webServer: {
    command: `npm run start -- -p ${smokePort}`,
    url: `${baseURL}/admin/dashboard`,
    reuseExistingServer: !isCi,
    timeout: 180_000,
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
