import { defineConfig, devices } from "@playwright/test";
import path from "node:path";

const baseURL = process.env.E2E_PROXY_ORIGIN ?? process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:9080";
const authDir = path.join(process.cwd(), "tmp", "e2e-auth");
const specsDir = path.join(process.cwd(), "tests", "e2e", "playwright", "specs", "auth");

export default defineConfig({
  testDir: specsDir,
  timeout: 180_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [
    ["list"],
    ["html", { open: "never", outputFolder: "playwright-report/auth-e2e" }],
  ],
  use: {
    baseURL,
    headless: process.env.E2E_HEADED === "1" ? false : true,
    video: "retain-on-failure",
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
    actionTimeout: 25_000,
    navigationTimeout: 60_000,
  },
  projects: [
    {
      name: "setup",
      testMatch: /00-bootstrap-storage-state\.spec\.ts/,
    },
    {
      name: "desktop-chrome",
      // Prefer prior storage-state when present; run setup explicitly when missing.
      dependencies: process.env.E2E_SKIP_SETUP === "1" ? [] : ["setup"],
      use: {
        ...devices["Desktop Chrome"],
        viewport: { width: 1440, height: 900 },
        storageState: path.join(authDir, "admin.json"),
      },
      testIgnore: /00-bootstrap-storage-state\.spec\.ts/,
    },
    {
      name: "mobile-chrome",
      dependencies: process.env.E2E_SKIP_SETUP === "1" ? [] : ["setup"],
      use: {
        ...devices["Pixel 5"],
        viewport: { width: 390, height: 844 },
        storageState: path.join(authDir, "admin.json"),
      },
      testMatch: /05-responsive\.spec\.ts/,
    },
  ],
});
