import { defineConfig, devices } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const baseURL = process.env.JP_CANARY_UAT_BASE ?? "https://jetpakistan.pk";
const storageState =
  process.env.JP_AI_CANARY_STORAGE_STATE ??
  path.resolve(__dirname, "../tmp/jp-ai-canary-admin-storage-state.json");

if (!process.env.OTA_AUDIT_ALLOW_REMOTE) {
  throw new Error("Production canary UAT requires OTA_AUDIT_ALLOW_REMOTE=1");
}

export default defineConfig({
  testDir: "./tests",
  testMatch: /jp-ai-production-canary-01\.spec\.ts/,
  globalSetup: "./scripts/canary-uat-global-setup.mjs",
  workers: 1,
  retries: 0,
  fullyParallel: false,
  reporter: [["list"], ["json", { outputFile: "test-results/jp-ai-canary-matrix.json" }]],
  timeout: 180_000,
  use: {
    baseURL,
    trace: "off",
    storageState: fs.existsSync(storageState) ? storageState : undefined,
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
