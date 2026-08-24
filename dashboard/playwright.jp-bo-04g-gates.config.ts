import { defineConfig, devices } from "@playwright/test";

const smokePort = process.env.PLAYWRIGHT_PORT ?? "3015";
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${smokePort}`;

export default defineConfig({
  testDir: "./tests",
  testMatch: ["**/jp-bo-04g-booking-checkout-gates.spec.ts"],
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: "list",
  timeout: 120_000,
  use: {
    baseURL,
    trace: "off",
  },
  webServer: {
    command: "node scripts/playwright-server.mjs",
    url: `${baseURL}/admin/dashboard`,
    reuseExistingServer: false,
    timeout: 180_000,
    env: {
      PLAYWRIGHT_PORT: smokePort,
      NODE_ENV: "production",
    },
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
