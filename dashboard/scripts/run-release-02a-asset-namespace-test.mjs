#!/usr/bin/env node
/**
 * Release-02A: build dashboard with asset prefix and run namespace contract tests.
 */
import { spawnSync } from "node:child_process";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const dashboardRoot = join(__dirname, "..");
const assetPrefix = process.env.DASHBOARD_ASSET_PREFIX ?? "/dashboard-next";

const env = {
  ...process.env,
  DASHBOARD_ASSET_PREFIX: assetPrefix,
  NODE_ENV: "production",
};

function run(command, args) {
  const result = spawnSync(command, args, {
    cwd: dashboardRoot,
    stdio: "inherit",
    shell: true,
    env,
  });
  if ((result.status ?? 1) !== 0) {
    process.exit(result.status ?? 1);
  }
}

console.log(`[release-02a] Building dashboard with DASHBOARD_ASSET_PREFIX=${assetPrefix}`);
run("npm", ["run", "build"]);

console.log("[release-02a] Running asset namespace Playwright contract");
run("npx", ["playwright", "test", "-c", "playwright.release-02a.config.ts"]);

console.log("[release-02a] PASS");
