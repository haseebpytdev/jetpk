import { defineConfig, devices } from "@playwright/test";

const baseURL = (process.env.PLAYWRIGHT_PRODUCTION_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");

const host = (() => {
  try {
    return new URL(baseURL).hostname;
  } catch {
    return "";
  }
})();

const forbiddenHosts = ["ota.haseebasif.com", "haseebasif.com", "jetpakistan.com"];
if (forbiddenHosts.includes(host) || host === "127.0.0.1" || host === "localhost") {
  throw new Error(`Production FAB audit must target https://jetpakistan.pk, not "${baseURL}".`);
}

if (!process.env.OTA_AUDIT_ALLOW_REMOTE) {
  throw new Error(`Production FAB audit requires OTA_AUDIT_ALLOW_REMOTE=1 (base URL: ${baseURL}).`);
}

export default defineConfig({
  testDir: "./tests",
  testMatch: /jp-fab-geometry-matrix-01\.spec\.ts/,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: "list",
  timeout: 120_000,
  expect: { timeout: 30_000 },
  use: {
    baseURL,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
