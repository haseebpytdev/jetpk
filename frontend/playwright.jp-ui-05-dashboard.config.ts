import path from "node:path";
import { defineConfig, devices } from "@playwright/test";

const smokePort = process.env.PLAYWRIGHT_PORT ?? "3003";
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${smokePort}`;
const dashboardRoot = path.resolve(__dirname, "..", "dashboard");
const isCi = !!process.env.CI;

export default defineConfig({
  testDir: "./tests/visual-audit",
  testMatch: "jp-ui-05-dashboard-visual-matrix.spec.ts",
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
  webServer: {
    command: `npm run start -- -p ${smokePort}`,
    cwd: dashboardRoot,
    url: `${baseURL}/admin/dashboard`,
    reuseExistingServer: !isCi,
    timeout: 300_000,
    stdout: "pipe",
    stderr: "pipe",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
