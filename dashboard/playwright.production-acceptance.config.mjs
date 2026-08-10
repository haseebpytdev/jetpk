import { defineConfig, devices } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "..");
const storagePath =
  process.env.JP_ADMIN_STORAGE_STATE ??
  path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");

const baseURL = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const hasStorage = fs.existsSync(storagePath);

export default defineConfig({
  testDir: "./tests",
  testMatch: [
    "jp-dash-03-production-acceptance.spec.ts",
    "jp-dash-03-deep-acceptance.spec.ts",
    "jp-dash-03-checkpoint-11.spec.ts",
    "jp-dash-03-checkpoint-12.spec.ts",
    "jp-dash-03-rbac-browser-matrix.spec.ts",
  ],
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: "list",
  timeout: 180_000,
  use: {
    baseURL,
    trace: "off",
    storageState: hasStorage ? storagePath : undefined,
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
